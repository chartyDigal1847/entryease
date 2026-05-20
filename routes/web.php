<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EntryEaseController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RegistrarController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\EventBusController;
use App\Http\Controllers\FederatedSearchController;

// ── Federated search — portal aggregator calls with Bearer ENTRYEASE_SEARCH_TOKEN (see DEORIS .env)
Route::get('/api/search', FederatedSearchController::class)
    ->middleware('portal.search')
    ->name('api.search');

// ── Root route — serves SSO shell
Route::get('/', [EntryEaseController::class, 'index'])->name('home');

// ── Logout route — handles session cleanup and CSRF token regeneration
Route::post('/logout', [EntryEaseController::class, 'logout'])->name('logout');
Route::get('/logout', [EntryEaseController::class, 'logout'])->name('logout.get');

// ── SSO redirect (POST) — production path
//    Legacy Blade handoff after module:ready. Remove when dashboards boot
//    directly from window.PORTAL_USER.
Route::post('/sso/redirect', [EntryEaseController::class, 'ssoRedirect'])->name('sso.redirect');

// ── SSO redirect (GET) — dev/standalone path only (?dev=1&role=...)
//    Only reached when module-bridge fires in top-level/standalone mode.
//    Role is still clamped server-side; never trusted blindly.
Route::get('/sso/redirect', [EntryEaseController::class, 'ssoRedirect'])->name('sso.redirect.dev');

