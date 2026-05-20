<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels — EntryEase DEORIS Module
|--------------------------------------------------------------------------
|
| Authorization callbacks for private/presence channels.
| Identity comes from SSO session — no Laravel Auth guard needed.
|
*/

/**
 * Private channel: entryease.admissions
 *
 * Subscribed by admin and admission officer dashboards.
 * Receives real-time events: ApplicationSubmitted, StatusChanged, ExamAssigned, etc.
 */
Broadcast::channel('entryease.admissions', function () {
    $role = session('sso_role');
    return in_array($role, ['admin', 'registrar', 'hr', 'admission_officer'], true);
});

/**
 * Private channel: entryease.student.{studentId}
 *
 * Subscribed by the student dashboard for their own real-time updates.
 * Students can only subscribe to their own channel.
 * Officers/admins can subscribe to any student channel for monitoring.
 */
Broadcast::channel('entryease.student.{studentId}', function ($user = null, string $studentId) {
    $role = session('sso_role', 'student');

    // Officers and admins can monitor any student channel
    if (in_array($role, ['admin', 'registrar', 'hr', 'admission_officer'], true)) {
        return true;
    }

    // Students can only subscribe to their own channel
    if ($role !== 'student') {
        return false;
    }

    $ssoId = session('sso_id');
    return $ssoId !== null && (string) $ssoId === (string) $studentId;
});

/**
 * Private channel: entryease.exam.{scheduleId}
 *
 * Used for real-time exam session updates (score published, schedule changes).
 */
Broadcast::channel('entryease.exam.{scheduleId}', function ($user = null, string $scheduleId) {
    $role = session('sso_role', 'student');

    // Officers and admins always have access
    if (in_array($role, ['admin', 'registrar', 'hr', 'admission_officer'], true)) {
        return true;
    }

    // Students can subscribe if they are assigned to this schedule
    if ($role === 'student') {
        $ssoId = session('sso_id');
        if (! $ssoId) {
            return false;
        }

        return \App\Models\Applicant::query()
            ->where('deoris_user_id', $ssoId)
            ->where('exam_schedule_id', $scheduleId)
            ->exists();
    }

    return false;
});
