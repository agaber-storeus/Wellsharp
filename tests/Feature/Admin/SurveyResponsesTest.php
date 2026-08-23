<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\StudentSurvey;
use App\Models\StudentSurveyAnswer;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyResponsesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_stored_survey_answers_without_student_identity(): void
    {
        $this->seedRoles();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $student->profile()->update(['first_name' => 'Private', 'last_name' => 'Student']);
        $course = Course::factory()->create(['name' => 'Well Control Subject']);
        $trainingClass = TrainingClass::factory()->create(['course_id' => $course->id, 'class_number' => 'SURVEY-CLASS-001']);
        $group = Group::create(['name' => 'Survey Group', 'status' => 'active']);
        $exam = Exam::create([
            'course_id' => $course->id,
            'name' => 'Well Control Final Exam',
            'question_order_mode' => 'static',
            'status' => 'published',
        ]);
        $schedule = ExamSchedule::create([
            'exam_id' => $exam->id,
            'group_id' => $group->id,
            'training_class_id' => $trainingClass->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'duration_minutes' => 90,
            'status' => 'scheduled',
        ]);
        $survey = StudentSurvey::create([
            'student_user_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => 'completed',
            'contact_confirmed_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);
        StudentSurveyAnswer::create([
            'student_survey_id' => $survey->id,
            'question_key' => 'classroom_facilities',
            'answer' => '4: Good',
        ]);
        StudentSurveyAnswer::create([
            'student_survey_id' => $survey->id,
            'question_key' => 'additional_comments',
            'answer' => 'The practical exercises were useful.',
        ]);

        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
            ->assertSee('admin-drawer-backdrop', false)
            ->assertSee('Class lifecycle')
            ->assertSee('Student Survey Answers')
            ->assertSee('Please rate the classroom facilities.')
            ->assertSee('4: Good')
            ->assertSee('The practical exercises were useful.')
            ->assertSee('SURVEY-CLASS-001')
            ->assertDontSee('Private Student');

        $this->assertDatabaseHas('student_survey_answers', [
            'student_survey_id' => $survey->id,
            'question_key' => 'classroom_facilities',
            'answer' => '4: Good',
        ]);
    }
}
