<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ExamSchedule;
use Illuminate\Http\Request;
use App\Services\Admission\AdmissionEventService;
use App\Services\DeorisUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class RegistrarController extends Controller
{
    /**
     * Get SSO user context from session.
     */
    private function userContext(): array
    {
        return [
            'id'    => session('sso_id'),
            'name'  => session('sso_name', 'Admission Officer'),
            'email' => session('sso_email', ''),
            'role'  => session('sso_role', 'admission_officer'),
        ];
    }

    private function userId(Request $request)
    {
        return session('sso_id') ?? $request->header('X-User-ID');
    }

    private function admissionStatusFor(string $status): string
    {
        return match ($status) {
            'Approved' => 'approved',
            'Rejected' => 'rejected',
            'Under Review' => 'under_review',
            default => 'pending',
        };
    }

    public function dashboard()
    {
        $stats = [
            'total'          => Applicant::count(),
            'pending'        => Applicant::pending()->count(),
            'under_review'   => Applicant::underReview()->count(),
            'approved'       => Applicant::approved()->count(),
            'rejected'       => Applicant::rejected()->count(),
            'scheduled'      => Applicant::whereNotNull('exam_schedule_id')->count(),
            'scored'         => \App\Models\ExamScore::count(),
            'total_students' => Applicant::distinct('deoris_user_id')->count(),
        ];

        $applications = Applicant::with(['student', 'examSchedule', 'examScore'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Exam schedules with question counts for the exam management panel
        $examSchedules = ExamSchedule::withCount(['questions', 'applicants'])
            ->withCount(['questions as active_questions_count' => fn($q) => $q->where('is_active', true)])
            ->orderBy('exam_date')
            ->get();

        $userContext = $this->userContext();

        return view('admission.registrar.dashboard', compact(
            'stats', 'applications', 'examSchedules', 'userContext'
        ));
    }

    public function applications(Request $request)
    {
        $applications = Applicant::with(['student', 'examSchedule', 'examScore'])
            ->when($request->filled('status'), fn($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                // Search in additional_info JSON field for phone/previous_school
                $query->where('additional_info', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $userContext = $this->userContext();

        return view('admission.registrar.applications', compact('applications', 'userContext'));
    }

    public function viewApplication(Applicant $applicant)
    {
        $applicant->load(['student', 'examSchedule', 'examScore']);
        $userContext = $this->userContext();
        return view('admission.registrar.view-application', compact('applicant', 'userContext'));
    }

    public function updateStatus(Request $request, Applicant $applicant)
    {
        // Prevent status updates for Approved and Rejected applications
        if (in_array($applicant->status, ['Approved', 'Rejected'], true)) {
            return back()->with('error', 'Cannot update status for approved or rejected applications.');
        }

        $validated = $request->validate([
            'status' => 'required|in:Pending,Under Review,Approved,Rejected',
            'notes'  => 'nullable|string|max:1000',
        ]);

        $previousStatus = $applicant->status;

        try {
            // Prepare update data - only update notes if provided and not empty
            $updateData = [
                'status'           => $validated['status'],
                'admission_status' => $this->admissionStatusFor($validated['status']),
                'reviewed_by'      => $this->userId($request),
            ];

            // Only update admin_notes if new notes are provided
            if (!empty($validated['notes'])) {
                $updateData['admin_notes'] = $validated['notes'];
            }

            $applicant->update($updateData);

            Log::info('[Admission] Application status updated', [
                'applicant_id' => $applicant->id,
                'previous_status' => $previousStatus,
                'new_status' => $validated['status'],
                'new_admission_status' => $this->admissionStatusFor($validated['status']),
                'reviewed_by' => $this->userId($request),
            ]);

            app(AdmissionEventService::class)->statusChanged(
                $applicant->fresh(),
                $previousStatus,
                (string) $this->userId($request),
            );

            // Update DEORIS user admission status for Approved, Rejected, and Under Review
            if (in_array($validated['status'], ['Approved', 'Rejected', 'Under Review'], true)) {
                if ($applicant->deoris_user_id) {
                    $deorisStatus = $this->admissionStatusFor($validated['status']);
                    app(DeorisUserService::class)->updateAdmissionStatusById(
                        $applicant->deoris_user_id,
                        $deorisStatus
                    );

                    Log::info('[Admission] DEORIS admission status updated', [
                        'applicant_id' => $applicant->id,
                        'deoris_user_id' => $applicant->deoris_user_id,
                        'deoris_status' => $deorisStatus,
                    ]);
                }
            }

            return redirect()->route('registrar.applications')->with('success', 'Application status updated successfully.');
        } catch (\Exception $e) {
            Log::error('[Admission] Error updating application status', [
                'applicant_id' => $applicant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->with('error', 'Failed to update application status: ' . $e->getMessage());
        }
    }

    public function viewStudentDocuments(Applicant $applicant)
    {
        $userContext = $this->userContext();

        return view('admission.registrar.student-documents', compact('applicant', 'userContext'));
    }

    public function downloadStudentDocument(Applicant $applicant, $documentType)
    {
        $allowedTypes = ['photo_2x2', 'psa_birth_cert'];

        if (!in_array($documentType, $allowedTypes)) {
            return back()->with('error', 'Invalid document type.');
        }

        $filePath = $applicant->{$documentType};

        if (!$filePath || !Storage::disk('private')->exists($filePath)) {
            return back()->with('error', 'File not found.');
        }

        return Storage::disk('private')->download($filePath);
    }

    public function previewStudentDocument(Applicant $applicant, $documentType)
    {
        $allowedTypes = ['photo_2x2', 'psa_birth_cert'];

        if (!in_array($documentType, $allowedTypes)) {
            abort(400, 'Invalid document type.');
        }

        $filePath = $applicant->{$documentType};

        if (!$filePath || !Storage::disk('private')->exists($filePath)) {
            abort(404, 'File not found.');
        }

        $mimeType = Storage::disk('private')->mimeType($filePath);

        return response(Storage::disk('private')->get($filePath), 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
        ]);
    }
}
