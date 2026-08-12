<?php

namespace App\Actions\Exams;

use App\Enums\ClassStatus;
use App\Enums\ExamScheduleStatus;
use App\Models\ExamSchedule;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

class CancelExamScheduleAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(ExamSchedule $schedule): ExamSchedule
    {
        return DB::transaction(function () use ($schedule): ExamSchedule {
            $schedule = ExamSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
            $schedule->update(['status' => ExamScheduleStatus::Cancelled, 'updated_by_user_id' => auth()->id()]);
            $schedule->trainingClass?->update(['status' => ClassStatus::Cancelled]);
            $this->audit->record('exam_schedule.cancelled', $schedule, ['status' => ExamScheduleStatus::Scheduled->value], ['status' => ExamScheduleStatus::Cancelled->value]);

            return $schedule;
        });
    }
}
