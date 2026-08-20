<?php

namespace Tests\Feature\Infrastructure;

use App\Enums\CertificateStatus;
use App\Enums\ClassStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamGroupAssignmentStatus;
use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamScheduleStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Models\Certificate;
use App\Models\Exam;
use App\Models\Role;
use App\Models\StudentSurvey;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_covers_supported_lifecycles_and_certificate_documents(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('users', ['wellsharp_id' => 'DEMO-STUDENT-DISABLED', 'status' => 'disabled']);
        $this->assertDatabaseHas('users', ['wellsharp_id' => 'DEMO-PROCTOR-DISABLED', 'status' => 'disabled']);
        $this->assertDatabaseHas('users', ['wellsharp_id' => 'DEMO-STUDENT-MINIMAL', 'email' => null]);
        $this->assertDatabaseHas('users', ['wellsharp_id' => 'DEMO-INSTRUCTOR-ARCHIVED', 'status' => 'archived']);
        $this->assertDatabaseHas('training_providers', ['provider_number' => 'DEMO-TP-ARCHIVED', 'status' => 'archived']);
        $this->assertDatabaseHas('course_levels', ['slug' => 'legacy-level', 'is_active' => 0]);
        $this->assertDatabaseHas('questions', ['is_active' => 0]);

        foreach (ExamStatus::cases() as $status) {
            $this->assertDatabaseHas('exams', ['status' => $status->value]);
        }

        foreach (ExamGroupAssignmentStatus::cases() as $status) {
            $this->assertDatabaseHas('exam_group_assignments', ['status' => $status->value]);
        }

        foreach (ExamScheduleStatus::cases() as $status) {
            $this->assertDatabaseHas('exam_schedules', ['status' => $status->value]);
        }

        foreach (ClassStatus::cases() as $status) {
            $this->assertDatabaseHas('classes', ['status' => $status->value]);
        }

        foreach (QuestionType::cases() as $type) {
            $this->assertDatabaseHas('questions', ['type' => $type->value]);
        }

        foreach (QuestionDifficulty::cases() as $difficulty) {
            $this->assertDatabaseHas('questions', ['difficulty' => $difficulty->value]);
        }

        foreach (ExamQuestionOrderMode::cases() as $mode) {
            $this->assertDatabaseHas('exams', ['question_order_mode' => $mode->value]);
        }

        $this->assertNotNull($this->app['db']->table('classes')->where('status', ClassStatus::Active->value)->value('actual_started_at'));
        $this->assertNotNull($this->app['db']->table('classes')->where('status', ClassStatus::Completed->value)->value('actual_started_at'));
        $this->assertNotNull($this->app['db']->table('classes')->where('status', ClassStatus::Completed->value)->value('actual_ended_at'));
        $this->assertNull($this->app['db']->table('classes')->where('status', ClassStatus::Planned->value)->value('actual_started_at'));

        $this->assertDatabaseHas('exam_control_credentials', ['control_id' => 'PR-DEMO-PROCTOR-001']);
        $this->assertDatabaseHas('exam_control_credentials', ['control_id' => 'PR-DEMO-PROCTOR-DISABLED']);
        $archivedInstructor = User::query()->where('wellsharp_id', 'DEMO-INSTRUCTOR-ARCHIVED')->firstOrFail();
        $this->assertDatabaseMissing('exam_control_credentials', ['user_id' => $archivedInstructor->getKey()]);

        // Only the Proctor role ever owns a Proctor's ID.
        $instructorIds = User::query()->whereHas('currentRole', fn ($query) => $query->where('key', Role::INSTRUCTOR))->pluck('id');
        $this->assertSame(0, (int) $this->app['db']->table('exam_control_credentials')->whereIn('user_id', $instructorIds)->count());

        $this->assertSame(0, (int) $this->app['db']->table('user_profiles')->whereNotNull('gender')->whereIn('user_id', function ($query): void {
            $query->select('users.id')->from('users')->join('roles', 'roles.id', '=', 'users.current_role_id')->whereIn('roles.key', [Role::ADMIN, Role::PROCTOR, Role::INSTRUCTOR]);
        })->count());
        $this->assertGreaterThan(0, StudentSurvey::query()->where('status', 'completed')->count());
        $this->assertGreaterThan(0, StudentSurvey::query()->where('status', 'started')->count());
        $this->assertGreaterThan(0, (int) $this->app['db']->table('student_survey_answers')->count());

        $this->assertGreaterThan(0, Exam::query()->where('question_order_mode', ExamQuestionOrderMode::Shuffle)->whereHas('examQuestions')->count());
        $this->assertGreaterThan(0, Exam::query()->where('question_order_mode', ExamQuestionOrderMode::Static)->whereHas('examQuestions')->count());

        foreach (ExamAttemptStatus::cases() as $status) {
            $this->assertDatabaseHas('exam_attempts', ['status' => $status->value]);
        }

        $this->assertDatabaseHas('exam_attempts', ['status' => 'submitted', 'passed' => 1]);
        $this->assertDatabaseHas('exam_attempts', ['status' => 'submitted', 'passed' => 0]);
        $this->assertDatabaseHas('certificates', ['status' => CertificateStatus::Revoked->value]);
        $this->assertGreaterThan(0, Certificate::query()->count());
        $this->assertSame(Certificate::query()->count() * 3, (int) $this->app['db']->table('certificate_documents')->count());

        // questions.code is NOT NULL/unique as of the backfill-and-enforce
        // migration - every seeded question must carry a valid 5-character code.
        $questionCount = (int) $this->app['db']->table('questions')->count();
        $this->assertGreaterThan(0, $questionCount);
        $this->assertSame(0, (int) $this->app['db']->table('questions')->whereNull('code')->count());
        $questionCodes = $this->app['db']->table('questions')->pluck('code');
        foreach ($questionCodes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}$/', (string) $code);
        }

        // users.username is a required-at-creation, generated display id.
        $userCount = (int) $this->app['db']->table('users')->count();
        $this->assertGreaterThan(0, $userCount);
        $this->assertSame(0, (int) $this->app['db']->table('users')->whereNull('username')->count());

        // Every active role has at least one seeded user representing it.
        foreach ([Role::ADMIN, Role::PROCTOR, Role::INSTRUCTOR, Role::STUDENT] as $roleKey) {
            $this->assertDatabaseHas('users', ['current_role_id' => Role::query()->where('key', $roleKey)->value('id')]);
        }

        // enrollments.skills_score must stay within NavigationController's
        // validated 0-100 range and exercise both ends of it, not just highs.
        $this->assertGreaterThan(0, (int) $this->app['db']->table('enrollments')->where('skills_score', '<', 70)->count());
        $this->assertGreaterThan(0, (int) $this->app['db']->table('enrollments')->where('skills_score', '=', 100)->count());
        $this->assertSame(0, (int) $this->app['db']->table('enrollments')->where('skills_score', '<', 0)->orWhere('skills_score', '>', 100)->count());

        // Running the command again must update the DEMO records instead of
        // creating duplicate users, attempts, certificates, or documents.
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(1, (int) $this->app['db']->table('users')->where('wellsharp_id', 'DEMO-ADMIN-001')->count());
        $this->assertSame(1, (int) $this->app['db']->table('certificates')->where('student_wellsharp_id', 'like', 'DEMO-STUDENT-%')->where('status', 'revoked')->count());
        $this->assertSame(Certificate::query()->count() * 3, (int) $this->app['db']->table('certificate_documents')->count());
    }
}
