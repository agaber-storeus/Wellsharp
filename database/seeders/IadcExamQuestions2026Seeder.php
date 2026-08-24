<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamStatus;
use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IadcExamQuestions2026Seeder extends Seeder
{
    private const DATASET = 'database/data/iadc_exam_questions_2026.json';

    public function run(): void
    {
        $dataset = $this->readDataset();
        $globalQuestionNumber = 0;

        DB::transaction(function () use ($dataset, &$globalQuestionNumber): void {
            foreach ($dataset['subjects'] as $subject) {
                $course = Course::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($subject['name'])])
                    ->first();

                if ($course === null) {
                    $courseWithImportCode = Course::query()->where('code', $subject['code'])->first();

                    if ($courseWithImportCode !== null && mb_strtolower($courseWithImportCode->name) !== mb_strtolower($subject['name'])) {
                        throw new RuntimeException("Course code {$subject['code']} is already assigned to another course.");
                    }

                    $course = Course::query()->firstOrCreate(
                        ['code' => $subject['code']],
                        [
                            'name' => $subject['name'],
                            'description' => 'IADC 2026 question bank imported from '.$subject['source'].'.',
                            'status' => CourseStatus::Active,
                        ],
                    );
                }

                $examCode = 'IADC26-'.($course->code === $subject['code'] ? strtoupper(substr($subject['code'], -4)) : $course->id);
                $examWithImportCode = Exam::query()->where('code', $examCode)->first();

                if ($examWithImportCode !== null && $examWithImportCode->course_id !== $course->id) {
                    throw new RuntimeException("Exam code {$examCode} is already assigned to another subject.");
                }

                $exam = Exam::query()->firstOrCreate(
                    ['code' => $examCode],
                    [
                        'course_id' => $course->id,
                        'name' => 'IADC 2026 '.$subject['name'],
                        'description' => 'Source exam question bank: '.$subject['source'],
                        'passing_score' => 75,
                        'retake_score' => 60,
                        'certificate_validity_years' => 2,
                        'question_order_mode' => ExamQuestionOrderMode::Static,
                        'status' => ExamStatus::Draft,
                    ],
                );

                foreach ($subject['questions'] as $questionData) {
                    $globalQuestionNumber++;
                    $questionCode = 'I'.str_pad((string) $globalQuestionNumber, 4, '0', STR_PAD_LEFT);

                    if (array_key_exists('o', $questionData) && ($questionData['a'] ?? null) === null) {
                        $this->command?->warn("Skipping {$subject['code']} question {$questionData['n']}: answer requires review.");

                        continue;
                    }

                    $questionWithImportCode = Question::query()->where('code', $questionCode)->first();

                    if ($questionWithImportCode !== null && $questionWithImportCode->course_id !== $course->id) {
                        throw new RuntimeException("Question code {$questionCode} is already assigned to another course.");
                    }

                    $isTrueFalse = array_key_exists('tf', $questionData);
                    $question = Question::query()->updateOrCreate(
                        ['code' => $questionCode],
                        [
                            'course_id' => $course->id,
                            'question_text' => $questionData['t'],
                            'question_image_path' => $this->imagePath($questionData['diagram'] ?? null),
                            'type' => $isTrueFalse ? QuestionType::TrueFalse : QuestionType::Mcq,
                            'difficulty' => QuestionDifficulty::Medium,
                            'default_marks' => 1,
                            'correct_answer_text' => $isTrueFalse ? null : $questionData['o'][$questionData['a']],
                            'correct_answer_boolean' => $isTrueFalse ? (bool) $questionData['tf'] : null,
                            'solution_text' => null,
                            'is_active' => true,
                        ],
                    );

                    if (! $isTrueFalse) {
                        foreach ($questionData['o'] as $displayOrder => $optionText) {
                            QuestionOption::query()->updateOrCreate(
                                ['question_id' => $question->id, 'display_order' => $displayOrder],
                                [
                                    'option_text' => $optionText,
                                    'is_correct' => $displayOrder === $questionData['a'],
                                ],
                            );
                        }
                    }

                    ExamQuestion::query()->updateOrCreate(
                        ['exam_id' => $exam->id, 'question_id' => $question->id],
                        ['display_order' => $questionData['n'], 'points' => 1],
                    );
                }
            }
        });
    }

    /**
     * @return array{version: string, subjects: array<int, array<string, mixed>>}
     */
    public static function dataset(): array
    {
        $path = base_path(self::DATASET);
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read IADC dataset: {$path}");
        }

        $dataset = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($dataset) || ! isset($dataset['subjects']) || ! is_array($dataset['subjects'])) {
            throw new RuntimeException('IADC dataset must contain a subjects array.');
        }

        return $dataset;
    }

    private function readDataset(): array
    {
        return self::dataset();
    }

    private function imagePath(?string $diagram): ?string
    {
        return match ($diagram) {
            'ogor-page-04' => 'questions/iadc/2026/ogor-page-04.png',
            'ogor-page-10' => 'questions/iadc/2026/ogor-page-10.png',
            'workover-page-06' => 'questions/iadc/2026/workover-page-06.jpg',
            default => null,
        };
    }
}
