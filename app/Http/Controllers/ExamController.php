<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\ExamSchedule;
use App\Models\ExamScore;
use App\Services\Admission\AdmissionEventService;
use App\Models\ExamQuestion;
use Illuminate\Http\Request;

/**
 * ExamController — Admission Officer only.
 *
 * Manages exam schedules and records exam scores.
 * Admin role can VIEW schedules (monitoring) but cannot create/edit/score.
 */
class ExamController extends Controller
{
    private function userContext(): array
    {
        return [
            'id'    => session('sso_id'),
            'name'  => session('sso_name', 'Officer'),
            'email' => session('sso_email', ''),
            'role'  => session('sso_role', 'registrar'),
        ];
    }

    private function isAdmissionOfficer(): bool
    {
        $role = session('sso_role', 'student');
        return in_array($role, ['registrar', 'hr', 'admission_officer']);
    }

    // ── Exam Schedules ────────────────────────────────────────────────────────

    /**
     * List all exam schedules (Admission Officer + Admin can view).
     */
    public function schedules()
    {
        $schedules   = ExamSchedule::withCount(['applicants', 'questions'])
            ->withCount(['questions as active_questions_count' => fn($q) => $q->where('is_active', true)])
            ->orderBy('exam_date')
            ->get();
        $userContext = $this->userContext();

        return view('admission.exam.schedules', compact('schedules', 'userContext'));
    }

