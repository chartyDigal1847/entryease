<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicantCollection;
use App\Http\Resources\ApplicantResource;
use App\Models\Applicant;
use App\Models\ActivityLog;
use App\Services\Admission\AdmissionEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Versioned REST API for Applicant resources.
 *
 * All endpoints require SSO authentication (enforced via middleware).
 * Role-based access is enforced per action.
 *
 * GET    /api/v1/applicants          — list (admin, admission_officer)
 * POST   /api/v1/applicants          — create (student: own; officer: any)
 * GET    /api/v1/applicants/{id}     — show
 * PUT    /api/v1/applicants/{id}     — update status (admission_officer only)
 * DELETE /api/v1/applicants/{id}     — delete (admission_officer only)
 */
class ApplicantController extends Controller
{
    public function __construct(
        private readonly AdmissionEventService $eventService,
    ) {}

    /**
     * GET /api/v1/applicants
     *
     * List applicants with filtering and pagination.
     * Admission officers and admins see all; students see only their own.
     */
    public function index(Request $request): ApplicantCollection
    {
        $role = $this->currentRole();

        $query = Applicant::with(['examSchedule', 'examScore'])
            ->orderByDesc('created_at');

        // Students can only see their own applications
        if ($role === 'student') {
            $query->where('deoris_user_id', session('sso_id'));
        }

        // Filters (officers/admins only)
        if ($this->isOfficerOrAdmin($role)) {
            if ($request->filled('status')) {
                $query->where(function ($q) use ($request) {
                    $q->where('status', $request->status)
                      ->orWhere('admission_status', strtolower(str_replace(' ', '_', $request->status)));
                });
            }

            if ($request->filled('grade_level')) {
                $query->where('grade_level', $request->grade_level);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('additional_info', 'like', "%{$search}%");
            }

            if ($request->filled('exam_schedule_id')) {
                $query->where('exam_schedule_id', $request->exam_schedule_id);
            }
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $applicants = $query->paginate($perPage)->withQueryString();

        return new ApplicantCollection($applicants);
    }

    /**
     * POST /api/v1/applicants
     *
     * Students submit their own application.
     * Officers can create on behalf of a student.
     */
    public function store(Request $request): JsonResponse
    {
        $role = $this->currentRole();

        if ($role === 'student') {
            return $this->storeStudentApplication($request);
        }

        if ($this->isOfficerOrAdmin($role)) {
            return $this->storeOfficerApplication($request);
        }

        return $this->forbidden('You are not authorized to create applications.');
    }

    /**
     * GET /api/v1/applicants/{applicant}
     */
    public function show(Applicant $applicant): ApplicantResource
    {
        $role = $this->currentRole();

        // Students can only view their own
        if ($role === 'student' && (string) $applicant->deoris_user_id !== (string) session('sso_id')) {
            abort(403, 'You can only view your own application.');
        }

        $applicant->load(['examSchedule', 'examScore', 'latestExamAttempt']);

        return new ApplicantResource($applicant);
    }

    /**
     * PUT /api/v1/applicants/{applicant}
     *
     * Update application status (admission_officer only).
     */
    public function update(Request $request, Applicant $applicant): JsonResponse
    {
        $role = $this->currentRole();

        if (! $this->isOfficer($role)) {
            return $this->forbidden('Only Admission Officers can update application status.');
        }

        if (in_array($applicant->status, [Applicant::STATUS_APPROVED, Applicant::STATUS_REJECTED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a finalized application.',
            ], 422);
        }

        $allowed = $applicant->nextStatuses();

        if (empty($allowed)) {
            return response()->json([
                'success' => false,
                'message' => 'No further status changes are allowed for this application.',
            ], 422);
        }

        $validated = $request->validate([
            'status'     => ['required', Rule::in($allowed)],
            'admin_notes'=> 'nullable|string|max:2000',
        ]);

        $previousStatus = $applicant->status;

        try {
            $applicant->transitionTo($validated['status'], (string) session('sso_id'));

            if (array_key_exists('admin_notes', $validated)) {
                $applicant->update(['admin_notes' => $validated['admin_notes'] ?? $applicant->admin_notes]);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'invalid_transition',
                'message' => $e->getMessage(),
            ], 422);
        }

        $this->eventService->statusChanged(
            $applicant->fresh(),
            $previousStatus,
            (string) session('sso_id'),
        );

        ActivityLog::record(
            "API: Applicant #{$applicant->id} status → {$validated['status']} by " . session('sso_name'),
            'amber'
        );

        return response()->json([
            'success'   => true,
            'message'   => 'Application status updated.',
            'applicant' => new ApplicantResource($applicant->fresh(['examSchedule', 'examScore'])),
        ]);
    }

