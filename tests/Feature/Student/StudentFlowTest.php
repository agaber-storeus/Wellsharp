<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Language;
use App\Models\Question;
use App\Models\Stack;
use App\Models\StudentSurvey;
use App\Models\TrainingClass;
use App\Models\User;
use App\Services\StudentSurveyDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_class_requires_and_persists_the_complete_student_flow(): void
    {
        $this->seedRoles();
        $student = User::factory()->student()->create(['email' => 'student-flow@example.test']);
        $student->profile()->update([
            'birthday' => '1990-07-26',
            'address' => '123 Training Street',
            'country' => 'Egypt',
            'city' => 'Cairo',
            'postal_code' => '11511',
            'company' => 'WellSharp Energy',
            'position' => 'Driller',
            'company_contact' => '+20 100 000 0000',
            'employee_id' => 'EMP-001',
        ]);
        $level = CourseLevel::firstOrCreate(['slug' => 'supervisor'], ['name' => 'Supervisor', 'sort_order' => 1, 'is_active' => true]);
        $stack = Stack::firstOrCreate(['slug' => 'surface'], ['name' => 'Surface', 'sort_order' => 1, 'is_active' => true]);
        $language = Language::firstOrCreate(['slug' => 'english'], ['name' => 'English', 'sort_order' => 1, 'is_active' => true]);
        $course = Course::factory()->create(['name' => 'Drilling Operations', 'course_level_id' => $level->id]);
        $course->stacks()->attach($stack);
        $course->languages()->attach($language);
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id]);
        $group = Group::create(['name' => 'Flow Group', 'status' => 'active']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $student->id, 'status' => 'active', 'joined_at' => now()]);
        $exam = Exam::create([
            'course_id' => $course->id,
            'name' => 'Drilling Operations Final',
            'passing_score' => 75,
            'retake_score' => 60,
            'question_order_mode' => 'static',
            'status' => 'published',
        ]);
        $question = Question::create(['course_id' => $course->id, 'question_text' => 'Flow question', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer']);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'display_order' => 1, 'points' => 1]);
        $schedule = ExamSchedule::create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'training_class_id' => $trainingClass->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'duration_minutes' => 210,
            'status' => 'scheduled',
        ]);

        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version]);
        $this->get(route('student.confirm', $schedule))
            ->assertOk()
            ->assertSee('1990')
            ->assertSee('123 Training Street')
            ->assertSee('WellSharp Energy')
            ->assertSee('EMP-001')
            ->assertDontSee('Class and Exam');
        $this->post(route('student.exams.start', $schedule))->assertStatus(422);

        $this->post(route('student.confirm.store', $schedule))->assertRedirect(route('student.survey.start', $schedule));
        $this->get(route('student.survey.form', $schedule))->assertOk()->assertSee('What is your current job position?');

        $answers = [];
        foreach (app(StudentSurveyDefinition::class)->questions() as $questionDefinition) {
            $answers[$questionDefinition['key']] = $questionDefinition['type'] === 'select' ? $questionDefinition['options'][0] : 'Instructor One';
        }
        $this->post(route('student.survey.store', $schedule), ['answers' => $answers])
            ->assertRedirect(route('student.proctor', $schedule));
        $this->assertDatabaseHas('student_surveys', ['student_user_id' => $student->id, 'exam_schedule_id' => $schedule->id, 'status' => 'completed']);
        $this->assertSame(count($answers), StudentSurvey::firstOrFail()->answers()->count());

        $this->get(route('student.proctor', $schedule))
            ->assertOk()
            ->assertSee('Drilling Operations, Supervisor, Surface')
            ->assertSee('75% or Greater')
            ->assertSee('60% or Greater')
            ->assertSee('3 Hours 30 Minutes')
            ->assertSee('English')
            ->assertSee('Start Exam')
            ->assertDontSee("Enter Proctor's ID", false)
            ->assertDontSee('Launch Assessment');
        $this->post(route('student.exams.start', $schedule))->assertRedirect(route('student.attempts.show', $schedule->attempts()->firstOrFail()));

        $attempt = $schedule->attempts()->firstOrFail();
        $this->get(route('student.attempts.show', $attempt))
            ->assertOk()
            ->assertSee('Flow question')
            ->assertSee('x-data="studentExam', false);
        $attemptQuestion = $attempt->attemptQuestions()->firstOrFail();
        $this->patchJson(route('student.attempts.answers.update', [$attempt, $attemptQuestion]), ['answer' => 'Student answer'])
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('answered', true);
        $this->assertDatabaseHas('exam_attempt_questions', ['id' => $attemptQuestion->id, 'answer' => 'Student answer']);
    }

    public function test_student_dashboard_uses_the_latest_assigned_class_for_continue(): void
    {
        $this->seedRoles();
        $student = User::factory()->student()->create(['email' => 'latest-class@example.test']);
        $student->profile()->update([
            'birthday' => '1992-03-14',
            'phone' => '+20 100 123 4567',
            'address' => 'Latest Class Training Center',
            'country' => 'Egypt',
            'city' => 'Cairo',
            'postal_code' => '11511',
            'company' => 'WellSharp Energy',
            'position' => 'Driller',
            'company_contact' => '+20 100 000 0000',
            'employee_id' => 'EMP-LATEST-001',
        ]);

        $group = Group::create(['name' => 'Latest Class Group', 'status' => 'active']);
        GroupMembership::create([
            'group_id' => $group->id,
            'student_user_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $olderCourse = Course::factory()->create(['name' => 'Older Subject']);
        $latestCourse = Course::factory()->create(['name' => 'Latest Subject']);
        $olderClass = TrainingClass::factory()->create(['course_id' => $olderCourse->id, 'class_number' => 'OLDER-CLASS']);
        $latestClass = TrainingClass::factory()->create(['course_id' => $latestCourse->id, 'class_number' => 'LATEST-CLASS']);
        $olderExam = Exam::create([
            'course_id' => $olderCourse->id,
            'name' => 'Older Exam',
            'question_order_mode' => 'static',
            'status' => 'published',
        ]);
        $latestExam = Exam::create([
            'course_id' => $latestCourse->id,
            'name' => 'Latest Exam',
            'question_order_mode' => 'static',
            'status' => 'published',
        ]);
        $olderSchedule = ExamSchedule::create([
            'exam_id' => $olderExam->id,
            'group_id' => $group->id,
            'training_class_id' => $olderClass->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);
        $latestSchedule = ExamSchedule::create([
            'exam_id' => $latestExam->id,
            'group_id' => $group->id,
            'training_class_id' => $latestClass->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'duration_minutes' => 90,
            'status' => 'scheduled',
        ]);

        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version]);
        $response = $this->get(route('student.dashboard'));

        $response->assertOk()
            ->assertSee('Welcome to your IADC WellSharp', false)
            ->assertSee('Review Your Contact Information')
            ->assertSee('March 14, 1992')
            ->assertSee('Latest Class Training Center')
            ->assertSee('EMP-LATEST-001')
            ->assertSee(route('student.confirm.store', $latestSchedule), false)
            ->assertDontSee(route('student.confirm.store', $olderSchedule), false)
            ->assertDontSee('student-schedule-panel')
            ->assertDontSee('Select the class you want to continue');

        $this->post(route('student.confirm.store', $latestSchedule))
            ->assertRedirect(route('student.survey.start', $latestSchedule));
        $this->assertDatabaseHas('student_surveys', [
            'student_user_id' => $student->id,
            'exam_schedule_id' => $latestSchedule->id,
        ]);
        $this->assertNotNull(StudentSurvey::query()
            ->where('student_user_id', $student->id)
            ->where('exam_schedule_id', $latestSchedule->id)
            ->firstOrFail()
            ->contact_confirmed_at);
    }

    public function test_student_cannot_continue_a_cancelled_or_ended_schedule(): void
    {
        $this->seedRoles();
        $student = User::factory()->student()->create();
        $course = Course::factory()->create();
        $group = Group::create(['name' => 'Unavailable Group', 'status' => 'active']);
        GroupMembership::create(['group_id' => $group->id, 'student_user_id' => $student->id, 'status' => 'active', 'joined_at' => now()]);
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Unavailable Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $schedule = ExamSchedule::create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'duration_minutes' => 60,
            'status' => 'cancelled',
        ]);

        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])
            ->get(route('student.confirm', $schedule))
            ->assertStatus(422);

        $schedule->update(['status' => 'scheduled', 'end_date' => now()->subDay()->toDateString()]);
        StudentSurvey::create([
            'student_user_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => 'completed',
            'contact_confirmed_at' => now(),
            'completed_at' => now(),
        ]);

        $this->get(route('student.proctor', $schedule))->assertStatus(422);
        $this->get(route('student.confirm', $schedule))->assertStatus(422);
        $this->get(route('student.dashboard'))->assertDontSee('Unavailable Exam');
    }
}
