<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamScore;
use App\Services\Admission\AdmissionEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Student portal controller.
 *
 * Identity comes from SSO session (set by EntryEaseController::ssoRedirect).
 * Users are managed in DEORIS only - no local user records.
 */
class StudentController extends Controller
{
    /**
     * Get DEORIS user ID from SSO session.
     *
     * SSO sets session(['user' => ['id', 'name', 'email', 'role']]).
     * Returns the DEORIS user ID directly.
     */
    private function getDeorisUserId(Request $request): ?int
    {
        $sessionUser = $request->session()->get('user');
        return $sessionUser['id'] ?? null;
    }

    /**
     * Get user data from SSO session.
     */
    private function getUserData(Request $request): array
    {
        return $request->session()->get('user', []);
    }

    /**
     * Show student dashboard
     */
    public function dashboard(Request $request)
    {
        $deorisUserId = $this->getDeorisUserId($request);
        $userData = $this->getUserData($request);
        
        $applications = Applicant::where('deoris_user_id', $deorisUserId)
            ->with(['examSchedule.activeQuestions', 'examScore', 'latestExamAttempt'])
            ->latest()
            ->get();

        return view('admission.student.dashboard', [
            'student' => (object) $userData,
            'applications' => $applications
        ]);
    }

    /**
     * Show apply form — redirect away if already applied.
     */
    public function apply(Request $request)
    {
        $deorisUserId = $this->getDeorisUserId($request);
        $userData = $this->getUserData($request);

        // One application per student — lock them out if already submitted
        $existing = Applicant::where('deoris_user_id', $deorisUserId)->first();
        if ($existing) {
            return redirect()->route('student.applications')
                ->with('info', 'You have already submitted an application. Only one application is allowed per account.');
        }

        return view('admission.student.apply', [
            'student' => (object) $userData
        ]);
    }

    /**
     * Show applications list
     */
    public function applications(Request $request)
    {
        $deorisUserId = $this->getDeorisUserId($request);
        $userData = $this->getUserData($request);
        
        $applications = Applicant::where('deoris_user_id', $deorisUserId)
            ->with(['examSchedule.activeQuestions', 'examScore', 'latestExamAttempt'])
            ->latest()
            ->get();

        return view('admission.student.applications', [
            'student' => (object) $userData,
            'applications' => $applications
        ]);
    }

