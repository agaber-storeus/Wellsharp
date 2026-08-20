<?php

use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\CourseConfigurationController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\ExamScheduleController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TrainingClassController;
use App\Http\Controllers\Admin\TrainingProviderController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active.user', 'session.version', 'current.role:admin'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('certificates/data', [CertificateController::class, 'data'])->name('certificates.data');
    Route::get('certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/data', [StudentController::class, 'data'])->name('students.data');
    Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('students', [StudentController::class, 'store'])->name('students.store');
    Route::get('users/data', [UserController::class, 'data'])->name('users.data');
    Route::resource('users', UserController::class)->except(['destroy']);
    Route::patch('users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
    Route::patch('users/{user}/archive', [UserController::class, 'archive'])->name('users.archive');
    Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
    Route::patch('users/{user}/role', [UserController::class, 'changeRole'])->name('users.role');
    Route::post('users/{user}/reveal-password', [UserController::class, 'revealPassword'])->name('users.reveal-password');

    Route::get('providers/data', [TrainingProviderController::class, 'data'])->name('providers.data');
    Route::resource('providers', TrainingProviderController::class)->except(['destroy']);
    Route::patch('providers/{provider}/archive', [TrainingProviderController::class, 'archive'])->name('providers.archive');
    Route::patch('providers/{provider}/status', [TrainingProviderController::class, 'updateStatus'])->name('providers.status');

    Route::get('subject-configuration', [CourseConfigurationController::class, 'index'])->name('configuration.index');
    Route::post('subject-configuration/{type}', [CourseConfigurationController::class, 'store'])->name('configuration.store');
    Route::patch('subject-configuration/{type}/reorder', [CourseConfigurationController::class, 'reorder'])->name('configuration.reorder');
    Route::patch('subject-configuration/{type}/{item}', [CourseConfigurationController::class, 'update'])->name('configuration.update');
    Route::patch('subject-configuration/{type}/{item}/toggle', [CourseConfigurationController::class, 'toggle'])->name('configuration.toggle');

    Route::get('classes/data', [TrainingClassController::class, 'data'])->name('classes.data');
    Route::resource('courses', CourseController::class)->except(['destroy']);
    Route::get('subjects', [CourseController::class, 'index'])->name('subjects.index');
    Route::get('subjects/data', [CourseController::class, 'data'])->name('subjects.data');
    Route::patch('courses/{course}/archive', [CourseController::class, 'archive'])->name('courses.archive');
    Route::get('courses/{course}/questions/template', [QuestionController::class, 'template'])->name('courses.questions.template');
    Route::get('courses/{course}/questions/import', [QuestionController::class, 'import'])->name('courses.questions.import');
    Route::post('courses/{course}/questions/import/preview', [QuestionController::class, 'preview'])->name('courses.questions.import.preview');
    Route::post('courses/{course}/questions/import/confirm', [QuestionController::class, 'confirm'])->name('courses.questions.import.confirm');
    Route::get('courses/{course}/questions', [QuestionController::class, 'index'])->name('courses.questions.index');
    Route::get('courses/{course}/questions/data', [QuestionController::class, 'courseData'])->name('courses.questions.data');
    Route::get('courses/{course}/questions/create', [QuestionController::class, 'create'])->name('courses.questions.create');
    Route::post('courses/{course}/questions', [QuestionController::class, 'store'])->name('courses.questions.store');
    Route::get('courses/{course}/questions/{question}/edit', [QuestionController::class, 'edit'])->name('courses.questions.edit');
    Route::put('courses/{course}/questions/{question}', [QuestionController::class, 'update'])->name('courses.questions.update');
    Route::delete('courses/{course}/questions/{question}', [QuestionController::class, 'destroy'])->name('courses.questions.destroy');

    Route::resource('classes', TrainingClassController::class)->except(['destroy'])->parameters(['classes' => 'trainingClass']);
    Route::patch('classes/{trainingClass}/cancel', [TrainingClassController::class, 'cancel'])->name('classes.cancel');
    Route::post('classes/{trainingClass}/enrollments', [TrainingClassController::class, 'enroll'])->name('classes.enrollments.store');
    Route::patch('classes/{trainingClass}/enrollments/{enrollment}/withdraw', [TrainingClassController::class, 'withdraw'])->name('classes.enrollments.withdraw');

    Route::get('groups/search', [GroupController::class, 'search'])->name('groups.search');
    Route::get('groups/data', [GroupController::class, 'data'])->name('groups.data');
    Route::resource('groups', GroupController::class)->except(['destroy']);
    Route::patch('groups/{group}/archive', [GroupController::class, 'archive'])->name('groups.archive');
    Route::post('groups/{group}/members', [GroupController::class, 'addMembers'])->name('groups.members.store');
    Route::delete('groups/{group}/members/{student}', [GroupController::class, 'removeMember'])->name('groups.members.destroy');

    Route::get('questions', [QuestionController::class, 'all'])->name('questions.index');
    Route::get('questions/create', [QuestionController::class, 'createGlobal'])->name('questions.create');
    Route::get('questions/import', [QuestionController::class, 'importGlobal'])->name('questions.import');
    Route::get('questions/data', [QuestionController::class, 'data'])->name('questions.data');
    Route::get('exams', [ExamController::class, 'all'])->name('exams.index');
    Route::get('exams/data', [ExamController::class, 'data'])->name('exams.data');
    Route::get('exams/create', [ExamController::class, 'createGlobal'])->name('exams.create');
    Route::post('exams', [ExamController::class, 'storeGlobal'])->name('exams.store');
    Route::get('exams/{exam}', [ExamController::class, 'showGlobal'])->name('exams.show');
    Route::get('exams/{exam}/edit', [ExamController::class, 'editGlobal'])->name('exams.edit');
    Route::put('exams/{exam}', [ExamController::class, 'updateGlobal'])->name('exams.update');
    Route::patch('exams/{exam}/archive', [ExamController::class, 'archive'])->name('exams.archive');
    Route::get('exam-schedules', [ExamScheduleController::class, 'index'])->name('exam-schedules.index');
    Route::get('exam-schedules/data', [ExamScheduleController::class, 'data'])->name('exam-schedules.data');
    Route::get('exam-schedules/create', [ExamScheduleController::class, 'create'])->name('exam-schedules.create');
    Route::post('exam-schedules', [ExamScheduleController::class, 'store'])->name('exam-schedules.store');
    Route::get('exam-schedules/{schedule}/edit', [ExamScheduleController::class, 'edit'])->name('exam-schedules.edit');
    Route::put('exam-schedules/{schedule}', [ExamScheduleController::class, 'update'])->name('exam-schedules.update');
    Route::patch('exam-schedules/{schedule}/cancel', [ExamScheduleController::class, 'cancel'])->name('exam-schedules.cancel');
    Route::get('exams/{exam}/schedules', [ExamScheduleController::class, 'index'])->name('exams.schedules.index');
    Route::get('exams/{exam}/schedules/create', [ExamScheduleController::class, 'create'])->name('exams.schedules.create');
    Route::post('exams/{exam}/schedules', [ExamScheduleController::class, 'store'])->name('exams.schedules.store');
    Route::get('exams/{exam}/schedules/{schedule}/edit', [ExamScheduleController::class, 'edit'])->name('exams.schedules.edit');
    Route::put('exams/{exam}/schedules/{schedule}', [ExamScheduleController::class, 'update'])->name('exams.schedules.update');
    Route::patch('exams/{exam}/schedules/{schedule}/cancel', [ExamScheduleController::class, 'cancel'])->name('exams.schedules.cancel');

    Route::get('courses/{course}/exams', [ExamController::class, 'index'])->name('courses.exams.index');
    Route::get('courses/{course}/exams/data', [ExamController::class, 'courseData'])->name('courses.exams.data');
    Route::get('courses/{course}/exams/create', [ExamController::class, 'create'])->name('courses.exams.create');
    Route::post('courses/{course}/exams', [ExamController::class, 'store'])->name('courses.exams.store');
    Route::get('courses/{course}/exams/{exam}', [ExamController::class, 'show'])->name('courses.exams.show');
    Route::get('courses/{course}/exams/{exam}/edit', [ExamController::class, 'edit'])->name('courses.exams.edit');
    Route::put('courses/{course}/exams/{exam}', [ExamController::class, 'update'])->name('courses.exams.update');
});
