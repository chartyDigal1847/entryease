<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ExamSchedule;
use App\Models\ExamScore;
use App\Models\DeorisProcessedEvent;
use App\Models\DeorisEventOutbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Service statistics endpoint for DEORIS portal dashboard.
 *
 * GET /api/v1/stats — aggregate service health and metrics
 */
class StatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $role = session('sso_role', 'student');
        $isPrivileged = in_array($role, ['admin', 'admission_officer', 'registrar', 'hr'], true);

        if (! $isPrivileged) {
            abort(403, 'Stats endpoint requires admin or officer role.');
        }

        $applicantStats = [
            'total'        => Applicant::count(),
            'pending'      => Applicant::pending()->count(),
            'under_review' => Applicant::underReview()->count(),
            'approved'     => Applicant::approved()->count(),
            'rejected'     => Applicant::rejected()->count(),
            'with_exam'    => Applicant::whereNotNull('exam_schedule_id')->count(),
            'scored'       => ExamScore::count(),
        ];

        $examStats = [
            'total_schedules' => ExamSchedule::count(),
            'upcoming'        => ExamSchedule::upcoming()->count(),
            'completed'       => ExamSchedule::completed()->count(),
        ];

        $scoreStats = null;
        if ($isPrivileged) {
            $raw = ExamScore::query()
                ->whereNotNull('total_items')
                ->where('total_items', '>', 0)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN score / total_items >= 0.75 THEN 1 ELSE 0 END) as passed')
                ->selectRaw('AVG(score / NULLIF(total_items, 0) * 100) as average')
                ->first();

            $scoreStats = [
                'total'   => (int) ($raw->total ?? 0),
                'passed'  => (int) ($raw->passed ?? 0),
                'average' => $raw->average !== null ? round($raw->average, 1) : null,
            ];
        }

        $eventStats = [
            'processed_events' => DeorisProcessedEvent::count(),
            'pending_outbox'   => DeorisEventOutbox::where('status', 'pending')->count(),
            'failed_outbox'    => DeorisEventOutbox::where('status', 'failed')->count(),
        ];

        return response()->json([
            'service'    => config('deoris.module_name', 'EntryEase'),
            'version'    => config('deoris.event_version', '1.0'),
            'applicants' => $applicantStats,
            'exams'      => $examStats,
            'scores'     => $scoreStats,
            'events'     => $eventStats,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
