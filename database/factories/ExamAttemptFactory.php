<?php

namespace Database\Factories;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    /**
     * `exam_id` derives from the created schedule (not an independent
     * Exam::factory()) so the attempt stays internally consistent - a
     * schedule's exam_id must match the attempt's exam_id.
     */
    public function definition(): array
    {
        return [
            'exam_schedule_id' => ExamSchedule::factory(),
            'exam_id' => fn (array $attributes): int => ExamSchedule::query()->whereKey($attributes['exam_schedule_id'])->value('exam_id'),
            'student_user_id' => User::factory()->student(),
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::InProgress,
            'started_at' => now(),
            'expires_at' => now()->addHour(),
            'submitted_at' => null,
            'score' => null,
            'passed' => null,
            'scored_at' => null,
            'released_at' => null,
            'released_by_user_id' => null,
        ];
    }

    public function submitted(bool $passed = true, float $score = 85.0): static
    {
        return $this->state(fn (): array => [
            'status' => ExamAttemptStatus::Submitted,
            'submitted_at' => now(),
            'score' => $score,
            'passed' => $passed,
            'scored_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => ExamAttemptStatus::Expired,
            'started_at' => now()->subHours(4),
            'expires_at' => now()->subHours(2),
        ]);
    }
}
