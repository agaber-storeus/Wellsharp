<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessDomainTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $subject;

    private Course $otherSubject;

    private User $studentOne;

    private User $studentTwo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->admin()->create();
        $this->studentOne = User::factory()->student()->create();
        $this->studentTwo = User::factory()->student()->create();
        $this->subject = Course::factory()->create(['name' => 'Well Control Subject']);
        $this->otherSubject = Course::factory()->create(['name' => 'Other Subject']);
        $this->actingAs($this->admin)->withSession(['auth.session_version' => $this->admin->session_version]);
    }

    public function test_admin_can_create_student_with_optional_profile_fields(): void
    {
        $this->post(route('admin.students.store'), [
            'wellsharp_id' => 'STUDENT-NEW-001', 'first_name' => 'New', 'last_name' => 'Student',
            'email' => 'new.student@example.test', 'age' => 32, 'gender' => 'Female',
            'password' => 'student-password-123', 'password_confirmation' => 'student-password-123',
        ])->assertRedirect();

        $student = User::where('wellsharp_id', 'STUDENT-NEW-001')->firstOrFail();
        $this->assertSame('student', $student->currentRole->key);
        $this->assertSame(32, $student->profile->age);
        $this->assertSame('Female', $student->profile->gender);
        $this->assertDatabaseHas('audit_events', ['action' => 'student.created', 'subject_id' => (string) $student->id]);
    }

    public function test_student_create_and_update_can_sync_multiple_group_memberships(): void
    {
        $morning = Group::create(['name' => 'Student Morning', 'status' => 'active']);
        $evening = Group::create(['name' => 'Student Evening', 'status' => 'active']);

        $this->post(route('admin.students.store'), [
            'wellsharp_id' => 'STUDENT-GROUPS-001', 'first_name' => 'Grouped', 'last_name' => 'Student',
            'password' => 'student-password-123', 'password_confirmation' => 'student-password-123',
            'group_ids' => [$morning->id, $evening->id],
        ])->assertRedirect();

        $student = User::where('wellsharp_id', 'STUDENT-GROUPS-001')->firstOrFail();
        $this->assertDatabaseCount('group_memberships', 2);

        $this->put(route('admin.users.update', $student), [
            'first_name' => 'Grouped', 'last_name' => 'Student', 'group_ids' => [$morning->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('group_memberships', ['group_id' => $morning->id, 'student_user_id' => $student->id, 'status' => 'active']);
        $this->assertDatabaseHas('group_memberships', ['group_id' => $evening->id, 'student_user_id' => $student->id, 'status' => 'removed']);
        $this->get(route('admin.users.edit', $student))->assertOk()->assertSee('Student Morning')->assertSee('Assigned Groups');
        $this->get(route('admin.users.show', $student))->assertOk()->assertSee('Student Morning')->assertDontSee('Student Evening');
    }

    public function test_student_age_is_optional_but_reasonably_validated(): void
    {
        $this->post(route('admin.students.store'), [
            'wellsharp_id' => 'STUDENT-INVALID-001', 'first_name' => 'Invalid', 'last_name' => 'Age',
            'password' => 'student-password-123', 'password_confirmation' => 'student-password-123', 'age' => 121,
        ])->assertSessionHasErrors('age');
        $this->assertDatabaseMissing('users', ['wellsharp_id' => 'STUDENT-INVALID-001']);
    }

    public function test_questions_page_filters_and_sorts_through_json_data_endpoint(): void
    {
        Question::create(['course_id' => $this->subject->id, 'question_text' => 'Alpine searchable question', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer']);

        $this->get(route('admin.questions.index'))->assertOk()->assertSee('Search, filter, and sort');
        $this->getJson(route('admin.questions.data', ['search' => 'Alpine', 'sort' => 'question_text', 'direction' => 'asc']))
            ->assertOk()
            ->assertJsonPath('data.0.question_text', 'Alpine searchable question');
    }

    public function test_student_table_endpoint_supports_search_filters_and_sorting(): void
    {
        $group = Group::create(['name' => 'Female Students', 'status' => 'active']);
        $this->studentOne->profile->update(['first_name' => 'Ada', 'last_name' => 'Zebra', 'age' => 29, 'gender' => 'Female']);
        $this->studentTwo->profile->update(['first_name' => 'Bob', 'last_name' => 'Alpha', 'age' => 31, 'gender' => 'Male']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $this->studentOne->id, 'status' => 'active', 'joined_at' => now()]);

        $this->getJson(route('admin.students.data', [
            'search' => 'Ada', 'gender' => 'Female', 'group_id' => $group->id,
            'sort' => 'name', 'direction' => 'asc', 'per_page' => 15,
        ]))->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.display_name', 'Ada Zebra')
            ->assertJsonPath('data.0.groups_count', 1);
    }

    public function test_group_table_endpoint_supports_search_filters_and_sorting(): void
    {
        Group::create(['name' => 'Alpha Group', 'code' => 'ALPHA', 'status' => 'active']);
        Group::create(['name' => 'Archived Group', 'code' => 'ARCHIVED', 'status' => 'archived']);

        $this->getJson(route('admin.groups.data', [
            'search' => 'Archived', 'status' => 'archived',
            'sort' => 'name', 'direction' => 'asc', 'per_page' => 15,
        ]))->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Archived Group')
            ->assertJsonPath('data.0.status', 'archived');
    }

    public function test_shared_user_form_renders_for_normal_and_student_users(): void
    {
        $this->get(route('admin.users.create'))->assertOk();
        $this->get(route('admin.users.edit', $this->studentOne))->assertOk()->assertSee('Age');
    }

    public function test_groups_support_many_to_many_memberships_and_history(): void
    {
        $group = Group::create(['name' => 'Morning Group', 'code' => 'MORNING', 'status' => 'active']);
        $secondGroup = Group::create(['name' => 'Evening Group', 'code' => 'EVENING', 'status' => 'active']);
        $this->getJson(route('admin.groups.search', ['q' => 'Morn']))
            ->assertOk()
            ->assertJsonFragment(['id' => $group->id, 'name' => 'Morning Group']);
        $this->studentOne->profile->update(['first_name' => 'Searchable', 'last_name' => 'Student']);
        $this->get(route('admin.groups.show', ['group' => $group, 'student_search' => 'Searchable']))
            ->assertOk()
            ->assertSee('Searchable Student')
            ->assertSee('Add selected Students');

        $this->post(route('admin.groups.members.store', $group), ['student_ids' => [$this->studentOne->id, $this->studentTwo->id]])->assertRedirect();
        $this->post(route('admin.groups.members.store', $secondGroup), ['student_ids' => [$this->studentOne->id]])->assertRedirect();
        $this->post(route('admin.groups.members.store', $group), ['student_ids' => [$this->studentOne->id]])->assertSessionHasErrors('student_ids');

        $this->assertCount(2, $group->fresh()->students);
        $this->assertCount(2, $this->studentOne->fresh()->groups);
        $this->delete(route('admin.groups.members.destroy', [$group, $this->studentOne]))->assertRedirect();
        $this->assertDatabaseHas('group_memberships', ['group_id' => $group->id, 'student_user_id' => $this->studentOne->id, 'status' => 'removed']);
        $this->post(route('admin.groups.members.store', $group), ['student_ids' => [$this->studentOne->id]])->assertRedirect();
        $this->delete(route('admin.groups.members.destroy', [$group, $this->studentOne]))->assertRedirect();
        $this->assertSame(2,
            (int) GroupMembership::query()->where('group_id', $group->id)->where('student_user_id', $this->studentOne->id)->where('status', 'removed')->count()
        );
        $this->assertDatabaseHas('audit_events', ['action' => 'group.student_removed']);
    }

    public function test_exam_uses_only_questions_from_its_subject_and_persists_static_order(): void
    {
        $first = Question::create(['course_id' => $this->subject->id, 'question_text' => 'First question', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'one']);
        $second = Question::create(['course_id' => $this->subject->id, 'question_text' => 'Second question', 'type' => 'true_false', 'difficulty' => 'medium', 'correct_answer_boolean' => true]);
        $foreign = Question::create(['course_id' => $this->otherSubject->id, 'question_text' => 'Foreign question', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'foreign']);

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Final Exam', 'code' => 'FINAL-001', 'description' => 'Final assessment',
            'question_order_mode' => 'static', 'status' => 'draft',
            'question_ids' => [$second->id, $first->id], 'display_orders' => [$second->id => 1, $first->id => 2],
        ])->assertRedirect();
        $exam = Exam::where('code', 'FINAL-001')->firstOrFail();
        $this->assertSame([$second->id, $first->id], $exam->examQuestions()->pluck('question_id')->all());
        $this->get(route('admin.exams.index'))->assertOk()->assertSee('Search, filter, and sort reusable assessments');
        $this->getJson(route('admin.exams.data', ['search' => 'Final Exam', 'sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Final Exam');

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Invalid Exam', 'question_order_mode' => 'shuffle', 'status' => 'draft',
            'question_ids' => [$first->id, $foreign->id], 'display_orders' => [$first->id => 1, $foreign->id => 2],
        ])->assertSessionHasErrors('question_ids');
        $this->assertDatabaseMissing('exams', ['name' => 'Invalid Exam']);
    }

    public function test_exam_shuffle_mode_keeps_one_common_question_set_and_each_group_gets_its_own_schedule(): void
    {
        $questions = collect(range(1, 3))->map(fn (int $number) => Question::create([
            'course_id' => $this->subject->id, 'question_text' => "Shuffle question {$number}",
            'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer',
        ]));
        $group = Group::create(['name' => 'Exam Group', 'status' => 'active']);
        $secondGroup = Group::create(['name' => 'Second Exam Group', 'status' => 'active']);
        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Shuffle Exam', 'question_order_mode' => 'shuffle', 'status' => 'published',
            'question_ids' => $questions->pluck('id')->all(),
        ])->assertRedirect();
        $exam = Exam::where('name', 'Shuffle Exam')->firstOrFail();
        $this->assertSame('shuffle', $exam->question_order_mode->value);
        foreach ([$group, $secondGroup] as $index => $targetGroup) {
            $this->post(route('admin.exam-schedules.store'), [
                'exam_id' => $exam->id, 'group_id' => $targetGroup->id,
                'start_date' => now()->addDays(3 + $index)->format('Y-m-d'),
                'end_date' => now()->addDays(5 + $index)->format('Y-m-d'),
                'duration_minutes' => 90,
            ])->assertRedirect();
        }
        $this->assertDatabaseCount('exam_schedules', 2);
        $this->assertDatabaseHas('exam_schedules', ['exam_id' => $exam->id, 'group_id' => $secondGroup->id]);
    }

    public function test_exam_schedules_validate_ranges_and_can_be_cancelled_without_deletion(): void
    {
        $exam = Exam::create(['course_id' => $this->subject->id, 'name' => 'Scheduled Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $group = Group::create(['name' => 'Scheduled Group', 'status' => 'active']);
        $startDate = now()->addDays(2)->format('Y-m-d');
        $endDate = now()->addDays(4)->format('Y-m-d');
        $this->post(route('admin.exam-schedules.store'), [
            'exam_id' => $exam->id, 'group_id' => $group->id,
            'start_date' => $startDate, 'end_date' => $endDate,
            'duration_minutes' => 60,
        ])->assertRedirect();
        $schedule = ExamSchedule::firstOrFail();
        $this->assertSame('scheduled', $schedule->status->value);
        $this->assertNotNull($schedule->training_class_id);
        $this->get(route('admin.exam-schedules.create'))->assertOk()->assertDontSee('Operational Class');
        $this->get(route('admin.exam-schedules.index'))->assertOk()->assertSee('Search, filter, and sort schedules');
        $this->getJson(route('admin.exam-schedules.data', ['search' => 'Scheduled Exam']))
            ->assertOk()
            ->assertJsonPath('data.0.exam', 'Scheduled Exam');
        $this->put(route('admin.exam-schedules.update', $schedule), [
            'exam_id' => $exam->id, 'group_id' => $group->id,
            'start_date' => now()->addDays(3)->format('Y-m-d'), 'end_date' => now()->addDays(5)->format('Y-m-d'),
            'duration_minutes' => 60,
        ])->assertRedirect();
        $this->post(route('admin.exam-schedules.store'), [
            'exam_id' => $exam->id, 'group_id' => $group->id,
            'start_date' => $endDate, 'end_date' => $startDate, 'duration_minutes' => 60,
        ])->assertSessionHasErrors('end_date');
        $this->patch(route('admin.exam-schedules.cancel', $schedule))->assertRedirect();
        $this->assertDatabaseHas('exam_schedules', ['id' => $schedule->id, 'status' => 'cancelled']);
    }

    public function test_non_admin_roles_cannot_manage_groups_exams_or_schedules(): void
    {
        $group = Group::create(['name' => 'Protected Group', 'status' => 'active']);
        $exam = Exam::create(['course_id' => $this->subject->id, 'name' => 'Protected Exam', 'question_order_mode' => 'static', 'status' => 'draft']);
        foreach ([$this->studentOne, User::factory()->proctor()->create(), User::factory()->instructor()->create()] as $user) {
            $this->actingAs($user)->withSession(['auth.session_version' => $user->session_version]);
            $this->get(route('admin.groups.index'))->assertForbidden();
            $this->get(route('admin.courses.exams.index', $this->subject))->assertForbidden();
            $this->get(route('admin.exam-schedules.index'))->assertForbidden();
        }
    }
}
