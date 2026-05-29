<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsApplicantDocuments;
use App\Models\Applicant;
use App\Models\ExamScore;
use App\Models\ExamSchedule;
use App\Services\Integration\PortalIdentityApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    use StreamsApplicantDocuments;

    public function __construct(
        private readonly PortalIdentityApiClient $portalIdentity,
    ) {}

    private function userContext(): array
    {
        return [
            'id' => session('sso_id'),
            'name' => session('sso_name', 'Admin'),
            'email' => session('sso_email', ''),
            'role' => session('sso_role', 'admin'),
        ];
    }

    private function actingPortalUserId(): string
    {
        $id = session('sso_id');

        if (! $id) {
            abort(403, 'DEORIS portal session required.');
        }

        return (string) $id;
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $stats = [
            'total' => Applicant::count(),
            'pending' => Applicant::pending()->count(),
            'under_review' => Applicant::underReview()->count(),
            'approved' => Applicant::approved()->count(),
            'rejected' => Applicant::rejected()->count(),
            'total_students' => Applicant::distinct('deoris_user_id')->count(),
            'exam_schedules' => ExamSchedule::count(),
            'upcoming_exams' => ExamSchedule::upcoming()->count(),
        ];

        $recentActivity = Applicant::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $approvalRatio = [
            'approved' => $stats['approved'],
            'rejected' => $stats['rejected'],
            'under_review' => $stats['under_review'],
            'pending' => $stats['pending'],
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
            'total' => (int) ($scoreStatsRaw->total ?? 0),
            'passed' => (int) ($scoreStatsRaw->passed ?? 0),
            'failed' => (int) ($scoreStatsRaw->failed ?? 0),
            'average' => $scoreStatsRaw->average !== null ? round($scoreStatsRaw->average, 1) : 0,
            'highest' => $scoreStatsRaw->highest !== null ? round($scoreStatsRaw->highest, 1) : 0,
            'lowest' => $scoreStatsRaw->lowest !== null ? round($scoreStatsRaw->lowest, 1) : 0,
        ];

        $scoreDistribution = array_fill(0, 10, 0);
        $examScores = DB::select('
            SELECT score / total_items * 100 as percentage
            FROM exam_scores
            WHERE score IS NOT NULL AND total_items IS NOT NULL AND total_items > 0
        ');

        foreach ($examScores as $row) {
            $percentage = $row->percentage ?? null;
            if ($percentage === null) {
                continue;
            }
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
        $query = Applicant::with(['examSchedule', 'examScore'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $status = $request->status;
            $statusMap = [
                'Pending' => 'pending',
                'Under Review' => 'under_review',
                'Approved' => 'approved',
                'Rejected' => 'rejected',
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
            $query->where(function ($q) use ($search) {
                $q->where('additional_info', 'like', "%{$search}%")
                    ->orWhere('portal_student_name', 'like', "%{$search}%")
                    ->orWhere('portal_student_email', 'like', "%{$search}%");
            });
        }

        $applicants = $query->paginate(15)->withQueryString();
        $userContext = $this->userContext();

        return view('admission.admin.applications', compact('applicants', 'userContext'));
    }

    public function updateStatus(Request $request, Applicant $applicant)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Admins have monitoring access only. Only Admission Officers can update application status.',
            ], 403);
        }

        return back()->with('error', 'Admins have monitoring access only. Only Admission Officers can update application status.');
    }

    // ── Admission officers (DEORIS portal API) ────────────────────────────────

    public function registrars()
    {
        try {
            $rows = $this->portalIdentity->listAdmissionOfficers($this->actingPortalUserId());
            $registrars = collect($rows)->map(fn (array $row) => (object) $row);
        } catch (\Throwable $e) {
            Log::error('[EntryEase] Failed to load admission officers from DEORIS', ['error' => $e->getMessage()]);
            return back()->with('error', 'Could not load admission officers from DEORIS portal.');
        }

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
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $this->portalIdentity->createAdmissionOfficer($this->actingPortalUserId(), $validated);
        } catch (\Throwable $e) {
            Log::warning('[EntryEase] Create admission officer failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.registrars')
            ->with('success', 'Admission Officer account created in DEORIS.');
    }

    public function editRegistrar(int $user)
    {
        try {
            $rows = $this->portalIdentity->listAdmissionOfficers($this->actingPortalUserId());
            $registrar = collect($rows)->firstWhere('id', $user);
        } catch (\Throwable $e) {
            abort(503, 'Could not load officer from DEORIS.');
        }

        abort_if(! $registrar, 404, 'Admission Officer not found.');

        $userContext = $this->userContext();

        return view('admission.admin.edit-registrar', [
            'user' => (object) $registrar,
            'userContext' => $userContext,
        ]);
    }

    public function updateRegistrar(Request $request, int $user)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'string|min:8|confirmed';
        }

        $validated = $request->validate($rules);

        try {
            $this->portalIdentity->updateAdmissionOfficer($this->actingPortalUserId(), $user, $validated);
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.registrars')
            ->with('success', 'Admission Officer updated in DEORIS.');
    }

    public function deleteRegistrar(int $user)
    {
        try {
            $this->portalIdentity->deleteAdmissionOfficer($this->actingPortalUserId(), $user);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.registrars')
            ->with('success', 'Admission Officer removed from DEORIS.');
    }

    // ── Document management ───────────────────────────────────────────────────

    public function viewStudentDocuments(Applicant $applicant)
    {
        $applicant->load(['examSchedule', 'examScore']);
        $userContext = $this->userContext();

        return view('admission.admin.student-documents', compact('applicant', 'userContext'));
    }

    public function downloadStudentDocument(Applicant $applicant, $documentType)
    {
        return $this->downloadApplicantDocument($applicant, $documentType);
    }

    public function previewStudentDocument(Applicant $applicant, $documentType)
    {
        return $this->previewApplicantDocument($applicant, $documentType);
    }

    // ── API endpoints used by admin.js ────────────────────────────────────────

    public function getApplicants()
    {
        $applicants = Applicant::orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'deoris_user_id' => $a->deoris_user_id,
                'grade_level' => $a->grade_level,
                'status' => $a->status,
                'admin_notes' => $a->admin_notes,
                'submitted_at' => $a->created_at->format('Y-m-d H:i'),
            ]);

        return response()->json($applicants);
    }

    public function getApplicant(Applicant $applicant)
    {
        return response()->json([
            'id' => $applicant->id,
            'status' => $applicant->status,
            'admin_notes' => $applicant->admin_notes,
            'deoris_user_id' => $applicant->deoris_user_id,
            'grade_level' => $applicant->grade_level,
            'additional_info' => json_decode($applicant->additional_info, true),
        ]);
    }
}
