<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Question;
use App\Models\StudentSurvey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentExamOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $studentOne;

    private User $studentTwo;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->studentOne = User::factory()->student()->create();
        $this->studentTwo = User::factory()->student()->create();
        $this->group = Group::create(['name' => 'Exam Group', 'status' => 'active']);

        foreach ([$this->studentOne, $this->studentTwo] as $student) {
            GroupMembership::create([
                'group_id' => $this->group->id,
                'student_user_id' => $student->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }
    }

    public function test_static_exam_uses_the_same_saved_order_for_every_student_and_refresh(): void
    {
        [$exam, $questions] = $this->makeExam('static');
        $schedule = $this->makeSchedule($exam);

        $this->startAs($this->studentOne, $schedule);
        $firstAttempt = ExamAttempt::firstOrFail();
        $firstOrder = $firstAttempt->attemptQuestions()->pluck('question_id')->all();

        $this->startAs($this->studentTwo, $schedule);
        $secondAttempt = ExamAttempt::query()->where('student_user_id', $this->studentTwo->id)->firstOrFail();
        $secondOrder = $secondAttempt->attemptQuestions()->pluck('question_id')->all();

        $expected = $questions->pluck('id')->all();
        $this->assertSame($expected, $firstOrder);
        $this->assertSame($expected, $secondOrder);

        $this->startAs($this->studentOne, $schedule);
        $this->assertDatabaseCount('exam_attempts', 2);
        $this->assertSame($firstOrder, $firstAttempt->fresh()->attemptQuestions()->pluck('question_id')->all());
    }

    public function test_shuffle_exam_creates_a_different_saved_order_per_student_and_reuses_it(): void
    {
        [$exam, $questions] = $this->makeExam('shuffle');
        $schedule = $this->makeSchedule($exam);

        $this->startAs($this->studentOne, $schedule);
        $firstAttempt = ExamAttempt::query()->where('student_user_id', $this->studentOne->id)->firstOrFail();
        $firstOrder = $firstAttempt->attemptQuestions()->pluck('question_id')->all();

        $this->startAs($this->studentTwo, $schedule);
        $secondAttempt = ExamAttempt::query()->where('student_user_id', $this->studentTwo->id)->firstOrFail();
        $secondOrder = $secondAttempt->attemptQuestions()->pluck('question_id')->all();

        $this->assertSame($questions->pluck('id')->sort()->values()->all(), collect($firstOrder)->sort()->values()->all());
        $this->assertSame($questions->pluck('id')->sort()->values()->all(), collect($secondOrder)->sort()->values()->all());
        $this->assertNotSame($firstOrder, $secondOrder);

        $this->startAs($this->studentOne, $schedule);
        $this->assertDatabaseCount('exam_attempts', 2);
        $this->assertSame($firstOrder, $firstAttempt->fresh()->attemptQuestions()->pluck('question_id')->all());
    }

    public function test_student_cannot_start_an_exam_for_another_group(): void
    {
        [$exam] = $this->makeExam('shuffle');
        $schedule = $this->makeSchedule($exam);
        $outsider = User::factory()->student()->create();

        $this->actingAs($outsider)->withSession(['auth.session_version' => $outsider->session_version])
            ->post(route('student.exams.start', $schedule))
            ->assertForbidden();
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_student_duration_starts_when_the_attempt_starts_and_is_not_capped_by_availability_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-09 23:30:00'));
        try {
            [$exam] = $this->makeExam('static');
            $schedule = ExamSchedule::create([
                'exam_id' => $exam->id,
                'group_id' => $this->group->id,
                'start_date' => '2026-08-09',
                'end_date' => '2026-08-09',
                'duration_minutes' => 90,
                'status' => 'scheduled',
            ]);

            $this->startAs($this->studentOne, $schedule);

            $attempt = ExamAttempt::firstOrFail();
            $this->assertTrue($attempt->expires_at->equalTo(Carbon::parse('2026-08-10 01:00:00')));
        } finally {
            Carbon::setTestNow();
        }
    }

    private function makeExam(string $mode): array
    {
        $exam = Exam::create([
            'course_id' => ($course = Course::factory()->create())->id,
            'name' => ucfirst($mode).' Exam',
            'question_order_mode' => $mode,
            'status' => 'published',
        ]);
        $questions = collect(range(1, 8))->map(fn (int $number): Question => Question::create([
            'course_id' => $course->id,
            'question_text' => "Question {$number}",
            'type' => 'input',
            'difficulty' => 'easy',
            'correct_answer_text' => 'Answer',
        ]));
        foreach ($questions as $index => $question) {
            ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $question->id, 'display_order' => $index + 1]);
        }

        return [$exam, $questions];
    }

    private function makeSchedule(Exam $exam): ExamSchedule
    {
        return ExamSchedule::create([
            'exam_id' => $exam->id,
            'group_id' => $this->group->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'duration_minutes' => 90,
            'status' => 'scheduled',
        ]);
    }

    private function startAs(User $student, ExamSchedule $schedule): void
    {
        StudentSurvey::updateOrCreate(
            ['student_user_id' => $student->id, 'exam_schedule_id' => $schedule->id],
            ['status' => 'completed', 'contact_confirmed_at' => now(), 'completed_at' => now()],
        );

        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])
            ->post(route('student.exams.start', $schedule))
            ->assertRedirect();
    }
}