    /**
     * DELETE /api/v1/applicants/{applicant}
     *
     * Soft-delete an application (admission_officer only).
     */
    public function destroy(Applicant $applicant): JsonResponse
    {
        $role = $this->currentRole();

        if (! $this->isOfficer($role)) {
            return $this->forbidden('Only Admission Officers can delete applications.');
        }

        $id = $applicant->id;
        $applicant->delete();

        ActivityLog::record("API: Applicant #{$id} deleted by " . session('sso_name'), 'red');

        return response()->json([
            'success' => true,
            'message' => "Applicant #{$id} deleted.",
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function storeStudentApplication(Request $request): JsonResponse
    {
        $deorisUserId = session('sso_id');

        if (Applicant::where('deoris_user_id', $deorisUserId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted an application.',
            ], 422);
        }

        $validated = $request->validate([
            'grade_level'     => 'required|string|in:Grade 7',
            'phone'           => 'required|string|max:20',
            'previous_school' => 'required|string|max:255',
        ]);

        $applicant = Applicant::create([
            'deoris_user_id'       => $deorisUserId,
            'portal_student_email' => session('sso_email'),
            'portal_student_name'  => session('sso_name'),
            'grade_level'      => $validated['grade_level'],
            'additional_info'  => json_encode([
                'phone'           => $validated['phone'],
                'previous_school' => $validated['previous_school'],
            ]),
            'status'           => 'Pending',
            'admission_status' => 'pending',
        ]);

        $this->eventService->applicationSubmitted($applicant);

        return response()->json([
            'success'   => true,
            'message'   => 'Application submitted successfully.',
            'applicant' => new ApplicantResource($applicant),
        ], 201);
    }

    private function storeOfficerApplication(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deoris_user_id'  => 'required|integer',
            'grade_level'     => 'required|string|in:Grade 7',
            'additional_info' => 'nullable|array',
            'status'          => ['nullable', Rule::in([Applicant::STATUS_PENDING, Applicant::STATUS_UNDER_REVIEW])],
        ]);

        $initialStatus = $validated['status'] ?? Applicant::STATUS_PENDING;

        $applicant = Applicant::create([
            'deoris_user_id'   => $validated['deoris_user_id'],
            'grade_level'      => $validated['grade_level'],
            'additional_info'  => isset($validated['additional_info'])
                ? json_encode($validated['additional_info'])
                : null,
            'status'           => $initialStatus,
            'admission_status' => Applicant::admissionStatusFor($initialStatus),
        ]);

        $this->eventService->applicationSubmitted($applicant);

        ActivityLog::record(
            "API: Applicant #{$applicant->id} created by officer " . session('sso_name'),
            'blue'
        );

        return response()->json([
            'success'   => true,
            'message'   => 'Applicant created.',
            'applicant' => new ApplicantResource($applicant),
        ], 201);
    }

    private function currentRole(): string
    {
        $role = session('sso_role', 'student');
        return match ($role) {
            'hr', 'registrar', 'admission_officer' => 'admission_officer',
            'admin' => 'admin',
            default => 'student',
        };
    }

    private function isOfficerOrAdmin(string $role): bool
    {
        return in_array($role, ['admission_officer', 'admin'], true);
    }

    private function isOfficer(string $role): bool
    {
        return $role === 'admission_officer';
    }

    private function admissionStatusFor(string $status): string
    {
        return Applicant::admissionStatusFor($status);
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 403);
    }
}