    /**
     * Show create schedule form (Admission Officer only).
     */
    public function createSchedule()
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403, 'Only Admission Officers can manage exam schedules.');
        }

        $userContext = $this->userContext();
        return view('admission.exam.create-schedule', compact('userContext'));
    }

    /**
     * Store a new exam schedule.
     */
    public function storeSchedule(Request $request)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403, 'Only Admission Officers can manage exam schedules.');
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'exam_date'    => 'required|date|after_or_equal:today',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i|after:start_time',
            'venue'        => 'nullable|string|max:255',
            'batch'        => 'nullable|string|max:100',
            'slots'        => 'required|integer|min:1|max:500',
            'instructions' => 'nullable|string|max:2000',
            'exam_type'    => 'required|in:online,onsite',
        ]);

        $schedule = ExamSchedule::create($validated);

        ActivityLog::record(
            "Exam schedule created: \"{$schedule->title}\" on " . $schedule->exam_date->format('M d, Y'),
            'blue'
        );

        return redirect()->route('exam.schedules')
            ->with('success', "Exam schedule \"{$schedule->title}\" created successfully.");
    }

    /**
     * Show edit schedule form.
     */
    public function editSchedule(ExamSchedule $schedule)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403, 'Only Admission Officers can manage exam schedules.');
        }

        $userContext = $this->userContext();
        return view('admission.exam.edit-schedule', compact('schedule', 'userContext'));
    }

    /**
     * Update an exam schedule.
     */
    public function updateSchedule(Request $request, ExamSchedule $schedule)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403, 'Only Admission Officers can manage exam schedules.');
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'exam_date'    => 'required|date',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i|after:start_time',
            'venue'        => 'nullable|string|max:255',
            'batch'        => 'nullable|string|max:100',
            'slots'        => 'required|integer|min:1|max:500',
            'instructions' => 'nullable|string|max:2000',
            'status'       => 'required|in:upcoming,ongoing,completed,cancelled',
            'exam_type'    => 'required|in:online,onsite',
        ]);

        $schedule->update($validated);

        ActivityLog::record("Exam schedule updated: \"{$schedule->title}\"", 'amber');

        return redirect()->route('exam.schedules')
            ->with('success', "Exam schedule updated successfully.");
    }

    /**
     * Delete an exam schedule (only if no scores recorded).
     */
    public function deleteSchedule(ExamSchedule $schedule)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403, 'Only Admission Officers can manage exam schedules.');
        }

        if ($schedule->scores()->count() > 0) {
            return back()->with('error', 'Cannot delete a schedule that already has scores recorded.');
        }

        $title = $schedule->title;
        $schedule->delete();

        ActivityLog::record("Exam schedule deleted: \"{$title}\"", 'red');

        return redirect()->route('exam.schedules')
            ->with('success', "Exam schedule \"{$title}\" deleted.");
    }

    // ── Exam Questions ───────────────────────────────────────────────────────

    public function questions(ExamSchedule $schedule)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403, 'Only Admission Officers can manage exam questions.');
        }

        $questions = $schedule->questions()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $userContext = $this->userContext();

        return view('admission.exam.questions', compact('schedule', 'questions', 'userContext'));
    }

    public function storeQuestion(Request $request, ExamSchedule $schedule)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403, 'Only Admission Officers can manage exam questions.');
        }

        $validated = $request->validate([
            'question_text' => 'required|string|max:2000',
            'choice_a' => 'required|string|max:1000',
            'choice_b' => 'required|string|max:1000',
            'choice_c' => 'required|string|max:1000',
            'choice_d' => 'required|string|max:1000',
            'correct_answer' => 'required|in:A,B,C,D',
            'points' => 'required|integer|min:1|max:100',
            'sort_order' => 'nullable|integer|min:0|max:10000',
        ]);

        $question = $schedule->questions()->create([
            'question_text' => $validated['question_text'],
            'choices' => [
                'A' => $validated['choice_a'],
                'B' => $validated['choice_b'],
                'C' => $validated['choice_c'],
                'D' => $validated['choice_d'],
            ],
            'correct_answer' => $validated['correct_answer'],
            'points' => $validated['points'],
            'sort_order' => $validated['sort_order'] ?? ($schedule->questions()->max('sort_order') + 1),
            'is_active' => true,
        ]);

        ActivityLog::record("Exam question added to \"{$schedule->title}\" (#{$question->id})", 'blue');

        return back()->with('success', 'Question added successfully.');
    }

    public function deleteQuestion(ExamSchedule $schedule, ExamQuestion $question)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403, 'Only Admission Officers can manage exam questions.');
        }

        if ($question->exam_schedule_id !== $schedule->id) {
            abort(404);
        }

        if ($question->attemptAnswers()->exists()) {
            $question->update(['is_active' => false]);
            return back()->with('success', 'Question has existing answers, so it was deactivated.');
        }

        $question->delete();

        return back()->with('success', 'Question deleted.');
    }

    // ── Assign Applicants to Schedule ─────────────────────────────────────────

    /**
     * Show applicants assigned to a schedule + score entry form.
     */
    public function scheduleApplicants(ExamSchedule $schedule)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403, 'Only Admission Officers can manage exam schedules.');
        }

        $applicants  = $schedule->applicants()->with('examScore')->get();
        $unassigned  = Applicant::whereNull('exam_schedule_id')
                                ->where('status', '!=', 'Rejected')
                                ->get();
        $userContext = $this->userContext();

        return view('admission.exam.schedule-applicants', compact(
            'schedule', 'applicants', 'unassigned', 'userContext'
        ));
    }

    /**
     * Assign an applicant to a schedule.
     */
    public function assignApplicant(Request $request, ExamSchedule $schedule)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403);
        }

        $validated = $request->validate([
            'applicant_id' => 'required|exists:applicants,id',
        ]);

        $applicant = Applicant::findOrFail($validated['applicant_id']);

        if ($schedule->availableSlots() <= 0) {
            return back()->with('error', 'No available slots in this schedule.');
        }

        if ($schedule->exam_type === 'onsite') {
            $request->validate([
                'exam_room' => 'nullable|string|max:120',
                'exam_seat_number' => 'nullable|string|max:40',
            ]);

            \App\Support\OnsiteSeating::assign(
                $applicant,
                $schedule,
                $request->input('exam_room'),
                $request->input('exam_seat_number'),
            );
        } else {
            $applicant->update(['exam_schedule_id' => $schedule->id]);
        }

        ActivityLog::record(
            "Applicant #{$applicant->id} ({$applicant->student?->name}) assigned to \"{$schedule->title}\"",
            'blue'
        );

        app(AdmissionEventService::class)->examAssigned($applicant->fresh(), $schedule);

        return back()->with('success', 'Applicant assigned to schedule.');
    }

    /**
     * Remove an applicant from a schedule.
     */
    public function unassignApplicant(Request $request, ExamSchedule $schedule, Applicant $applicant)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403);
        }

        \App\Support\OnsiteSeating::clear($applicant);

        ActivityLog::record(
            "Applicant #{$applicant->id} removed from \"{$schedule->title}\"",
            'amber'
        );

        return back()->with('success', 'Applicant removed from schedule.');
    }

    /**
     * Update on-site exam room and seat for an assigned applicant.
     */
    public function updateSeating(Request $request, ExamSchedule $schedule, Applicant $applicant)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403);
        }

        if ($schedule->exam_type !== 'onsite') {
            return back()->with('error', 'Seating is only used for on-site exams.');
        }

        if ($applicant->exam_schedule_id !== $schedule->id) {
            abort(404);
        }

        $validated = $request->validate([
            'exam_room' => 'required|string|max:120',
            'exam_seat_number' => 'required|string|max:40',
        ]);

        \App\Support\OnsiteSeating::update(
            $applicant,
            $validated['exam_room'],
            $validated['exam_seat_number'],
        );

        ActivityLog::record(
            "Seating updated for applicant #{$applicant->id}: room {$validated['exam_room']}, seat {$validated['exam_seat_number']}",
            'blue'
        );

        return back()->with('success', 'Room and seat updated.');
    }

    // ── Exam Scores ───────────────────────────────────────────────────────────

    /**
     * Record or update a score for an applicant.
     */
    public function recordScore(Request $request, ExamSchedule $schedule, Applicant $applicant)
    {
        if (!$this->isAdmissionOfficer()) {
            abort(403, 'Only Admission Officers can record exam scores.');
        }
        // Lock total_items to the number of active questions for this schedule
        $scheduleTotal = $schedule->activeQuestions()->where('is_active', true)->count() ?: 1;

        $validated = $request->validate([
            'score'   => 'required|numeric|min:0|max:' . $scheduleTotal,
            'remarks' => 'nullable|string|max:500',
        ]);

        $score = ExamScore::updateOrCreate(
            [
                'applicant_id'     => $applicant->id,
                'exam_schedule_id' => $schedule->id,
            ],
            [
                'score'       => $validated['score'],
                'total_items' => $scheduleTotal,
                'remarks'     => $validated['remarks'] ?? null,
                'recorded_by' => session('sso_name', 'Officer'),
                'recorded_at' => now(),
            ]
        );

        $percentage = round(($score->score / $score->total_items) * 100, 1);

        ActivityLog::record(
            "Score recorded for {$applicant->student?->name}: {$score->score}/{$score->total_items} ({$percentage}%)",
            $percentage >= 75 ? 'green' : 'red'
        );

        app(AdmissionEventService::class)->examCompleted($applicant->fresh(), $score);

        return back()->with('success', 'Score recorded successfully.');
    }

    /**
     * Score analytics — all applicants with scores for a schedule.
     */
    public function scoreAnalytics(ExamSchedule $schedule)
    {
        $scores = ExamScore::where('exam_schedule_id', $schedule->id)
            ->with('applicant')
            ->get();

        $stats = [
            'total'   => $scores->count(),
            'passed'  => $scores->filter(fn($s) => $s->passed)->count(),
            'failed'  => $scores->filter(fn($s) => $s->passed === false)->count(),
            'average' => $scores->count() ? round($scores->avg('score'), 2) : null,
            'highest' => $scores->count() ? $scores->max('score') : null,
            'lowest'  => $scores->count() ? $scores->min('score') : null,
        ];

        $userContext = $this->userContext();

        return view('admission.exam.score-analytics', compact('schedule', 'scores', 'stats', 'userContext'));
    }
}
