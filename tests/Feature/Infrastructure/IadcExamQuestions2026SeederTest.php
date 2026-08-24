<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Exam;
use App\Models\Question;
use Database\Seeders\IadcExamQuestions2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IadcExamQuestions2026SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dataset_has_the_two_source_counts_and_only_one_explicit_review_record(): void
    {
        $dataset = IadcExamQuestions2026Seeder::dataset();

        $this->assertSame(['Well Servicing Oil and Gas Operator Representative', 'Well Servicing Workover'], array_column($dataset['subjects'], 'name'));
        $this->assertSame([83, 50], array_map(static fn (array $subject): int => count($subject['questions']), $dataset['subjects']));

        $reviewCount = 0;
        foreach ($dataset['subjects'] as $subject) {
            foreach ($subject['questions'] as $question) {
                if (array_key_exists('o', $question) && ($question['a'] ?? null) === null) {
                    $reviewCount++;

                    continue;
                }

                if (array_key_exists('o', $question)) {
                    self::assertNotEmpty($question['o']);
                    self::assertLessThan(count($question['o']), $question['a']);
                } else {
                    self::assertArrayHasKey('tf', $question);
                }
            }
        }

        $this->assertSame(1, $reviewCount);
    }

    public function test_seeder_imports_answers_images_and_is_idempotent(): void
    {
        $this->seed(IadcExamQuestions2026Seeder::class);

        $this->assertSame(2, (int) DB::table('courses')->whereIn('name', [
            'Well Servicing Oil and Gas Operator Representative',
            'Well Servicing Workover',
        ])->count());
        $this->assertSame(2, Exam::query()->where('code', 'like', 'IADC26-%')->count());
        $this->assertSame(132, Question::query()->where('code', 'like', 'I%')->count());
        $this->assertDatabaseMissing('questions', ['code' => 'I0027']);
        $this->assertDatabaseHas('questions', ['code' => 'I0084', 'type' => 'mcq']);
        $this->assertDatabaseHas('questions', ['code' => 'I0078', 'question_image_path' => 'questions/iadc/2026/ogor-page-10.png']);
        $this->assertDatabaseHas('questions', ['code' => 'I0105', 'question_image_path' => 'questions/iadc/2026/workover-page-06.jpg']);
        $this->assertDatabaseHas('questions', ['code' => 'I0108', 'question_image_path' => 'questions/iadc/2026/workover-page-06.jpg']);
        $this->assertSame('/storage/questions/iadc/2026/workover-page-06.jpg', Storage::disk('public')->url('questions/iadc/2026/workover-page-06.jpg'));

        $questionCount = (int) DB::table('questions')->count();
        $optionCount = (int) DB::table('question_options')->count();
        $examQuestionCount = (int) DB::table('exam_questions')->count();

        $this->seed(IadcExamQuestions2026Seeder::class);

        $this->assertSame($questionCount, (int) DB::table('questions')->count());
        $this->assertSame($optionCount, (int) DB::table('question_options')->count());
        $this->assertSame($examQuestionCount, (int) DB::table('exam_questions')->count());
        $this->assertSame(1, (int) DB::table('questions')->where('code', 'I0001')->count());
        $this->assertSame(1, (int) DB::table('courses')->where('name', 'Well Servicing Workover')->count());
    }
}
