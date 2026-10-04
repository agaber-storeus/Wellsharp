<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\TrainingProvider;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\QueryException;
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
            'email' => 'new.student@example.test', 'birthday' => now()->subYears(32)->toDateString(), 'gender' => 'Female',
            'password' => 'Stud1', 'password_confirmation' => 'Stud1',
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
            'password' => 'Stud1', 'password_confirmation' => 'Stud1',
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

    public function test_student_age_is_derived_from_birthday_and_reasonably_validated(): void
    {
        $this->post(route('admin.students.store'), [
            'wellsharp_id' => 'STUDENT-INVALID-001', 'first_name' => 'Invalid', 'last_name' => 'Age',
            'password' => 'Stud1', 'password_confirmation' => 'Stud1', 'birthday' => now()->subYears(121)->toDateString(),
        ])->assertSessionHasErrors('birthday');
        $this->assertDatabaseMissing('users', ['wellsharp_id' => 'STUDENT-INVALID-001']);
    }

    public function test_a_client_submitted_age_is_ignored_in_favor_of_the_birthday_derived_value(): void
    {
        $this->post(route('admin.students.store'), [
            'wellsharp_id' => 'STUDENT-SPOOFED-AGE', 'first_name' => 'Spoofed', 'last_name' => 'Age',
            'password' => 'Stud1', 'password_confirmation' => 'Stud1',
            'birthday' => now()->subYears(20)->toDateString(), 'age' => 99,
        ])->assertRedirect();

        $student = User::where('wellsharp_id', 'STUDENT-SPOOFED-AGE')->firstOrFail();
        $this->assertSame(20, $student->profile->age);
    }

    public function test_updating_a_students_birthday_recalculates_the_stored_age(): void
    {
        $this->post(route('admin.students.store'), [
            'wellsharp_id' => 'STUDENT-AGE-UPDATE', 'first_name' => 'Age', 'last_name' => 'Updates',
            'password' => 'Stud1', 'password_confirmation' => 'Stud1', 'birthday' => now()->subYears(18)->toDateString(),
        ])->assertRedirect();
        $student = User::where('wellsharp_id', 'STUDENT-AGE-UPDATE')->firstOrFail();
        $this->assertSame(18, $student->profile->age);

        $this->put(route('admin.users.update', $student), [
            'first_name' => 'Age', 'last_name' => 'Updates', 'birthday' => now()->subYears(45)->toDateString(),
        ])->assertRedirect();

        $this->assertSame(45, $student->profile->fresh()->age);
    }

    public function test_calculate_age_handles_null_birthday_and_the_not_yet_had_birthday_this_year_edge_case(): void
    {
        $this->assertNull(UserProfile::calculateAge(null));
        $this->assertNull(UserProfile::calculateAge(''));

        // Born exactly 10 years ago tomorrow: the birthday has not happened
        // yet this year, so the age must still read 9, not 10.
        $notYetHadBirthday = now()->subYears(10)->addDay();
        $this->assertSame(9, UserProfile::calculateAge($notYetHadBirthday->toDateString()));

        $alreadyHadBirthday = now()->subYears(10)->subDay();
        $this->assertSame(10, UserProfile::calculateAge($alreadyHadBirthday->toDateString()));
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

    public function test_admin_archival_tables_use_alpine_controls_and_persist_archive_state(): void
    {
        $group = Group::create(['name' => 'Archive Group', 'code' => 'ARCHIVE-GROUP', 'status' => 'active']);
        $this->get(route('admin.groups.index'))
            ->assertOk()
            ->assertSee('archiveGroup(group)', false)
            ->assertSee('group.archive_url', false)
            ->assertSee('archive_url', false);
        $this->patchJson(route('admin.groups.archive', $group))
            ->assertOk()
            ->assertJsonPath('status', 'archived');
        $this->assertDatabaseHas('student_groups', ['id' => $group->id, 'status' => 'archived']);
        $this->assertDatabaseHas('audit_events', ['action' => 'group.updated']);

        $subject = Course::factory()->create(['name' => 'Archive Subject', 'status' => 'active']);
        $this->get(route('admin.courses.index'))
            ->assertOk()
            ->assertSee('archiveSubject(subject)', false)
            ->assertSee('subject.archiving', false)
            ->assertSee('Archived');
        $this->patchJson(route('admin.courses.archive', $subject))
            ->assertOk()
            ->assertJsonPath('status', 'retired')
            ->assertJsonPath('status_label', 'Archived');
        $this->assertDatabaseHas('courses', ['id' => $subject->id, 'status' => 'retired']);
        $this->assertDatabaseHas('audit_events', ['action' => 'course.archived']);

        $this->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('toggleStatus(student)', false)
            ->assertSee('archiveStudent(student)', false)
            ->assertSee('archive_url', false);
        $this->patchJson(route('admin.users.archive', $this->studentOne))
            ->assertOk()
            ->assertJsonPath('status', 'archived');
        $this->assertDatabaseHas('users', ['id' => $this->studentOne->id, 'status' => 'archived']);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.archived']);
        $this->getJson(route('admin.students.data', ['status' => 'archived']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'archived');
    }

    public function test_admin_can_unarchive_every_archivable_dashboard_entity(): void
    {
        $user = User::factory()->student()->archived()->create();
        $provider = TrainingProvider::factory()->archived()->create();
        $subject = Course::factory()->retired()->create(['archived_at' => now()]);
        $group = Group::factory()->archived()->create();
        $exam = Exam::factory()->archived()->create(['course_id' => $this->subject->id]);
        $question = Question::create([
            'course_id' => $this->subject->id,
            'question_text' => 'Restore this question',
            'type' => 'input',
            'difficulty' => 'easy',
            'correct_answer_text' => 'answer',
            'is_active' => false,
        ]);

        $this->patchJson(route('admin.users.unarchive', $user))->assertOk()->assertJsonPath('status', 'active');
        $this->patchJson(route('admin.providers.unarchive', $provider))->assertOk()->assertJsonPath('status', 'active');
        $this->patchJson(route('admin.courses.unarchive', $subject))->assertOk()->assertJsonPath('status', 'active');
        $this->patchJson(route('admin.groups.unarchive', $group))->assertOk()->assertJsonPath('status', 'active');
        $this->patchJson(route('admin.exams.unarchive', $exam))->assertOk()->assertJsonPath('status', 'draft');
        $this->patchJson(route('admin.courses.questions.unarchive', [$this->subject, $question]))->assertOk()->assertJsonPath('active', true);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active', 'archived_at' => null]);
        $this->assertDatabaseHas('training_providers', ['id' => $provider->id, 'status' => 'active', 'archived_at' => null]);
        $this->assertDatabaseHas('courses', ['id' => $subject->id, 'status' => 'active', 'archived_at' => null]);
        $this->assertDatabaseHas('student_groups', ['id' => $group->id, 'status' => 'active']);
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'status' => 'draft']);
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'is_active' => 1]);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.unarchived']);
        $this->assertDatabaseHas('audit_events', ['action' => 'training_provider.unarchived']);
        $this->assertDatabaseHas('audit_events', ['action' => 'course.unarchived']);
        $this->assertDatabaseHas('audit_events', ['action' => 'group.unarchived']);
        $this->assertDatabaseHas('audit_events', ['action' => 'exam.unarchived']);
        $this->assertDatabaseHas('audit_events', ['action' => 'question.unarchived']);
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
        $this->get(route('admin.exams.index'))->assertSee('archiveExam(exam)', false);
        $this->patchJson(route('admin.exams.archive', $exam))
            ->assertOk()
            ->assertJsonPath('status', 'archived');
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'status' => 'archived']);
        $this->assertDatabaseHas('audit_events', ['action' => 'exam.archived']);

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Invalid Exam', 'question_order_mode' => 'shuffle', 'status' => 'draft',
            'question_ids' => [$first->id, $foreign->id], 'display_orders' => [$first->id => 1, $foreign->id => 2],
        ])->assertSessionHasErrors('question_ids');
        $this->assertDatabaseMissing('exams', ['name' => 'Invalid Exam']);
    }

    public function test_manual_exam_question_cards_render_answers_selection_and_admin_edit_links(): void
    {
        $mcq = Question::create(['course_id' => $this->subject->id, 'question_text' => 'Which barrier is correct?', 'type' => 'mcq', 'difficulty' => 'hard']);
        QuestionOption::create(['question_id' => $mcq->id, 'option_text' => 'Approved barrier', 'is_correct' => true, 'display_order' => 1]);
        QuestionOption::create(['question_id' => $mcq->id, 'option_text' => 'Neutral wrong option', 'is_correct' => false, 'display_order' => 2]);
        $trueFalse = Question::create(['course_id' => $this->subject->id, 'question_text' => 'Pressure is controlled.', 'type' => 'true_false', 'difficulty' => 'medium', 'correct_answer_boolean' => true]);
        $input = Question::create(['course_id' => $this->subject->id, 'question_text' => 'Enter the expected acronym.', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'BOP']);

        $create = $this->get(route('admin.courses.exams.create', $this->subject))->assertOk();
        foreach (['Which barrier is correct?', 'Approved barrier', 'Neutral wrong option', 'Pressure is controlled.', 'Enter the expected acronym.', 'BOP'] as $text) {
            $create->assertSee($text);
        }
        $create->assertSee('\\u0022correct\\u0022:true', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('edit_url', false)
            ->assertSee('x-if="selectionMode === \'manual\'"', false);

        $exam = Exam::factory()->create(['course_id' => $this->subject->id, 'question_selection_mode' => 'manual']);
        $exam->questions()->sync([
            $mcq->id => ['display_order' => 1],
            $trueFalse->id => ['display_order' => 2],
            $input->id => ['display_order' => 3],
        ]);

        $edit = $this->get(route('admin.courses.exams.edit', [$this->subject, $exam]))->assertOk();
        $edit->assertSee('Approved barrier')->assertSee('BOP')->assertSee((string) $mcq->id);

        $show = $this->get(route('admin.courses.exams.show', [$this->subject, $exam]))->assertOk();
        $show->assertSee('Approved barrier')
            ->assertSee('Neutral wrong option')
            ->assertSee('True')
            ->assertSee('False')
            ->assertSee('Expected answer')
            ->assertSee('BOP')
            ->assertSee('Correct')
            ->assertSee(route('admin.courses.questions.edit', [$this->subject, $mcq]), false);
    }

    public function test_random_exam_show_does_not_render_manual_question_cards(): void
    {
        $question = Question::create(['course_id' => $this->subject->id, 'question_text' => 'Hidden random-bank answer', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'Secret answer']);
        $exam = Exam::factory()->create(['course_id' => $this->subject->id, 'question_selection_mode' => 'random', 'question_count' => 1]);
        $exam->questions()->sync([$question->id => ['display_order' => 1]]);

        $this->get(route('admin.courses.exams.show', [$this->subject, $exam]))
            ->assertOk()
            ->assertDontSee('exam-question-grid', false)
            ->assertDontSee('Hidden random-bank answer')
            ->assertDontSee('Secret answer');
    }

    public function test_exams_display_and_sort_by_created_at_with_newest_first_by_default(): void
    {
        $oldest = Exam::factory()->create(['course_id' => $this->subject->id, 'name' => 'Oldest Exam', 'created_at' => now()->subDays(3)]);
        $newest = Exam::factory()->create(['course_id' => $this->subject->id, 'name' => 'Newest Exam', 'created_at' => now()->subDay()]);

        $this->get(route('admin.exams.index'))
            ->assertOk()
            ->assertSee("sortBy('created_at')", false)
            ->assertSee('Created At');

        $this->getJson(route('admin.exams.data'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $oldest->id)
            ->assertJsonPath('data.0.created_at', $newest->created_at->format('M j, Y H:i'));

        $this->getJson(route('admin.exams.data', ['sort' => 'created_at', 'direction' => 'asc']))
            ->assertJsonPath('data.0.id', $oldest->id)
            ->assertJsonPath('data.1.id', $newest->id);

        $this->getJson(route('admin.exams.data', ['sort' => 'created_at', 'direction' => 'desc']))
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $oldest->id);
    }

    public function test_exam_schedules_display_and_sort_by_created_at_with_newest_first_by_default(): void
    {
        $oldest = ExamSchedule::factory()->create(['created_at' => now()->subDays(3)]);
        $newest = ExamSchedule::factory()->create(['created_at' => now()->subDay()]);

        $this->get(route('admin.exam-schedules.index'))
            ->assertOk()
            ->assertSee("sortBy('created_at')", false)
            ->assertSee('Created At');

        $this->getJson(route('admin.exam-schedules.data'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $oldest->id)
            ->assertJsonPath('data.0.created_at', $newest->created_at->format('M j, Y H:i'));

        $this->getJson(route('admin.exam-schedules.data', ['sort' => 'created_at', 'direction' => 'asc']))
            ->assertJsonPath('data.0.id', $oldest->id)
            ->assertJsonPath('data.1.id', $newest->id);

        $this->getJson(route('admin.exam-schedules.data', ['sort' => 'created_at', 'direction' => 'desc']))
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $oldest->id);
    }

    public function test_created_at_sort_preserves_exam_and_schedule_filtering_and_pagination(): void
    {
        Exam::factory()->count(26)->create(['course_id' => $this->subject->id, 'status' => 'draft']);
        Exam::factory()->create(['course_id' => $this->otherSubject->id, 'status' => 'published']);

        $this->getJson(route('admin.exams.data', [
            'course_id' => $this->subject->id,
            'status' => 'draft',
            'sort' => 'created_at',
            'direction' => 'desc',
            'page' => 2,
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.current_page', 2);

        $exam = Exam::factory()->create(['course_id' => $this->subject->id]);
        $group = Group::factory()->create();
        ExamSchedule::factory()->count(26)->create(['exam_id' => $exam->id, 'group_id' => $group->id, 'status' => 'scheduled']);
        ExamSchedule::factory()->cancelled()->create();

        $this->getJson(route('admin.exam-schedules.data', [
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'status' => 'scheduled',
            'sort' => 'created_at',
            'direction' => 'desc',
            'page' => 2,
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_global_exam_create_loads_selected_subject_questions_and_saves_status(): void
    {
        $subjectQuestion = Question::create([
            'course_id' => $this->subject->id,
            'question_text' => 'Selected subject question',
            'type' => 'input',
            'difficulty' => 'easy',
            'correct_answer_text' => 'answer',
        ]);
        Question::create([
            'course_id' => $this->otherSubject->id,
            'question_text' => 'Other subject question',
            'type' => 'input',
            'difficulty' => 'easy',
            'correct_answer_text' => 'answer',
        ]);

        $this->get(route('admin.exams.create', ['course_id' => $this->subject->id]))
            ->assertOk()
            ->assertSee('Selected subject question')
            ->assertSee('Other subject question')
            ->assertSee('x-on:change="changeSubject"', false)
            ->assertSee('x-model.debounce.200ms="search"', false)
            ->assertSee('x-on:click="sortBy(\'text\')"', false)
            ->assertDontSee('window.location=', false);

        $this->post(route('admin.exams.store'), [
            'course_id' => $this->subject->id,
            'name' => 'Global Subject Exam',
            'question_order_mode' => 'static',
            'status' => 'published',
            'question_ids' => [$subjectQuestion->id],
            'display_orders' => [$subjectQuestion->id => 1],
        ])->assertRedirect();

        $exam = Exam::query()->where('name', 'Global Subject Exam')->firstOrFail();
        $this->assertSame($this->subject->id, $exam->course_id);
        $this->assertSame('published', $exam->status->value);
        $this->assertSame([$subjectQuestion->id], $exam->examQuestions()->pluck('question_id')->all());
    }

    public function test_admin_can_set_and_update_certificate_validity_years_on_an_exam(): void
    {
        $question = Question::create(['course_id' => $this->subject->id, 'question_text' => 'Validity question', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer']);

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Validity Exam', 'code' => 'VALIDITY-001', 'question_order_mode' => 'static', 'status' => 'draft',
            'certificate_validity_years' => 3,
            'question_ids' => [$question->id], 'display_orders' => [$question->id => 1],
        ])->assertRedirect();
        $exam = Exam::where('code', 'VALIDITY-001')->firstOrFail();
        $this->assertSame(3, $exam->certificate_validity_years);

        $this->put(route('admin.courses.exams.update', [$this->subject, $exam]), [
            'name' => 'Validity Exam', 'question_order_mode' => 'static', 'status' => 'draft',
            'certificate_validity_years' => 5,
            'question_ids' => [$question->id], 'display_orders' => [$question->id => 1],
        ])->assertRedirect();
        $this->assertSame(5, $exam->fresh()->certificate_validity_years);

        // Leaving it blank clears it back to "use the default" rather than
        // silently keeping a stale value, matching how passing_score/retake_score behave.
        $this->put(route('admin.courses.exams.update', [$this->subject, $exam]), [
            'name' => 'Validity Exam', 'question_order_mode' => 'static', 'status' => 'draft',
            'question_ids' => [$question->id], 'display_orders' => [$question->id => 1],
        ])->assertRedirect();
        $this->assertNull($exam->fresh()->certificate_validity_years);
    }

    public function test_certificate_validity_years_rejects_out_of_range_values(): void
    {
        $question = Question::create(['course_id' => $this->subject->id, 'question_text' => 'Range question', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer']);

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Zero Validity Exam', 'question_order_mode' => 'static', 'status' => 'draft',
            'certificate_validity_years' => 0,
            'question_ids' => [$question->id], 'display_orders' => [$question->id => 1],
        ])->assertSessionHasErrors('certificate_validity_years');
        $this->assertDatabaseMissing('exams', ['name' => 'Zero Validity Exam']);

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Huge Validity Exam', 'question_order_mode' => 'static', 'status' => 'draft',
            'certificate_validity_years' => 100,
            'question_ids' => [$question->id], 'display_orders' => [$question->id => 1],
        ])->assertSessionHasErrors('certificate_validity_years');
        $this->assertDatabaseMissing('exams', ['name' => 'Huge Validity Exam']);
    }

    public function test_exam_shuffle_mode_keeps_one_common_question_set_and_each_group_gets_its_own_schedule(): void
    {
        $questions = collect(range(1, 3))->map(fn (int $number) => Question::create([
            'course_id' => $this->subject->id, 'question_text' => "Shuffle question {$number}",
            'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer',
        ]));
        $group = Group::create(['name' => 'Exam Group', 'status' => 'active']);
        $secondGroup = Group::create(['name' => 'Second Exam Group', 'status' => 'active']);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
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
                'proctor_id' => $proctor->id,
                'instructor_id' => $instructor->id,
            ])->assertRedirect();
        }
        $this->assertDatabaseCount('exam_schedules', 2);
        $this->assertDatabaseHas('exam_schedules', ['exam_id' => $exam->id, 'group_id' => $secondGroup->id]);
    }

    public function test_admin_can_publish_an_exam_with_an_inline_group_schedule_in_one_action(): void
    {
        $question = Question::create(['course_id' => $this->subject->id, 'question_text' => 'Inline schedule question', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer']);
        $group = Group::create(['name' => 'Inline Schedule Group', 'status' => 'active']);
        $provider = TrainingProvider::factory()->create();
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Immediate Visibility Exam', 'question_order_mode' => 'static', 'status' => 'published',
            'question_ids' => [$question->id], 'display_orders' => [$question->id => 1],
            'group_id' => $group->id,
            'training_provider_id' => $provider->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'duration_minutes' => 45,
            'proctor_id' => $proctor->id,
            'instructor_id' => $instructor->id,
        ])->assertRedirect();

        $exam = Exam::where('name', 'Immediate Visibility Exam')->firstOrFail();
        $this->assertSame('published', $exam->status->value);
        $this->assertDatabaseCount('exam_schedules', 1);
        $schedule = ExamSchedule::where('exam_id', $exam->id)->firstOrFail();
        $this->assertSame($group->id, $schedule->group_id);
        $this->assertSame($provider->id, $schedule->training_provider_id);
        $this->assertNotNull($schedule->training_class_id);
        $this->assertSame($provider->id, $schedule->trainingClass->training_provider_id);
        $this->assertDatabaseCount('classes', 1);

        // Visible immediately to Proctor/Instructor without the Laravel Scheduler running,
        // shown under the Exam's own name (Exam and Class are the same record).
        $this->actingAs($proctor)->withSession(['auth.session_version' => $proctor->session_version]);
        $this->get(route('proctor.classes'))->assertOk()->assertSee('Immediate Visibility Exam');
        $this->assertDatabaseHas('classes', ['course_id' => $this->subject->id, 'status' => 'planned']);
    }

    public function test_inline_exam_schedule_fields_must_be_all_or_nothing(): void
    {
        $question = Question::create(['course_id' => $this->subject->id, 'question_text' => 'Partial schedule question', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer']);
        $group = Group::create(['name' => 'Partial Group', 'status' => 'active']);

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Partially Scheduled Exam', 'question_order_mode' => 'static', 'status' => 'published',
            'question_ids' => [$question->id], 'display_orders' => [$question->id => 1],
            'group_id' => $group->id,
        ])->assertSessionHasErrors(['start_date', 'end_date', 'duration_minutes']);

        $this->assertDatabaseMissing('exams', ['name' => 'Partially Scheduled Exam']);
    }

    public function test_publishing_an_exam_without_schedule_fields_still_succeeds_for_later_or_multi_group_scheduling(): void
    {
        $question = Question::create(['course_id' => $this->subject->id, 'question_text' => 'No inline schedule question', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer']);

        $this->post(route('admin.courses.exams.store', $this->subject), [
            'name' => 'Publish Without Schedule', 'question_order_mode' => 'static', 'status' => 'published',
            'question_ids' => [$question->id], 'display_orders' => [$question->id => 1],
        ])->assertRedirect();

        $exam = Exam::where('name', 'Publish Without Schedule')->firstOrFail();
        $this->assertSame('published', $exam->status->value);
        $this->assertDatabaseCount('exam_schedules', 0);
        $this->assertDatabaseCount('classes', 0);
    }

    public function test_duplicate_schedule_for_same_exam_group_and_dates_is_rejected(): void
    {
        $exam = Exam::create(['course_id' => $this->subject->id, 'name' => 'No Duplicate Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $group = Group::create(['name' => 'No Duplicate Group', 'status' => 'active']);
        $payload = [
            'exam_id' => $exam->id, 'group_id' => $group->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'), 'end_date' => now()->addDays(6)->format('Y-m-d'),
            'duration_minutes' => 60,
            'proctor_id' => User::factory()->proctor()->create()->id,
            'instructor_id' => User::factory()->instructor()->create()->id,
        ];

        $this->post(route('admin.exam-schedules.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('exam_schedules', 1);
        $this->assertDatabaseCount('classes', 1);

        $this->post(route('admin.exam-schedules.store'), $payload)->assertSessionHasErrors('group_id');
        $this->assertDatabaseCount('exam_schedules', 1);
        $this->assertDatabaseCount('classes', 1);
    }

    public function test_exam_schedule_create_rejects_duplicate_class_id(): void
    {
        $exam = Exam::create(['course_id' => $this->subject->id, 'name' => 'Class ID Duplicate Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $groups = Group::factory()->count(2)->create(['status' => 'active']);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $payload = [
            'exam_id' => $exam->id,
            'class_id' => 'CLASS-UNIQUE-001',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(6)->format('Y-m-d'),
            'duration_minutes' => 60,
            'proctor_id' => $proctor->id,
            'instructor_id' => $instructor->id,
        ];

        $this->post(route('admin.exam-schedules.store'), $payload + ['group_id' => $groups[0]->id])->assertRedirect();
        $this->post(route('admin.exam-schedules.store'), $payload + ['group_id' => $groups[1]->id])
            ->assertSessionHasErrors(['class_id' => 'Class ID has already been used. Please enter a unique Class ID.']);

        $this->assertDatabaseCount('exam_schedules', 1);
        $this->assertDatabaseHas('exam_schedules', ['class_id' => 'CLASS-UNIQUE-001']);
    }

    public function test_exam_schedule_edit_allows_own_class_id_but_rejects_another_schedule_class_id(): void
    {
        $exam = Exam::factory()->published()->create(['course_id' => $this->subject->id]);
        $groups = Group::factory()->count(2)->create(['status' => 'active']);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();

        $first = ExamSchedule::factory()->create(['exam_id' => $exam->id, 'group_id' => $groups[0]->id, 'class_id' => 'CLASS-EDIT-001', 'start_date' => now()->addDays(5), 'end_date' => now()->addDays(6)]);
        $second = ExamSchedule::factory()->create(['exam_id' => $exam->id, 'group_id' => $groups[1]->id, 'class_id' => 'CLASS-EDIT-002', 'start_date' => now()->addDays(7), 'end_date' => now()->addDays(8)]);

        $payload = [
            'exam_id' => $exam->id,
            'group_id' => $groups[0]->id,
            'class_id' => 'CLASS-EDIT-001',
            'start_date' => now()->addDays(9)->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
            'duration_minutes' => 75,
            'proctor_id' => $proctor->id,
            'instructor_id' => $instructor->id,
        ];

        $this->put(route('admin.exam-schedules.update', $first), $payload)->assertRedirect();
        $this->assertSame('CLASS-EDIT-001', $first->fresh()->class_id);

        $this->put(route('admin.exam-schedules.update', $first), [...$payload, 'class_id' => $second->class_id])
            ->assertSessionHasErrors(['class_id' => 'Class ID has already been used. Please enter a unique Class ID.']);
    }

    public function test_exam_schedule_class_id_unique_index_is_enforced(): void
    {
        $exam = Exam::factory()->published()->create(['course_id' => $this->subject->id]);
        $groups = Group::factory()->count(2)->create(['status' => 'active']);

        ExamSchedule::factory()->create(['exam_id' => $exam->id, 'group_id' => $groups[0]->id, 'class_id' => 'CLASS-DB-001']);

        $this->expectException(QueryException::class);
        ExamSchedule::factory()->create(['exam_id' => $exam->id, 'group_id' => $groups[1]->id, 'class_id' => 'CLASS-DB-001']);
    }

    public function test_exam_schedule_accepts_each_allowed_stack_and_blank_stack(): void
    {
        $exam = Exam::factory()->published()->create(['course_id' => $this->subject->id]);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $stacks = [null, 'Surface', 'Subsea', 'Combined Surface and Subsea'];

        foreach ($stacks as $index => $stack) {
            $group = Group::factory()->create(['status' => 'active']);
            $this->post(route('admin.exam-schedules.store'), [
                'exam_id' => $exam->id,
                'group_id' => $group->id,
                'stack_offered' => $stack,
                'supplement_offered' => 'No Supplement Offered',
                'start_date' => now()->addDays(5 + $index)->format('Y-m-d'),
                'end_date' => now()->addDays(6 + $index)->format('Y-m-d'),
                'duration_minutes' => 60,
                'proctor_id' => $proctor->id,
                'instructor_id' => $instructor->id,
            ])->assertRedirect();
        }

        foreach ($stacks as $stack) {
            $this->assertDatabaseHas('exam_schedules', ['stack_offered' => $stack]);
        }
    }

    public function test_exam_schedule_rejects_invalid_stack(): void
    {
        $exam = Exam::factory()->published()->create(['course_id' => $this->subject->id]);

        $this->post(route('admin.exam-schedules.store'), [
            'exam_id' => $exam->id,
            'group_id' => Group::factory()->create(['status' => 'active'])->id,
            'stack_offered' => 'Invalid Stack',
            'supplement_offered' => 'No Supplement Offered',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(6)->format('Y-m-d'),
            'duration_minutes' => 60,
            'proctor_id' => User::factory()->proctor()->create()->id,
            'instructor_id' => User::factory()->instructor()->create()->id,
        ])->assertSessionHasErrors('stack_offered');
    }

    public function test_exam_schedule_accepts_allowed_supplements_and_rejects_invalid_supplement(): void
    {
        $exam = Exam::factory()->published()->create(['course_id' => $this->subject->id]);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();

        foreach (['No Supplement Offered', 'Workover'] as $index => $supplement) {
            $this->post(route('admin.exam-schedules.store'), [
                'exam_id' => $exam->id,
                'group_id' => Group::factory()->create(['status' => 'active'])->id,
                'supplement_offered' => $supplement,
                'start_date' => now()->addDays(5 + $index)->format('Y-m-d'),
                'end_date' => now()->addDays(6 + $index)->format('Y-m-d'),
                'duration_minutes' => 60,
                'proctor_id' => $proctor->id,
                'instructor_id' => $instructor->id,
            ])->assertRedirect();

            $this->assertDatabaseHas('exam_schedules', ['supplement_offered' => $supplement]);
        }

        $this->post(route('admin.exam-schedules.store'), [
            'exam_id' => $exam->id,
            'group_id' => Group::factory()->create(['status' => 'active'])->id,
            'supplement_offered' => 'Invalid Supplement',
            'start_date' => now()->addDays(9)->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
            'duration_minutes' => 60,
            'proctor_id' => $proctor->id,
            'instructor_id' => $instructor->id,
        ])->assertSessionHasErrors('supplement_offered');
    }

    public function test_exam_schedule_edit_persists_offered_values(): void
    {
        $exam = Exam::factory()->published()->create(['course_id' => $this->subject->id]);
        $group = Group::factory()->create(['status' => 'active']);
        $schedule = ExamSchedule::factory()->create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(6),
        ]);

        $this->put(route('admin.exam-schedules.update', $schedule), [
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'stack_offered' => 'Combined Surface and Subsea',
            'supplement_offered' => 'Workover',
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'duration_minutes' => 75,
            'proctor_id' => User::factory()->proctor()->create()->id,
            'instructor_id' => User::factory()->instructor()->create()->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('exam_schedules', [
            'id' => $schedule->id,
            'stack_offered' => 'Combined Surface and Subsea',
            'supplement_offered' => 'Workover',
        ]);
    }

    public function test_same_subject_and_dates_at_different_providers_create_separate_classes(): void
    {
        $exam = Exam::create(['course_id' => $this->subject->id, 'name' => 'Provider Isolation Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $providers = TrainingProvider::factory()->count(2)->create();
        $groups = collect([
            Group::create(['name' => 'Provider A Group', 'status' => 'active']),
            Group::create(['name' => 'Provider B Group', 'status' => 'active']),
        ]);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();

        foreach ($providers as $index => $provider) {
            $this->post(route('admin.exam-schedules.store'), [
                'exam_id' => $exam->id,
                'group_id' => $groups[$index]->id,
                'training_provider_id' => $provider->id,
                'start_date' => now()->addDays(5)->format('Y-m-d'),
                'end_date' => now()->addDays(6)->format('Y-m-d'),
                'duration_minutes' => 60,
                'proctor_id' => $proctor->id,
                'instructor_id' => $instructor->id,
            ])->assertRedirect();
        }

        $this->assertDatabaseCount('classes', 2);
        foreach ($providers as $provider) {
            $this->assertDatabaseHas('classes', ['course_id' => $this->subject->id, 'training_provider_id' => $provider->id]);
        }
    }

    public function test_schedule_auto_resolves_one_location_and_rejects_invalid_multi_provider_locations(): void
    {
        $exam = Exam::factory()->published()->create(['course_id' => $this->subject->id]);
        $groups = Group::factory()->count(3)->create(['status' => 'active']);
        $single = TrainingProvider::factory()->create();
        $multi = TrainingProvider::factory()->create();
        $multiSecond = $multi->locations()->create(['location' => 'Second Site']);
        $other = TrainingProvider::factory()->create();
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $base = [
            'exam_id' => $exam->id,
            'start_date' => now()->addDays(8)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
            'duration_minutes' => 60,
            'proctor_id' => $proctor->id,
            'instructor_id' => $instructor->id,
        ];

        $this->post(route('admin.exam-schedules.store'), $base + [
            'group_id' => $groups[0]->id,
            'training_provider_id' => $single->id,
        ])->assertRedirect();
        $singleSchedule = ExamSchedule::where('group_id', $groups[0]->id)->firstOrFail();
        $this->assertSame($single->locations()->firstOrFail()->id, $singleSchedule->training_provider_location_id);
        $this->assertSame($singleSchedule->training_provider_location_id, $singleSchedule->trainingClass->training_provider_location_id);

        $this->post(route('admin.exam-schedules.store'), $base + [
            'group_id' => $groups[1]->id,
            'training_provider_id' => $multi->id,
        ])->assertSessionHasErrors('training_provider_location_id');

        $this->post(route('admin.exam-schedules.store'), $base + [
            'group_id' => $groups[1]->id,
            'training_provider_id' => $multi->id,
            'training_provider_location_id' => $other->locations()->firstOrFail()->id,
        ])->assertSessionHasErrors('training_provider_location_id');

        $this->post(route('admin.exam-schedules.store'), $base + [
            'group_id' => $groups[1]->id,
            'training_provider_id' => $multi->id,
            'training_provider_location_id' => $multiSecond->id,
        ])->assertRedirect();
    }

    public function test_same_provider_and_dates_at_different_locations_create_separate_classes(): void
    {
        $exam = Exam::factory()->published()->create(['course_id' => $this->subject->id]);
        $provider = TrainingProvider::factory()->create();
        $locations = collect([$provider->locations()->firstOrFail(), $provider->locations()->create(['location' => 'Remote Site'])]);
        $groups = Group::factory()->count(2)->create(['status' => 'active']);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();

        foreach ($locations as $index => $location) {
            $this->post(route('admin.exam-schedules.store'), [
                'exam_id' => $exam->id,
                'group_id' => $groups[$index]->id,
                'training_provider_id' => $provider->id,
                'training_provider_location_id' => $location->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(11)->toDateString(),
                'duration_minutes' => 60,
                'proctor_id' => $proctor->id,
                'instructor_id' => $instructor->id,
            ])->assertRedirect();
        }

        $this->assertDatabaseCount('classes', 2);
        foreach ($locations as $location) {
            $this->assertDatabaseHas('classes', ['training_provider_id' => $provider->id, 'training_provider_location_id' => $location->id]);
        }
    }

    public function test_schedule_edit_preserves_location_and_in_use_location_deactivation_preserves_history(): void
    {
        $exam = Exam::factory()->published()->create(['course_id' => $this->subject->id]);
        $provider = TrainingProvider::factory()->create();
        $location = $provider->locations()->firstOrFail();
        $group = Group::factory()->create(['status' => 'active']);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $payload = [
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'training_provider_id' => $provider->id,
            'training_provider_location_id' => $location->id,
            'start_date' => now()->addDays(12)->toDateString(),
            'end_date' => now()->addDays(13)->toDateString(),
            'duration_minutes' => 60,
            'proctor_id' => $proctor->id,
            'instructor_id' => $instructor->id,
        ];
        $this->post(route('admin.exam-schedules.store'), $payload)->assertRedirect();
        $schedule = ExamSchedule::firstOrFail();

        $this->get(route('admin.exam-schedules.edit', $schedule))
            ->assertOk()
            ->assertSee((string) $location->id);
        $this->put(route('admin.exam-schedules.update', $schedule), [...$payload, 'duration_minutes' => 75])->assertRedirect();
        $this->assertSame($location->id, $schedule->fresh()->training_provider_location_id);

        $otherProvider = TrainingProvider::factory()->create();
        $this->put(route('admin.exam-schedules.update', $schedule), [
            ...$payload,
            'training_provider_id' => $otherProvider->id,
        ])->assertSessionHasErrors('training_provider_location_id');

        $this->put(route('admin.providers.update', $provider), [
            'provider_number' => $provider->provider_number,
            'name' => $provider->name,
            'locations' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('training_provider_locations', ['id' => $location->id, 'is_active' => false]);
        $this->assertSame($location->id, $schedule->fresh()->training_provider_location_id);
        $this->assertSame($location->id, $schedule->trainingClass->fresh()->training_provider_location_id);
    }

    public function test_updating_a_schedule_provider_keeps_its_linked_class_in_sync(): void
    {
        $exam = Exam::create(['course_id' => $this->subject->id, 'name' => 'Provider Update Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $group = Group::create(['name' => 'Provider Update Group', 'status' => 'active']);
        $providers = TrainingProvider::factory()->count(2)->create();
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $payload = [
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'training_provider_id' => $providers[0]->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(6)->format('Y-m-d'),
            'duration_minutes' => 60,
            'proctor_id' => $proctor->id,
            'instructor_id' => $instructor->id,
        ];

        $this->post(route('admin.exam-schedules.store'), $payload)->assertRedirect();
        $schedule = ExamSchedule::query()->firstOrFail();

        $this->put(route('admin.exam-schedules.update', $schedule), [
            ...$payload,
            'training_provider_id' => $providers[1]->id,
        ])->assertRedirect();

        $this->assertSame($providers[1]->id, $schedule->fresh()->training_provider_id);
        $this->assertSame($providers[1]->id, $schedule->fresh()->trainingClass->training_provider_id);
        $this->assertDatabaseCount('classes', 1);
    }

    public function test_exam_schedules_validate_ranges_and_can_be_cancelled_without_deletion(): void
    {
        $exam = Exam::create(['course_id' => $this->subject->id, 'name' => 'Scheduled Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $group = Group::create(['name' => 'Scheduled Group', 'status' => 'active']);
        $proctor = User::factory()->proctor()->create();
        $instructor = User::factory()->instructor()->create();
        $startDate = now()->addDays(2)->format('Y-m-d');
        $endDate = now()->addDays(4)->format('Y-m-d');
        $this->post(route('admin.exam-schedules.store'), [
            'exam_id' => $exam->id, 'group_id' => $group->id,
            'start_date' => $startDate, 'end_date' => $endDate,
            'duration_minutes' => 60,
            'proctor_id' => $proctor->id, 'instructor_id' => $instructor->id,
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
            'proctor_id' => $proctor->id, 'instructor_id' => $instructor->id,
        ])->assertRedirect();
        $this->post(route('admin.exam-schedules.store'), [
            'exam_id' => $exam->id, 'group_id' => $group->id,
            'start_date' => $endDate, 'end_date' => $startDate, 'duration_minutes' => 60,
            'proctor_id' => $proctor->id, 'instructor_id' => $instructor->id,
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

    public function test_admin_management_pages_render_alpine_controls_without_external_alpine_dependency(): void
    {
        $pages = [
            [route('admin.courses.index'), 'archiveSubject(subject)'],
            [route('admin.students.index'), 'archiveStudent(student)'],
            [route('admin.exams.index'), 'archiveExam(exam)'],
            [route('admin.questions.index'), 'archiveQuestion(question)'],
            [route('admin.users.index'), 'archiveUser(user)'],
            [route('admin.providers.index'), 'archiveProvider(provider)'],
            [route('admin.configuration.index'), 'toggle(section, row)'],
        ];

        foreach ($pages as [$url, $action]) {
            $this->get($url)
                ->assertOk()
                ->assertSee($action, false)
                ->assertSee('type="button"', false)
                ->assertDontSee('cdn.jsdelivr.net/npm/alpinejs');
        }
    }
}
