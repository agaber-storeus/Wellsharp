<?php

namespace App\Services;

use App\Actions\Classes\SyncGroupEnrollmentsAction;
use App\Enums\ClassStatus;
use App\Models\ExamSchedule;
use App\Models\TrainingClass;

class ExamClassSynchronizer
{
    public function __construct(private readonly SyncGroupEnrollmentsAction $syncEnrollments) {}

    public function sync(ExamSchedule $schedule): TrainingClass
    {
        $schedule->loadMissing(['exam', 'trainingClass', 'group']);
        $class = $schedule->trainingClass;

        if (! $class) {
            $class = TrainingClass::query()
                ->where('course_id', $schedule->exam->course_id)
                ->whereDate('starts_at', $schedule->start_date)
                ->whereDate('ends_at', $schedule->end_date)
                ->first();
        }

        if (! $class) {
            $class = TrainingClass::create([
                'class_number' => 'EXAM-CLASS-'.$schedule->public_id,
                'course_id' => $schedule->exam->course_id,
                'status' => ClassStatus::Planned,
                'starts_at' => $schedule->start_date?->copy()->startOfDay(),
                'ends_at' => $schedule->end_date?->copy()->endOfDay(),
                'notes' => 'Shared Exam/Class record created automatically from Exam scheduling.',
            ]);
        }

        if ((int) $schedule->training_class_id !== (int) $class->getKey()) {
            $schedule->forceFill(['training_class_id' => $class->getKey()])->saveQuietly();
        }

        if ($schedule->group) {
            $this->syncEnrollments->execute($class, $schedule->group);
        }

        return $class->fresh(['course', 'provider']);
    }
}
