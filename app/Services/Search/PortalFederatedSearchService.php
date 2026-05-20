<?php

namespace App\Services\Search;

use App\Models\Applicant;
use Illuminate\Support\Facades\DB;

/**
 * Applicant records surfaced to the DEORIS portal federated search aggregator.
 *
 * Searches applicants by joining with DEORIS user data (name/email).
 * Each hit uses the standard DEORIS keys: type, title, subtitle, url, score, meta.
 */
final readonly class PortalFederatedSearchService
{
    /**
     * @return array{data: list<array<string, mixed>>}
     */
    public function search(string $query, int $limit): array
    {
        $limit   = max(1, min($limit, 25));
        $pattern = '%' . addcslashes($query, '%_\\') . '%';

        // Join applicants with DEORIS users to search by name/email
        $results = Applicant::query()
            ->select('applicants.*')
            ->join(
                DB::raw('(SELECT id, name, email FROM ' . DB::connection('deoris')->getDatabaseName() . '.users) AS du'),
                'applicants.deoris_user_id',
                '=',
                'du.id'
            )
            ->where(function ($q) use ($pattern) {
                $q->where('du.name', 'like', $pattern)
                  ->orWhere('du.email', 'like', $pattern)
                  ->orWhere('applicants.additional_info', 'like', $pattern);
            })
            ->addSelect(DB::raw('du.name as _user_name, du.email as _user_email'))
            ->orderBy('du.name')
            ->limit($limit)
            ->get();

        $base   = rtrim((string) config('app.url'), '/');
        $module = config('deoris.module_name', 'EntryEase');

        $data = [];
        foreach ($results as $applicant) {
            $name  = $applicant->_user_name ?? "Applicant #{$applicant->id}";
            $email = $applicant->_user_email ?? '';

            $data[] = [
                'type'        => 'applicant',
                'title'       => $name,
                'subtitle'    => $email,
                'description' => "Applicant — {$applicant->effective_status} — {$applicant->grade_level}",
                'url'         => $base . '/admin/applications',
                'score'       => self::score($query, $name, $email),
                'meta'        => [
                    'applicant_id'     => $applicant->id,
                    'deoris_user_id'   => $applicant->deoris_user_id,
                    'status'           => $applicant->effective_status,
                    'grade_level'      => $applicant->grade_level,
                    'module'           => $module,
                ],
            ];
        }

        return ['data' => $data];
    }

    private static function score(string $needle, string $fullName, string $email): float
    {
        $n    = mb_strtolower($needle);
        $name = mb_strtolower($fullName);
        $mail = mb_strtolower($email);

        if ($name === $n || $mail === $n) {
            return 1.0;
        }

        if (str_starts_with($name, $n) || str_starts_with($mail, $n)) {
            return 0.92;
        }

        return 0.82;
    }
}
