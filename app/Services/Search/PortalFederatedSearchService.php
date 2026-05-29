<?php

namespace App\Services\Search;

use App\Models\Applicant;

/**
 * Applicant records surfaced to the DEORIS portal federated search aggregator.
 */
final readonly class PortalFederatedSearchService
{
    /**
     * @return array{data: list<array<string, mixed>>}
     */
    public function search(string $query, int $limit): array
    {
        $limit = max(1, min($limit, 25));
        $pattern = '%'.addcslashes($query, '%_\\').'%';

        $results = Applicant::query()
            ->where(function ($q) use ($pattern) {
                $q->where('portal_student_name', 'like', $pattern)
                    ->orWhere('portal_student_email', 'like', $pattern)
                    ->orWhere('additional_info', 'like', $pattern);
            })
            ->orderBy('portal_student_name')
            ->limit($limit)
            ->get();

        $base = rtrim((string) config('app.url'), '/');
        $module = config('deoris.module_name', 'EntryEase');

        $data = [];
        foreach ($results as $applicant) {
            $name = $applicant->portal_student_name ?? "Applicant #{$applicant->id}";
            $email = $applicant->portal_student_email ?? '';

            $data[] = [
                'type' => 'applicant',
                'title' => $name,
                'subtitle' => $email,
                'description' => "Applicant — {$applicant->effective_status} — {$applicant->grade_level}",
                'url' => $base.'/admin/applications',
                'score' => self::score($query, $name, $email),
                'meta' => [
                    'applicant_id' => $applicant->id,
                    'deoris_user_id' => $applicant->deoris_user_id,
                    'status' => $applicant->effective_status,
                    'grade_level' => $applicant->grade_level,
                    'module' => $module,
                ],
            ];
        }

        return ['data' => $data];
    }

    private static function score(string $needle, string $fullName, string $email): float
    {
        $n = mb_strtolower($needle);
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
