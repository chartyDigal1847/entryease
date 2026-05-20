<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ExamScore;
use App\Models\ExamSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    /**
     * Get SSO user context from session.
     * Used by all views as $userContext.
     */
    private function userContext(): array
    {
        return [
            'id'    => session('sso_id'),
            'name'  => session('sso_name', 'Admin'),
            'email' => session('sso_email', ''),
            'role'  => session('sso_role', 'admin'),
        ];
    }

    private function userEmail(Request $request): ?string
    {
        $email = session('sso_email') ?? $request->header('X-User-Email');
        return is_string($email) && $email !== '' ? $email : null;
    }

    private function userId(Request $request)
    {
        return session('sso_id') ?? $request->header('X-User-ID');
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $stats = [
            'total'          => Applicant::count(),
            'pending'        => Applicant::pending()->count(),
            'under_review'   => Applicant::underReview()->count(),
            'approved'       => Applicant::approved()->count(),
            'rejected'       => Applicant::rejected()->count(),
            'total_students' => Applicant::distinct('deoris_user_id')->count(),
            'exam_schedules' => ExamSchedule::count(),
            'upcoming_exams' => ExamSchedule::upcoming()->count(),
        ];

        $recentActivity = Applicant::with(['student', 'examSchedule', 'examScore'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $approvalRatio = [
            'approved'     => $stats['approved'],
            'rejected'     => $stats['rejected'],
            'under_review' => $stats['under_review'],
            'pending'      => $stats['pending'],
        ];

        $scoreStatsRaw = (object) DB::selectOne('
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN score / total_items >= 0.75 THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN score / total_items < 0.75 THEN 1 ELSE 0 END) as failed,
                AVG(score / total_items * 100) as average,
                MAX(score / total_items * 100) as highest,
                MIN(score / total_items * 100) as lowest
            FROM exam_scores
            WHERE score IS NOT NULL AND total_items IS NOT NULL AND total_items > 0
        ');

        $scoreStats = [
            'total'   => (int) ($scoreStatsRaw->total ?? 0),
            'passed'  => (int) ($scoreStatsRaw->passed ?? 0),
            'failed'  => (int) ($scoreStatsRaw->failed ?? 0),
            'average' => $scoreStatsRaw->average !== null ? round($scoreStatsRaw->average, 1) : 0,
            'highest' => $scoreStatsRaw->highest !== null ? round($scoreStatsRaw->highest, 1) : 0,
            'lowest'  => $scoreStatsRaw->lowest !== null ? round($scoreStatsRaw->lowest, 1) : 0,
        ];

        $scoreDistribution = array_fill(0, 10, 0);
        $examScores = DB::select('
            SELECT score / total_items * 100 as percentage
            FROM exam_scores
            WHERE score IS NOT NULL AND total_items IS NOT NULL AND total_items > 0
        ');

        foreach ($examScores as $row) {
            $percentage = $row->percentage ?? null;
            if ($percentage === null) continue;
            $bucket = min(9, max(0, (int) floor((float) $percentage / 10)));
            $scoreDistribution[$bucket]++;
        }

        $userContext = $this->userContext();

        return view('admission.admin.dashboard', compact(
            'stats', 'recentActivity', 'approvalRatio',
            'scoreStats', 'scoreDistribution', 'userContext'
        ));
    }

    // ── Applications ──────────────────────────────────────────────────────────

    public function applications(Request $request)
    {
        $query = Applicant::with(['student', 'examSchedule', 'examScore'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $status = $request->status;
            $statusMap = [
                'Pending'      => 'pending',
                'Under Review' => 'under_review',
                'Approved'     => 'approved',
                'Rejected'     => 'rejected',
            ];
            $admissionStatus = $statusMap[$status] ?? null;
            $query->where(function ($q) use ($status, $admissionStatus) {
                $q->where('status', $status);
                if ($admissionStatus) {
                    $q->orWhere('admission_status', $admissionStatus);
                }
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            // Search by phone/school in additional_info OR by student name/email via DEORIS
            $matchingUserIds = DB::connection('deoris')
                ->table('users')
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->pluck('id');

            $query->where(function ($q) use ($search, $matchingUserIds) {
                $q->where('additional_info', 'like', "%{$search}%")
                  ->orWhereIn('deoris_user_id', $matchingUserIds);
            });
        }

        $applicants  = $query->paginate(15)->withQueryString();
        $userContext = $this->userContext();

        return view('admission.admin.applications', compact('applicants', 'userContext'));
    }

    public function updateStatus(Request $request, Applicant $applicant)
    {
        // Admin is monitoring only — only Admission Officers can change status.
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Admins have monitoring access only. Only Admission Officers can update application status.',
            ], 403);
        }

        return back()->with('error', 'Admins have monitoring access only. Only Admission Officers can update application status.');
    }

    // ── Registrar (Admission Officer) management ──────────────────────────────
    // Users live in DEORIS. These methods proxy to the `deoris` DB connection
    // so the admin can manage officer accounts without leaving EntryEase.

    public function registrars()
    {
        $registrars = DB::connection('deoris')
            ->table('users')
            ->whereIn('role', ['admission_officer', 'registrar', 'hr'])
            ->orderBy('name')
            ->get();

        $userContext = $this->userContext();

        return view('admission.admin.registrars', compact('registrars', 'userContext'));
    }

    public function createRegistrar()
    {
        $userContext = $this->userContext();
        return view('admission.admin.create-registrar', compact('userContext'));
    }

    public function storeRegistrar(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $exists = DB::connection('deoris')
            ->table('users')
            ->where('email', $validated['email'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['email' => 'This email is already registered in DEORIS.'])->withInput();
        }

        DB::connection('deoris')->table('users')->insert([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => bcrypt($validated['password']),
            'role'       => 'admission_officer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.registrars')
            ->with('success', 'Admission Officer account created successfully.');
    }

    public function editRegistrar(int $user)
    {
        $registrar = DB::connection('deoris')
            ->table('users')
            ->where('id', $user)
            ->whereIn('role', ['admission_officer', 'registrar', 'hr'])
            ->first();

        abort_if(! $registrar, 404, 'Admission Officer not found.');

        $userContext = $this->userContext();

        return view('admission.admin.edit-registrar', [
            'user'        => $registrar,
            'userContext' => $userContext,
        ]);
    }

    public function updateRegistrar(Request $request, int $user)
    {
        $registrar = DB::connection('deoris')
            ->table('users')
            ->where('id', $user)
            ->whereIn('role', ['admission_officer', 'registrar', 'hr'])
            ->first();

        abort_if(! $registrar, 404, 'Admission Officer not found.');

        $rules = [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'string|min:8|confirmed';
        }

        $validated = $request->validate($rules);

        $emailTaken = DB::connection('deoris')
            ->table('users')
            ->where('email', $validated['email'])
            ->where('id', '!=', $user)
            ->exists();

        if ($emailTaken) {
            return back()->withErrors(['email' => 'This email is already in use.'])->withInput();
        }

        $data = [
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'updated_at' => now(),
        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($validated['password']);
        }

        DB::connection('deoris')->table('users')->where('id', $user)->update($data);

        return redirect()->route('admin.registrars')
            ->with('success', 'Admission Officer updated successfully.');
    }

    public function deleteRegistrar(int $user)
    {
        $registrar = DB::connection('deoris')
            ->table('users')
            ->where('id', $user)
            ->whereIn('role', ['admission_officer', 'registrar', 'hr'])
            ->first();

        abort_if(! $registrar, 404, 'Admission Officer not found.');

        DB::connection('deoris')->table('users')->where('id', $user)->delete();

        return redirect()->route('admin.registrars')
            ->with('success', 'Admission Officer account deleted.');
    }

    // ── Document management ───────────────────────────────────────────────────

    public function viewStudentDocuments(Applicant $applicant)
    {
        $applicant->load(['student', 'examSchedule', 'examScore']);
        $userContext = $this->userContext();
        return view('admission.admin.student-documents', compact('applicant', 'userContext'));
    }

    public function downloadStudentDocument(Applicant $applicant, $documentType)
    {
        if (! in_array($documentType, ['photo_2x2', 'psa_birth_cert'])) {
            return back()->with('error', 'Invalid document type.');
        }

        $filePath = $applicant->{$documentType};

        if (! $filePath || ! Storage::disk('private')->exists($filePath)) {
            return back()->with('error', 'File not found.');
        }

        return Storage::disk('private')->download($filePath);
    }

    public function previewStudentDocument(Applicant $applicant, $documentType)
    {
        if (! in_array($documentType, ['photo_2x2', 'psa_birth_cert'])) {
            abort(400, 'Invalid document type.');
        }

        $filePath = $applicant->{$documentType};

        if (! $filePath || ! Storage::disk('private')->exists($filePath)) {
            abort(404, 'File not found on server.');
        }

        $mimeType = Storage::disk('private')->mimeType($filePath);

        return response(Storage::disk('private')->get($filePath), 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
        ]);
    }

    // ── API endpoints used by admin.js ────────────────────────────────────────

    public function getApplicants()
    {
        $applicants = Applicant::with(['examSchedule', 'examScore'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($a) => [
                'id'           => $a->id,
                'deoris_user_id' => $a->deoris_user_id,
                'grade_level'  => $a->grade_level,
                'status'       => $a->status,
                'admin_notes'  => $a->admin_notes,
                'submitted_at' => $a->created_at->format('Y-m-d H:i'),
            ]);

        return response()->json($applicants);
    }

    public function getApplicant(Applicant $applicant)
    {
        return response()->json([
            'id'              => $applicant->id,
            'status'          => $applicant->status,
            'admin_notes'     => $applicant->admin_notes,
            'deoris_user_id'  => $applicant->deoris_user_id,
            'grade_level'     => $applicant->grade_level,
            'additional_info' => json_decode($applicant->additional_info, true),
        ]);
    }
}