    /**
     * Store new application — collects profile info + documents in one step.
     * Submitted as a standard multipart form (not JSON) so files can be uploaded.
     * One application per student — locked after first submission.
     */
    public function storeApplication(Request $request)
    {
        $deorisUserId = $this->getDeorisUserId($request);

        // Hard lock — one application per student, no exceptions
        if (Applicant::where('deoris_user_id', $deorisUserId)->exists()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already submitted an application.',
                ], 422);
            }
            return redirect()->route('student.applications')
                ->with('info', 'You have already submitted an application.');
        }

        $validated = $request->validate([
            'phone'          => 'required|string|max:20',
            'previous_school'=> 'required|string|max:255',
            'photo_2x2'      => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'psa_birth_cert' => 'required|mimes:pdf,jpeg,png,jpg|max:5120',
        ], [
            'phone.required'           => 'Please enter your contact number.',
            'previous_school.required' => 'Please enter your previous school.',
            'photo_2x2.required'       => 'A 2×2 photo is required.',
            'photo_2x2.image'          => 'The photo must be an image file (JPG or PNG).',
            'photo_2x2.max'            => 'The photo must not exceed 5 MB.',
            'psa_birth_cert.required'  => 'A PSA Birth Certificate is required.',
            'psa_birth_cert.max'       => 'The PSA Birth Certificate must not exceed 5 MB.',
        ]);

        // Store photo_2x2
        $photoFile = $request->file('photo_2x2');
        $photoName = 'photo_2x2_' . $deorisUserId . '_' . time() . '.' . $photoFile->getClientOriginalExtension();
        $photoPath = $photoFile->storeAs('documents/applicants/' . $deorisUserId, $photoName, 'private');

        // Store psa_birth_cert
        $psaFile = $request->file('psa_birth_cert');
        $psaName = 'psa_birth_cert_' . $deorisUserId . '_' . time() . '.' . $psaFile->getClientOriginalExtension();
        $psaPath = $psaFile->storeAs('documents/applicants/' . $deorisUserId, $psaName, 'private');

        // Create the application with documents stored on applicant record
        $applicant = Applicant::create([
            'deoris_user_id'   => $deorisUserId,
            'grade_level'      => 'Grade 7',
            'additional_info'  => json_encode([
                'phone' => $validated['phone'],
                'previous_school' => $validated['previous_school'],
            ]),
            'photo_2x2'        => $photoPath,
            'psa_birth_cert'   => $psaPath,
            'status'           => 'Pending',
            'admission_status' => 'pending',
            'documents_updated_at' => now(),
        ]);

        app(AdmissionEventService::class)->applicationSubmitted($applicant);

        return redirect()->route('student.applications')
            ->with('success', 'Your application has been submitted successfully! We will review it shortly.');
    }

    /**
     * Get student's applications (API) — score data is never returned to students.
     */
    public function getApplications(Request $request)
    {
        $deorisUserId = $this->getDeorisUserId($request);
        $applications = Applicant::where('deoris_user_id', $deorisUserId)
            ->get(['id', 'deoris_user_id', 'grade_level', 'status', 'admission_status',
                   'exam_schedule_id', 'exam_seat_number', 'exam_room',
                   'photo_2x2', 'psa_birth_cert', 'created_at', 'updated_at']);
        return response()->json($applications);
    }

    /**
     * Update student's own application — LOCKED after submission.
     */
    public function updateApplication(Request $request, Applicant $applicant)
    {
        return response()->json([
            'success' => false,
            'message' => 'Applications cannot be edited after submission.',
        ], 403);
    }

    /**
     * Delete student's own application — LOCKED after submission.
     */
    public function deleteApplication(Request $request, Applicant $applicant)
    {
        return response()->json([
            'success' => false,
            'message' => 'Applications cannot be deleted after submission.',
        ], 403);
    }

    /**
     * Download student's own document (photo_2x2 or psa_birth_cert).
     * Files are stored on the private disk — never web-accessible directly.
     */
    public function downloadDocument(Request $request, string $document)
    {
        $deorisUserId = $this->getDeorisUserId($request);

        $allowed = ['photo_2x2', 'psa_birth_cert'];
        if (!in_array($document, $allowed, true)) {
            abort(404, 'Document not found.');
        }

        // Get the applicant record to find the document path
        $applicant = Applicant::where('deoris_user_id', $deorisUserId)->first();
        if (!$applicant) {
            abort(404, 'Application not found.');
        }

        $filePath = $applicant->{$document};

        if (!$filePath || !Storage::disk('private')->exists($filePath)) {
            abort(404, 'File not found on server.');
        }

        return Storage::disk('private')->download(
            $filePath,
            basename($filePath)
        );
    }

    /**
     * Show the student's assigned exam schedule.
     */
    public function examSchedule(Request $request)
    {
        $deorisUserId = $this->getDeorisUserId($request);
        $userData = $this->getUserData($request);
        
        $application = Applicant::where('deoris_user_id', $deorisUserId)
            ->with(['examSchedule.activeQuestions', 'examScore', 'latestExamAttempt'])
            ->latest()
            ->first();

        return view('admission.student.exam-schedule', [
            'student' => (object) $userData,
            'application' => $application
        ]);
    }

    public function takeExam(Request $request)
    {
        $deorisUserId = $this->getDeorisUserId($request);
        $userData = $this->getUserData($request);
        
        $application = Applicant::where('deoris_user_id', $deorisUserId)
            ->with(['examSchedule.activeQuestions', 'examScore', 'latestExamAttempt'])
            ->latest()
            ->first();

        // No application at all
        if (!$application) {
            return redirect()->route('student.apply')
                ->with('error', 'You need to submit a Grade 7 application before you can take the exam.');
        }

        // No exam schedule assigned yet
        if (!$application->examSchedule) {
            return redirect()->route('student.exam.schedule')
                ->with('info', 'Your exam schedule has not been assigned yet. Please check back later.');
        }

        // Already submitted
        if ($application->examScore || optional($application->latestExamAttempt)->status === 'submitted') {
            return redirect()->route('student.results')
                ->with('info', 'You have already submitted your exam. View your results below.');
        }

        // Schedule cancelled
        if ($application->examSchedule->status === 'cancelled') {
            return redirect()->route('student.exam.schedule')
                ->with('error', 'This exam schedule has been cancelled. Please contact the Admission Office.');
        }

        $student = (object) $userData;
        $schedule = $application->examSchedule;

        // Debug logging
        Log::info('takeExam called', [
            'applicant_id' => $application->id,
            'exam_type' => $schedule->exam_type,
            'exam_type_is_null' => is_null($schedule->exam_type),
            'exam_type_value' => var_export($schedule->exam_type, true),
        ]);

        // ONLINE EXAM: Load questions and show exam interface
        if ($schedule->exam_type === 'online') {
            // No questions added yet
            $questions = $schedule->activeQuestions;
            if ($questions->isEmpty()) {
                return redirect()->route('student.exam.schedule')
                    ->with('info', 'The exam questions are not ready yet. The Admission Officer will add them soon. Please check back later.');
            }

            // Create or resume attempt
            $attempt = ExamAttempt::firstOrCreate(
                [
                    'applicant_id'     => $application->id,
                    'exam_schedule_id' => $schedule->id,
                ],
                [
                    'status'      => 'in_progress',
                    'started_at'  => now(),
                    'total_items' => $questions->sum('points'),
                ]
            );

            return view('admission.student.take-exam-online', compact(
                'student', 'application', 'questions', 'attempt', 'schedule'
            ));
        }

        // ON-SITE EXAM: Show exam details, seat, and permit
        $permit = \App\Support\PermitPresenter::make($application, $schedule, $student);

        return view('admission.student.take-exam-onsite', compact(
            'student', 'application', 'schedule', 'permit'
        ));
    }

    public function submitExam(Request $request)
    {
        $deorisUserId = $this->getDeorisUserId($request);
        
        $application = Applicant::where('deoris_user_id', $deorisUserId)
            ->with(['examSchedule.activeQuestions', 'examScore', 'latestExamAttempt'])
            ->latest()
            ->firstOrFail();

        if (!$application->examSchedule) {
            return redirect()->route('student.exam.schedule')->with('error', 'Your exam schedule has not been assigned yet.');
        }

        if ($application->examScore || optional($application->latestExamAttempt)->status === 'submitted') {
            return redirect()->route('student.results')->with('error', 'Your exam has already been submitted.');
        }

        $questions = $application->examSchedule->activeQuestions;
        if ($questions->isEmpty()) {
            return redirect()->route('student.exam.schedule')->with('error', 'The exam is not available yet.');
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'nullable|in:A,B,C,D',
        ]);

        DB::transaction(function () use ($application, $questions, $validated) {
            $attempt = ExamAttempt::updateOrCreate(
                [
                    'applicant_id' => $application->id,
                    'exam_schedule_id' => $application->examSchedule->id,
                ],
                [
                    'status' => 'in_progress',
                    'started_at' => optional($application->latestExamAttempt)->started_at ?? now(),
                ]
            );

            $score = 0;
            $totalItems = $questions->sum('points');

            foreach ($questions as $question) {
                $answer = $validated['answers'][$question->id] ?? null;
                $isCorrect = $answer !== null && $answer === $question->correct_answer;
                $points = $isCorrect ? $question->points : 0;
                $score += $points;

                ExamAttemptAnswer::updateOrCreate(
                    [
                        'exam_attempt_id' => $attempt->id,
                        'exam_question_id' => $question->id,
                    ],
                    [
                        'answer' => $answer,
                        'is_correct' => $isCorrect,
                        'points_awarded' => $points,
                    ]
                );
            }

            $attempt->update([
                'status' => 'submitted',
                'score' => $score,
                'total_items' => $totalItems,
                'submitted_at' => now(),
            ]);

            $examScore = ExamScore::updateOrCreate(
                [
                    'applicant_id' => $application->id,
                    'exam_schedule_id' => $application->examSchedule->id,
                ],
                [
                    'score' => $score,
                    'total_items' => $totalItems,
                    'remarks' => 'Auto-scored from student exam submission.',
                    'recorded_by' => 'EntryEase Exam',
                    'recorded_at' => now(),
                ]
            );

            app(AdmissionEventService::class)->examCompleted($application->fresh(), $examScore);
        });

        return redirect()->route('student.results')->with('success', 'Your exam has been submitted and scored.');
    }

    /**
     * Show the student's exam results.
     */
    public function results(Request $request)
    {
        $deorisUserId = $this->getDeorisUserId($request);
        $userData = $this->getUserData($request);
        
        $application = Applicant::where('deoris_user_id', $deorisUserId)
            ->with(['examSchedule', 'examScore'])
            ->latest()
            ->first();

        return view('admission.student.results', [
            'student' => (object) $userData,
            'application' => $application
        ]);
    }

    /**
     * Download exam permit as PDF (printable HTML).
     * Only available for onsite exams.
     */
    public function downloadPermit(Request $request, Applicant $applicant)
    {
        $deorisUserId = $this->getDeorisUserId($request);

        if ($applicant->deoris_user_id !== $deorisUserId) {
            abort(403);
        }

        $applicant->load('examSchedule');

        if (!$applicant->examSchedule || $applicant->examSchedule->exam_type !== 'onsite') {
            abort(403, 'Permit download is only available for on-site exams.');
        }

        $userData = $this->getUserData($request);
        $student = (object) $userData;
        $schedule = $applicant->examSchedule;
        $permit = \App\Support\PermitPresenter::make($applicant, $schedule, $student);

        return view('admission.student.permit-download', [
            'student' => $student,
            'application' => $applicant,
            'schedule' => $schedule,
            'permit' => $permit,
        ]);
    }
}
