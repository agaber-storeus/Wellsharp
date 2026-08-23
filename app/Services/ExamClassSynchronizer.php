<?php

namespace App\Services;

use App\Actions\Classes\SyncGroupEnrollmentsAction;
use App\Enums\ClassStatus;
use App\Models\ExamSchedule;
use App\Models\TrainingClass;

class ExamClassSynchronizer
{
    public function __construct(private readonly SyncGroupEnrollmentsAction $syncEnrollments) {}

    /** @param array{proctor_id?: int|null, instructor_id?: int|null} $staff */
    public function sync(ExamSchedule $schedule, array $staff = []): TrainingClass
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
                'proctor_id' => $staff['proctor_id'] ?? null,
                'instructor_id' => $staff['instructor_id'] ?? null,
                'notes' => 'Shared Exam/Class record created automatically from Exam scheduling.',
            ]);
        } else {
            // Reusing an existing Class (matched by course+dates, or already linked to
            // this schedule): never overwrite an existing assignment - only backfill a
            // legacy/unassigned Class, mirroring how course_id/dates are left alone on
            // reuse. An Admin who wants to change an already-assigned Class's staff
            // does so explicitly via the Classes edit screen, not by re-saving a schedule.
            $backfill = array_filter([
                'proctor_id' => $class->proctor_id === null ? ($staff['proctor_id'] ?? null) : null,
                'instructor_id' => $class->instructor_id === null ? ($staff['instructor_id'] ?? null) : null,
            ]);
            if ($backfill !== []) {
                $class->forceFill($backfill)->save();
            }
        }

        if ((int) $schedule->training_class_id !== (int) $class->getKey()) {
            $schedule->forceFill(['training_class_id' => $class->getKey()])->saveQuietly();
        }

        if ($schedule->group) {
            $this->syncEnrollments->execute($class, $schedule->group);
        }

        return $class->fresh(['course', 'provider', 'proctor', 'instructor']);
    }
}
