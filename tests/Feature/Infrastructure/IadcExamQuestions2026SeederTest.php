<?php

namespace Tests\Feature\Infrastructure;

use Database\Seeders\IadcExamQuestions2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IadcExamQuestions2026SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dataset_contains_all_subject_csv_files(): void
    {
        $dataset = IadcExamQuestions2026Seeder::dataset();

        $this->assertCount(11, $dataset['subjects']);
        $this->assertSame([916, 111, 152, 90, 120, 102, 138, 6, 8, 87, 56], array_map(static fn (array $subject): int => count($subject['questions']), $dataset['subjects']));
    }

    public function test_seeder_imports_unique_questions_under_the_correct_subject_and_is_idempotent(): void
    {
        $this->seed(IadcExamQuestions2026Seeder::class);

        $this->assertSame(11, (int) DB::table('courses')->count());
        $this->assertSame(11, (int) DB::table('exams')->count());
        $this->assertSame(1387, (int) DB::table('questions')->count());
        $this->assertSame(0, (int) DB::table('question_options')->count());
        $this->assertSame(1387, (int) DB::table('exam_questions')->count());

        $expected = [
            'drilling-operation-driller-surface' => 662,
            'drilling-operation-supervisor-surface' => 110,
            'well-servicing-coiled-tubing' => 112,
            'drilling-operation-introductory' => 79,
            'well-servicing-wireline' => 117,
            'well-servicing-workover' => 85,
            'well-servicing-ogor' => 132,
            'driller-kill-sheet' => 6,
            'supervisor-kill-sheet' => 8,
            'instructor-drilling-operations-supervisor-surface' => 25,
            'instructor-well-servicing-workover' => 51,
        ];
        foreach ($expected as $code => $count) {
            $courseId = DB::table('courses')->where('code', $code)->value('id');
            $this->assertSame($count, (int) DB::table('questions')->where('course_id', $courseId)->count(), $code);
        }

        $questionCount = (int) DB::table('questions')->count();
        $examQuestionCount = (int) DB::table('exam_questions')->count();
        $this->seed(IadcExamQuestions2026Seeder::class);
        $this->assertSame($questionCount, (int) DB::table('questions')->count());
        $this->assertSame($examQuestionCount, (int) DB::table('exam_questions')->count());
        $this->assertSame(11, (int) DB::table('courses')->count());
    }
}
