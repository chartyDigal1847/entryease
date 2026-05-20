<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExamScheduleResource;
use App\Models\ActivityLog;
use App\Models\ExamSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Versioned REST API for ExamSchedule resources.
 *
 * GET    /api/v1/exam-schedules          — list (all authenticated)
 * POST   /api/v1/exam-schedules          — create (admission_officer)
 * GET    /api/v1/exam-schedules/{id}     — show
 * PUT    /api/v1/exam-schedules/{id}     — update (admission_officer)
 * DELETE /api/v1/exam-schedules/{id}     — delete (admission_officer)
 */
class ExamScheduleController extends Controller
{
    /**
     * GET /api/v1/exam-schedules
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ExamSchedule::withCount(['applicants', 'questions'])
            ->withCount(['questions as active_questions_count' => fn($q) => $q->where('is_active', true)])
            ->orderBy('exam_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('exam_type')) {
            $query->where('exam_type', $request->exam_type);
        }

        if ($request->filled('date_from')) {
            $query->where('exam_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('exam_date', '<=', $request->date_to);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $schedules = $query->paginate($perPage)->withQueryString();

        return ExamScheduleResource::collection($schedules);
    }

    /**
     * POST /api/v1/exam-schedules
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireOfficer();

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'exam_date'    => 'required|date|after_or_equal:today',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i|after:start_time',
            'venue'        => 'nullable|string|max:255',
            'batch'        => 'nullable|string|max:100',
            'slots'        => 'required|integer|min:1|max:500',
            'instructions' => 'nullable|string|max:2000',
            'exam_type'    => ['required', Rule::in(['online', 'onsite'])],
        ]);

        $schedule = ExamSchedule::create($validated);

        ActivityLog::record(
            "API: Exam schedule created: \"{$schedule->title}\" by " . session('sso_name'),
            'blue'
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Exam schedule created.',
            'schedule' => new ExamScheduleResource($schedule),
        ], 201);
    }

    /**
     * GET /api/v1/exam-schedules/{schedule}
     */
    public function show(ExamSchedule $examSchedule): ExamScheduleResource
    {
        $examSchedule->loadCount(['applicants', 'questions']);
        return new ExamScheduleResource($examSchedule);
    }

    /**
     * PUT /api/v1/exam-schedules/{schedule}
     */
    public function update(Request $request, ExamSchedule $examSchedule): JsonResponse
    {
        $this->requireOfficer();

        $validated = $request->validate([
            'title'        => 'sometimes|required|string|max:255',
            'exam_date'    => 'sometimes|required|date',
            'start_time'   => 'sometimes|required|date_format:H:i',
            'end_time'     => 'sometimes|required|date_format:H:i',
            'venue'        => 'nullable|string|max:255',
            'batch'        => 'nullable|string|max:100',
            'slots'        => 'sometimes|required|integer|min:1|max:500',
            'instructions' => 'nullable|string|max:2000',
            'status'       => ['sometimes', Rule::in(['upcoming', 'ongoing', 'completed', 'cancelled'])],
            'exam_type'    => ['sometimes', Rule::in(['online', 'onsite'])],
        ]);

        $examSchedule->update($validated);

        ActivityLog::record(
            "API: Exam schedule updated: \"{$examSchedule->title}\" by " . session('sso_name'),
            'amber'
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Exam schedule updated.',
            'schedule' => new ExamScheduleResource($examSchedule->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/exam-schedules/{schedule}
     */
    public function destroy(ExamSchedule $examSchedule): JsonResponse
    {
        $this->requireOfficer();

        if ($examSchedule->scores()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a schedule that has recorded scores.',
            ], 422);
        }

        $title = $examSchedule->title;
        $examSchedule->delete();

        ActivityLog::record("API: Exam schedule deleted: \"{$title}\" by " . session('sso_name'), 'red');

        return response()->json([
            'success' => true,
            'message' => "Exam schedule \"{$title}\" deleted.",
        ]);
    }

    private function requireOfficer(): void
    {
        $role = session('sso_role', 'student');
        $isOfficer = in_array($role, ['admission_officer', 'registrar', 'hr'], true);

        if (! $isOfficer) {
            abort(403, 'Only Admission Officers can manage exam schedules.');
        }
    }
}