// ── Admin routes
Route::prefix('admin')->name('admin.')->middleware('entryease.role:admin')->group(function () {
    Route::get('/dashboard',                                    [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/applications',                                 [AdminController::class, 'applications'])->name('applications');
    Route::put('/applications/{applicant}',                     [AdminController::class, 'updateStatus'])->name('applications.update');
    Route::get('/registrars',                                   [AdminController::class, 'registrars'])->name('registrars');
    Route::get('/registrars/create',                            [AdminController::class, 'createRegistrar'])->name('registrars.create');
    Route::post('/registrars',                                  [AdminController::class, 'storeRegistrar'])->name('registrars.store');
    Route::get('/registrars/{user}/edit',                       [AdminController::class, 'editRegistrar'])->name('registrars.edit');
    Route::put('/registrars/{user}',                            [AdminController::class, 'updateRegistrar'])->name('registrars.update');
    Route::delete('/registrars/{user}',                         [AdminController::class, 'deleteRegistrar'])->name('registrars.delete');
    Route::get('/applicants/{applicant}/documents',                 [AdminController::class, 'viewStudentDocuments'])->name('student.documents');
    Route::get('/applicants/{applicant}/documents/{documentType}/download', [AdminController::class, 'downloadStudentDocument'])->name('student.document.download');
    Route::get('/applicants/{applicant}/documents/{documentType}/preview',  [AdminController::class, 'previewStudentDocument'])->name('student.document.preview');
    Route::get('/settings', [AdminController::class, 'dashboard'])->name('settings');
    Route::get('/help',     [AdminController::class, 'dashboard'])->name('help');
});

// ── Admin API routes (used by admin.js)
Route::prefix('api/admin')->name('api.admin.')->middleware('entryease.role:admin')->group(function () {
    Route::get('/applications',             [AdminController::class, 'getApplicants'])->name('applications');
    Route::get('/applications/{applicant}', [AdminController::class, 'getApplicant'])->name('applications.show');
    Route::put('/applications/{applicant}', [AdminController::class, 'updateStatus'])->name('applications.update');
});

// ── Registrar (Admission Officer) routes
Route::prefix('registrar')->name('registrar.')->middleware('entryease.role:registrar,hr,admission_officer')->group(function () {
    Route::get('/dashboard',                    [RegistrarController::class, 'dashboard'])->name('dashboard');
    Route::get('/applications',                 [RegistrarController::class, 'applications'])->name('applications');
    Route::get('/applications/{applicant}',     [RegistrarController::class, 'viewApplication'])->name('application.view');
    Route::put('/applications/{applicant}',     [RegistrarController::class, 'updateStatus'])->name('application.update');
    Route::get('/applicants/{applicant}/documents', [RegistrarController::class, 'viewStudentDocuments'])->name('student.documents');
    Route::get('/applicants/{applicant}/documents/{documentType}/download', [RegistrarController::class, 'downloadStudentDocument'])->name('student.document.download');
    Route::get('/applicants/{applicant}/documents/{documentType}/preview',  [RegistrarController::class, 'previewStudentDocument'])->name('student.document.preview');
});

// ── Exam routes (Admission Officer manages; Admin can view schedules only)
Route::prefix('exam')->name('exam.')->middleware('entryease.role:registrar,hr,admission_officer,admin')->group(function () {
    // Schedules — list (admin + officer), manage (officer only)
    Route::get('/schedules',                                        [ExamController::class, 'schedules'])->name('schedules');
    Route::get('/schedules/create',                                 [ExamController::class, 'createSchedule'])->name('schedules.create');
    Route::post('/schedules',                                       [ExamController::class, 'storeSchedule'])->name('schedules.store');
    Route::get('/schedules/{schedule}/edit',                        [ExamController::class, 'editSchedule'])->name('schedules.edit');
    Route::put('/schedules/{schedule}',                             [ExamController::class, 'updateSchedule'])->name('schedules.update');
    Route::delete('/schedules/{schedule}',                          [ExamController::class, 'deleteSchedule'])->name('schedules.delete');
    Route::get('/schedules/{schedule}/questions',                   [ExamController::class, 'questions'])->name('schedules.questions');
    Route::post('/schedules/{schedule}/questions',                  [ExamController::class, 'storeQuestion'])->name('schedules.questions.store');
    Route::delete('/schedules/{schedule}/questions/{question}',      [ExamController::class, 'deleteQuestion'])->name('schedules.questions.delete');

    // Applicant assignment
    Route::get('/schedules/{schedule}/applicants',                  [ExamController::class, 'scheduleApplicants'])->name('schedules.applicants');
    Route::post('/schedules/{schedule}/applicants',                 [ExamController::class, 'assignApplicant'])->name('schedules.applicants.assign');
    Route::patch('/schedules/{schedule}/applicants/{applicant}/seating', [ExamController::class, 'updateSeating'])->name('schedules.applicants.seating');
    Route::delete('/schedules/{schedule}/applicants/{applicant}',   [ExamController::class, 'unassignApplicant'])->name('schedules.applicants.unassign');

    // Score recording + analytics
    Route::post('/schedules/{schedule}/applicants/{applicant}/score', [ExamController::class, 'recordScore'])->name('schedules.score');
    Route::get('/schedules/{schedule}/analytics',                     [ExamController::class, 'scoreAnalytics'])->name('schedules.analytics');
});

// ── Student routes
Route::prefix('student')->name('student.')->middleware('entryease.role:student')->group(function () {
    Route::get('/dashboard',                 [StudentController::class, 'dashboard'])->name('dashboard');
    Route::get('/apply',                     [StudentController::class, 'apply'])->name('apply');
    Route::post('/apply',                    [StudentController::class, 'storeApplication'])->name('apply.store');
    Route::get('/applications',              [StudentController::class, 'applications'])->name('applications');
    Route::get('/documents/{type}/download', [StudentController::class, 'downloadDocument'])->name('documents.download');
    Route::get('/exam-schedule',             [StudentController::class, 'examSchedule'])->name('exam.schedule');
    Route::get('/take-exam',                 [StudentController::class, 'takeExam'])->name('exam.take');
    Route::post('/take-exam',                [StudentController::class, 'submitExam'])->name('exam.submit');
    Route::get('/exam/{applicant}/permit',   [StudentController::class, 'downloadPermit'])->name('exam.permit.download');
    Route::get('/results',                   [StudentController::class, 'results'])->name('results');
});

// ── Student API routes (kept for JS compatibility — edit/delete now return 403)
Route::prefix('api/student')->name('api.student.')->middleware('entryease.role:student')->group(function () {
    Route::get('/applications',                [StudentController::class, 'getApplications'])->name('applications');
    Route::post('/apply',                      [StudentController::class, 'storeApplication'])->name('apply');
    Route::put('/applications/{applicant}',    [StudentController::class, 'updateApplication']);
    Route::delete('/applications/{applicant}', [StudentController::class, 'deleteApplication']);
});

// ── DEORIS Event Bus (HTTP fallback for cross-module events)
Route::prefix('entryease/api/events')->group(function () {
    Route::post('/inbound', [EventBusController::class, 'inbound'])->name('deoris.events.inbound');
    Route::get('/recent', [EventBusController::class, 'recent'])
        ->middleware('entryease.role:registrar,hr,admission_officer,admin')
        ->name('deoris.events.recent');
});

// ── EntryEase module API (legacy/portal API endpoints)
Route::prefix('entryease/api')->middleware('entryease.role:registrar,hr,admission_officer,admin')->group(function () {
    Route::get('/bootstrap',                    [EntryEaseController::class, 'bootstrap']);
    Route::get('/applicants',                   [EntryEaseController::class, 'listApplicants']);
    Route::post('/applicants',                  [EntryEaseController::class, 'storeApplicant']);
    Route::get('/applicants/{applicant}',       [EntryEaseController::class, 'getApplicant']);
    Route::put('/applicants/{applicant}',       [EntryEaseController::class, 'updateApplicant']);
    Route::delete('/applicants/{applicant}',    [EntryEaseController::class, 'deleteApplicant']);
    Route::get('/activity-log',                 [EntryEaseController::class, 'getActivityLog']);
});

// (SSO routes defined above — no duplicates)
