<?php

namespace Database\Factories;

use App\Enums\ExamScheduleStatus;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSchedule>
 */
class ExamScheduleFactory extends Factory
{
    protected $model = ExamSchedule::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory()->published(),
            'group_id' => Group::factory(),
            'training_class_id' => null,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'duration_minutes' => 60,
            'status' => ExamScheduleStatus::Scheduled,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'override_started_at' => null,
            'override_ended_at' => null,
            'override_started_by_user_id' => null,
            'override_ended_by_user_id' => null,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn (): array => [
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
            'status' => ExamScheduleStatus::Scheduled,
        ]);
    }

    public function ongoing(): static
    {
        return $this->state(fn (): array => [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => ExamScheduleStatus::Scheduled,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->subDays(28)->toDateString(),
            'status' => ExamScheduleStatus::Completed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'start_date' => now()->subDays(14)->toDateString(),
            'end_date' => now()->subDays(12)->toDateString(),
            'status' => ExamScheduleStatus::Cancelled,
        ]);
    }
}
