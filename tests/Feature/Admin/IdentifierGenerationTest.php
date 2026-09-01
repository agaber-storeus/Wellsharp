<?php

namespace Tests\Feature\Admin;

use App\Actions\Users\CreateUserAction;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Role;
use App\Models\User;
use App\Services\Generation\GeneratesUniqueCode;
use App\Services\Generation\GenerationRetryExhaustedException;
use App\Services\ProctorIdGenerator;
use App\Services\QuestionCodeGenerator;
use App\Services\TemporaryPasswordGenerator;
use App\Services\UserIdentityGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentifierGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin)->withSession(['auth.session_version' => $this->admin->session_version]);
    }

    public function test_wellsharp_id_username_and_password_are_auto_generated_when_left_blank(): void
    {
        $response = $this->post(route('admin.users.store'), [
            'first_name' => 'Ahmed', 'last_name' => 'Gaber',
            'role_id' => Role::where('key', Role::PROCTOR)->value('id'),
        ]);
        $response->assertRedirect();

        $user = User::whereHas('profile', fn ($q) => $q->where('first_name', 'Ahmed')->where('last_name', 'Gaber'))->firstOrFail();

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5,8}$/', $user->wellsharp_id);
        $this->assertMatchesRegularExpression('/^[a-z]{5,8}$/', $user->username);
        $this->assertNotNull($user->getRawOriginal('password'));
        $this->assertFalse(Hash::needsRehash($user->getRawOriginal('password')));

        $response->assertSessionHas('generated_password');
        $generatedPassword = session('generated_password');
        $this->assertNotEmpty($generatedPassword);
        $this->assertGreaterThanOrEqual(5, strlen($generatedPassword));
        $this->assertLessThanOrEqual(8, strlen($generatedPassword));
        $this->assertSame(8, strlen($generatedPassword));
        $this->assertMatchesRegularExpression('/[A-Z]/', $generatedPassword);
        $this->assertMatchesRegularExpression('/[a-z]/', $generatedPassword);
        $this->assertMatchesRegularExpression('/[0-9]/', $generatedPassword);
        $this->assertTrue(Hash::check($generatedPassword, $user->getRawOriginal('password')));
        $this->assertStringNotContainsStringIgnoringCase($user->wellsharp_id, $generatedPassword);
        $this->assertStringNotContainsStringIgnoringCase($user->username, $generatedPassword);
    }

    public function test_admin_supplied_wellsharp_id_and_password_are_still_honored(): void
    {
        $this->post(route('admin.users.store'), [
            'wellsharp_id' => 'MANUAL-001', 'first_name' => 'Manual', 'last_name' => 'Entry',
            'password' => 'Manual8x', 'password_confirmation' => 'Manual8x',
            'role_id' => Role::where('key', Role::INSTRUCTOR)->value('id'),
        ])->assertRedirect()->assertSessionMissing('generated_password');

        $user = User::where('wellsharp_id', 'MANUAL-001')->firstOrFail();
        $this->assertTrue(Hash::check('Manual8x', $user->getRawOriginal('password')));
        $this->assertNotEmpty($user->username);
    }

    public function test_manual_password_outside_five_to_eight_characters_is_rejected(): void
    {
        $this->post(route('admin.users.store'), [
            'first_name' => 'Short', 'last_name' => 'Pass',
            'password' => 'Ab1', 'password_confirmation' => 'Ab1',
            'role_id' => Role::where('key', Role::INSTRUCTOR)->value('id'),
        ])->assertSessionHasErrors('password');

        $this->post(route('admin.users.store'), [
            'first_name' => 'Long', 'last_name' => 'Pass',
            'password' => 'WayTooLongPassword1', 'password_confirmation' => 'WayTooLongPassword1',
            'role_id' => Role::where('key', Role::INSTRUCTOR)->value('id'),
        ])->assertSessionHasErrors('password');
    }

    public function test_student_password_is_auto_generated_at_exactly_five_characters(): void
    {
        $response = $this->post(route('admin.students.store'), [
            'first_name' => 'Sara', 'last_name' => 'Ali',
        ]);
        $response->assertRedirect();

        $student = User::whereHas('profile', fn ($q) => $q->where('first_name', 'Sara')->where('last_name', 'Ali'))->firstOrFail();
        $this->assertSame(Role::STUDENT, $student->currentRole->key);

        $generatedPassword = session('generated_password');
        $this->assertSame(5, strlen($generatedPassword));
        $this->assertTrue(Hash::check($generatedPassword, $student->getRawOriginal('password')));
        $this->assertNotSame($generatedPassword, $student->getRawOriginal('password'));
        $this->assertStringNotContainsStringIgnoringCase($student->wellsharp_id, $generatedPassword);
        $this->assertStringNotContainsStringIgnoringCase($student->username, $generatedPassword);
    }

    public function test_non_student_roles_still_auto_generate_within_five_to_eight_default_eight(): void
    {
        foreach ([Role::PROCTOR, Role::INSTRUCTOR, Role::ADMIN] as $roleKey) {
            $response = $this->post(route('admin.users.store'), [
                'first_name' => 'Role', 'last_name' => ucfirst($roleKey),
                'role_id' => Role::where('key', $roleKey)->value('id'),
            ]);
            $response->assertRedirect();
            $generatedPassword = session('generated_password');
            $this->assertSame(8, strlen($generatedPassword), "default generated length for {$roleKey}");
        }
    }

    public function test_student_manual_password_must_be_exactly_five_characters(): void
    {
        foreach (['Ab12' => false, 'Abcd1' => true, 'Abcde1' => false, 'Abcdefg1' => false] as $password => $shouldPass) {
            $response = $this->post(route('admin.students.store'), [
                'first_name' => 'Case', 'last_name' => (string) strlen($password),
                'password' => $password, 'password_confirmation' => $password,
            ]);
            if ($shouldPass) {
                $response->assertSessionDoesntHaveErrors('password');
            } else {
                $response->assertSessionHasErrors('password');
            }
        }
    }

    public function test_non_student_manual_password_accepts_the_full_five_to_eight_range(): void
    {
        foreach (['Abcd1', 'Abcdefg1'] as $password) {
            $this->post(route('admin.users.store'), [
                'first_name' => 'Range', 'last_name' => (string) strlen($password),
                'password' => $password, 'password_confirmation' => $password,
                'role_id' => Role::where('key', Role::PROCTOR)->value('id'),
            ])->assertSessionDoesntHaveErrors('password');
        }
    }

    public function test_updating_a_student_enforces_exactly_five_while_updating_a_proctor_keeps_five_to_eight(): void
    {
        $student = User::factory()->student()->create();
        $this->put(route('admin.users.update', $student), [
            'first_name' => $student->profile->first_name, 'last_name' => $student->profile->last_name,
            'password' => 'Abcdefg1', 'password_confirmation' => 'Abcdefg1',
        ])->assertSessionHasErrors('password');
        $this->put(route('admin.users.update', $student), [
            'first_name' => $student->profile->first_name, 'last_name' => $student->profile->last_name,
            'password' => 'Abcd1', 'password_confirmation' => 'Abcd1',
        ])->assertSessionDoesntHaveErrors('password');

        $proctor = User::factory()->proctor()->create();
        $this->put(route('admin.users.update', $proctor), [
            'first_name' => $proctor->profile->first_name, 'last_name' => $proctor->profile->last_name,
            'password' => 'Abcd1', 'password_confirmation' => 'Abcd1',
        ])->assertSessionDoesntHaveErrors('password');
        $this->put(route('admin.users.update', $proctor), [
            'first_name' => $proctor->profile->first_name, 'last_name' => $proctor->profile->last_name,
            'password' => 'Ab1', 'password_confirmation' => 'Ab1',
        ])->assertSessionHasErrors('password');
    }

    public function test_role_change_does_not_regenerate_the_existing_password(): void
    {
        $student = User::factory()->student()->create();
        $studentHash = $student->getRawOriginal('password');
        $proctorRoleId = Role::where('key', Role::PROCTOR)->value('id');

        $this->patch(route('admin.users.role', $student), ['role_id' => $proctorRoleId])->assertRedirect();
        $this->assertSame($studentHash, $student->fresh()->getRawOriginal('password'));

        $proctor = User::factory()->proctor()->create();
        $proctorHash = $proctor->getRawOriginal('password');
        $studentRoleId = Role::where('key', Role::STUDENT)->value('id');

        $this->patch(route('admin.users.role', $proctor), ['role_id' => $studentRoleId])->assertRedirect();
        $this->assertSame($proctorHash, $proctor->fresh()->getRawOriginal('password'));
    }

    public function test_temporary_password_generator_generates_for_role(): void
    {
        $generator = app(TemporaryPasswordGenerator::class);

        for ($i = 0; $i < 20; $i++) {
            $this->assertSame(5, strlen($generator->generateForRole(Role::STUDENT)));
        }
        foreach ([Role::PROCTOR, Role::INSTRUCTOR, Role::ADMIN, null] as $roleKey) {
            $this->assertSame(8, strlen($generator->generateForRole($roleKey)));
        }
    }

    public function test_wellsharp_id_and_username_are_unique_even_for_many_identical_names(): void
    {
        $action = app(CreateUserAction::class);
        $roleId = Role::where('key', Role::STUDENT)->value('id');

        $wellsharpIds = [];
        $usernames = [];
        for ($i = 0; $i < 25; $i++) {
            $result = $action->execute([
                'first_name' => 'Ahmed', 'last_name' => 'Gaber', 'role_id' => $roleId,
            ]);
            $wellsharpIds[] = $result->user->wellsharp_id;
            $usernames[] = $result->user->username;
        }

        $this->assertCount(25, array_unique($wellsharpIds));
        $this->assertCount(25, array_unique($usernames));
        foreach ($usernames as $username) {
            $this->assertLessThanOrEqual(8, strlen($username));
            $this->assertGreaterThanOrEqual(5, strlen($username));
            $this->assertMatchesRegularExpression('/^[a-z]{5,8}$/', $username);
        }
    }

    public function test_name_edge_cases_generate_valid_identifiers_without_crashing(): void
    {
        $generator = app(UserIdentityGenerator::class);
        $cases = [
            ['Li', 'O'],
            ['Verylongfirstnamethatexceedsnormal', 'Verylonglastnamethatexceedsnormal'],
            ['  Ann  ', '  Marie  '],
            ['Anne-Marie', 'Smith-Jones'],
            ["O'Brien", "D'Angelo"],
            ['أحمد', 'جابر'],
            ['', ''],
        ];

        foreach ($cases as [$first, $last]) {
            $wellsharpId = $generator->generateWellsharpId($first, $last);
            $username = $generator->generateUsername($first, $last);

            $this->assertMatchesRegularExpression('/^[A-Z0-9]{5,8}$/', $wellsharpId, "WellSharp ID for [$first $last]");
            $this->assertMatchesRegularExpression('/^[a-z]{5,8}$/', $username, "Username for [$first $last]");
        }
    }

    public function test_temporary_password_generator_meets_the_application_policy(): void
    {
        $generator = app(TemporaryPasswordGenerator::class);
        $passwords = [];
        for ($i = 0; $i < 50; $i++) {
            $password = $generator->generate();
            $passwords[] = $password;
            $this->assertSame(8, strlen($password), 'default length must be 8');
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
        }
        $this->assertCount(50, array_unique($passwords));

        // Requested lengths are clamped to the 5-8 business range rather than honored verbatim.
        $this->assertSame(5, strlen($generator->generate(5)));
        $this->assertSame(8, strlen($generator->generate(8)));
        $this->assertSame(5, strlen($generator->generate(1)));
        $this->assertSame(8, strlen($generator->generate(20)));
    }

    public function test_exam_code_is_auto_generated_unique_and_stable_after_edit(): void
    {
        $course = Course::factory()->create();

        // StoreExamRequest requires question_ids, so the code-generation
        // assertion goes through a direct create - the HTTP round trip for
        // "code stays stable on edit" (below) is what exercises the form/route.
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Direct Create Exam', 'question_order_mode' => 'static', 'status' => 'draft']);
        $this->assertNotNull($exam->code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5,8}$/', $exam->code);

        $originalCode = $exam->code;
        $this->put(route('admin.courses.exams.update', [$course, $exam]), [
            'name' => 'Direct Create Exam Renamed', 'question_order_mode' => 'static', 'status' => 'draft',
            'question_ids' => [],
        ])->assertRedirect();
        $this->assertSame($originalCode, $exam->fresh()->code);
    }

    public function test_exam_codes_are_unique_across_many_exams(): void
    {
        $course = Course::factory()->create();
        $codes = collect(range(1, 20))->map(fn (int $i) => Exam::create([
            'course_id' => $course->id, 'name' => "Bulk Exam {$i}", 'question_order_mode' => 'static', 'status' => 'draft',
        ])->code);

        $this->assertCount(20, $codes->unique());
    }

    public function test_question_code_is_exactly_five_chars_unique_and_stable_after_edit(): void
    {
        $course = Course::factory()->create();
        $question = Question::create([
            'course_id' => $course->id, 'question_text' => 'Is the barrier verified?',
            'type' => 'true_false', 'difficulty' => 'easy', 'correct_answer_boolean' => true,
        ]);

        $this->assertNotNull($question->code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}$/', $question->code);

        $originalCode = $question->code;
        $question->update(['question_text' => 'Is the barrier verified and logged?']);
        $this->assertSame($originalCode, $question->fresh()->code);
    }

    public function test_question_codes_are_unique_across_many_questions(): void
    {
        $course = Course::factory()->create();
        $codes = collect(range(1, 30))->map(fn (int $i) => Question::create([
            'course_id' => $course->id, 'question_text' => "Bulk question {$i}",
            'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer',
        ])->code);

        $this->assertCount(30, $codes->unique());
        $codes->each(fn (string $code) => $this->assertSame(5, strlen($code)));
    }

    public function test_collision_on_first_candidate_is_retried_until_a_free_value_is_found(): void
    {
        $generator = new class
        {
            use GeneratesUniqueCode;

            public function run(array $sequence, callable $exists): string
            {
                $i = 0;

                return $this->generateUnique(function () use ($sequence, &$i): string {
                    return $sequence[$i++];
                }, $exists);
            }
        };

        $result = $generator->run(['DUPLICATE', 'DUPLICATE', 'FRESH'], fn (string $value): bool => $value === 'DUPLICATE');

        $this->assertSame('FRESH', $result);
    }

    public function test_generation_retry_throws_a_meaningful_exception_once_attempts_are_exhausted(): void
    {
        $generator = new class
        {
            use GeneratesUniqueCode;

            public function alwaysCollides(): string
            {
                return $this->generateUnique(fn (): string => 'TAKEN', fn (): bool => true, 3);
            }
        };

        $this->expectException(GenerationRetryExhaustedException::class);
        $generator->alwaysCollides();
    }

    public function test_question_code_generator_reports_length_exactly_five(): void
    {
        $code = app(QuestionCodeGenerator::class)->generate();
        $this->assertSame(5, strlen($code));
    }

    public function test_backfill_command_fills_missing_identifiers_without_touching_existing_ones_and_is_idempotent(): void
    {
        $withUsername = User::factory()->student()->create();
        $existingUsername = $withUsername->username;

        $missingUsername = User::factory()->student()->create();
        $missingUsername->forceFill(['username' => null])->save();

        $course = Course::factory()->create();
        $examWithCode = Exam::create(['course_id' => $course->id, 'name' => 'Has Code', 'question_order_mode' => 'static', 'status' => 'draft']);
        $existingExamCode = $examWithCode->code;
        $examMissingCode = Exam::create(['course_id' => $course->id, 'name' => 'Missing Code', 'question_order_mode' => 'static', 'status' => 'draft']);
        $examMissingCode->forceFill(['code' => null])->save();

        // questions.code is NOT NULL at the DB level (see the
        // 2026_08_20_000001 migration), so a "missing question code" row can
        // no longer exist to backfill - only users.username and exams.code
        // (both still nullable) are exercised here.
        $this->artisan('wellsharp:backfill-identifiers')->assertExitCode(0);

        $this->assertSame($existingUsername, $withUsername->fresh()->username);
        $this->assertSame($existingExamCode, $examWithCode->fresh()->code);
        $this->assertNotNull($missingUsername->fresh()->username);
        $this->assertNotNull($examMissingCode->fresh()->code);

        $backfilledUsername = $missingUsername->fresh()->username;
        $backfilledExamCode = $examMissingCode->fresh()->code;

        // Re-running must be a no-op: nothing left to backfill, nothing already
        // assigned gets touched or regenerated.
        $this->artisan('wellsharp:backfill-identifiers')->assertExitCode(0);
        $this->assertSame($backfilledUsername, $missingUsername->fresh()->username);
        $this->assertSame($backfilledExamCode, $examMissingCode->fresh()->code);
    }

    public function test_question_code_column_rejects_null_at_the_database_level(): void
    {
        $course = Course::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('questions')->insert([
            'public_id' => (string) Str::ulid(),
            'course_id' => $course->id,
            'code' => null,
            'question_text' => 'Bypassing Eloquent entirely',
            'type' => 'input',
            'difficulty' => 'easy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_migration_backfills_legacy_null_question_codes_before_enforcing_not_null(): void
    {
        $course = Course::factory()->create();

        // Simulate the pre-migration state: relax the constraint and insert
        // legacy rows with no code, exactly as older data would look before
        // this feature shipped.
        Schema::table('questions', fn (Blueprint $table) => $table->string('code', 5)->nullable()->change());
        DB::table('questions')->insert([
            ['public_id' => (string) Str::ulid(), 'course_id' => $course->id, 'code' => null, 'question_text' => 'Legacy A', 'type' => 'input', 'difficulty' => 'easy', 'created_at' => now(), 'updated_at' => now()],
            ['public_id' => (string) Str::ulid(), 'course_id' => $course->id, 'code' => null, 'question_text' => 'Legacy B', 'type' => 'input', 'difficulty' => 'easy', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->assertSame(2, Question::whereNull('code')->count());

        // Re-run the actual migration: backfill, verify zero NULLs, enforce NOT NULL.
        $migration = require database_path('migrations/2026_08_20_000001_backfill_and_enforce_question_code_not_null.php');
        $migration->up();

        $this->assertSame(0, Question::whereNull('code')->count());
        $codes = Question::whereIn('question_text', ['Legacy A', 'Legacy B'])->pluck('code');
        $this->assertCount(2, $codes->unique());
        $codes->each(fn (string $code) => $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}$/', $code));

        $this->expectException(QueryException::class);
        DB::table('questions')->insert([
            'public_id' => (string) Str::ulid(), 'course_id' => $course->id, 'code' => null,
            'question_text' => 'Should be rejected', 'type' => 'input', 'difficulty' => 'easy',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_proctors_id_is_generated_only_for_proctors_and_shares_the_centralized_format(): void
    {
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $this->assertNotNull($proctor->examControlCredential);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5,8}$/', $proctor->examControlCredential->control_id);
        $this->assertNull($instructor->examControlCredential);
        $this->assertNull($admin->examControlCredential);
        $this->assertNull($student->examControlCredential);
    }

    public function test_proctors_id_is_unique_across_many_proctors_and_stable_after_profile_edits(): void
    {
        $generator = app(ProctorIdGenerator::class);
        $ids = collect(range(1, 20))->map(fn () => $generator->generate());
        $this->assertCount(20, $ids->unique());
        $ids->each(fn (string $id) => $this->assertMatchesRegularExpression('/^[A-Z0-9]{5,8}$/', $id));

        $proctor = User::factory()->proctor()->create();
        $originalId = $proctor->examControlCredential->control_id;

        $this->actingAs($this->admin)->put(route('admin.users.update', $proctor), [
            'first_name' => 'Updated', 'last_name' => 'Name',
        ])->assertRedirect();

        $this->assertSame($originalId, $proctor->fresh()->examControlCredential->control_id);
    }

    public function test_proctor_id_generator_resolves_collisions_via_the_shared_retry_trait(): void
    {
        $generator = new class
        {
            use GeneratesUniqueCode;

            public function run(array $sequence, callable $exists): string
            {
                $i = 0;

                return $this->generateUnique(function () use ($sequence, &$i): string {
                    return $sequence[$i++];
                }, $exists);
            }
        };

        $result = $generator->run(['P1TAKEN', 'P1TAKEN', 'P1FRESH'], fn (string $value): bool => $value === 'P1TAKEN');
        $this->assertSame('P1FRESH', $result);
    }

    public function test_backfill_command_dry_run_reports_without_saving(): void
    {
        $missingUsername = User::factory()->student()->create();
        $missingUsername->forceFill(['username' => null])->save();

        $this->artisan('wellsharp:backfill-identifiers', ['--dry-run' => true])->assertExitCode(0);

        $this->assertNull($missingUsername->fresh()->username);
    }
}
