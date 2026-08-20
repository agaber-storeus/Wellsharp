<?php

namespace App\Console\Commands;

use App\Actions\Classes\SyncGroupEnrollmentsAction;
use App\Models\ExamSchedule;
use Illuminate\Console\Command;

class SyncClassEnrollments extends Command
{
    protected $signature = 'wellsharp:sync-class-enrollments';

    protected $description = 'Backfill missing Class roster Enrollments for existing Exam/Group schedules (idempotent, safe to re-run)';

    public function handle(SyncGroupEnrollmentsAction $sync): int
    {
        $processed = 0;

        ExamSchedule::query()
            ->whereNotNull('training_class_id')
            ->whereNotNull('group_id')
            ->with(['trainingClass', 'group'])
            ->orderBy('id')
            ->chunkById(100, function ($schedules) use ($sync, &$processed): void {
                foreach ($schedules as $schedule) {
                    if ($schedule->trainingClass && $schedule->group) {
                        $sync->execute($schedule->trainingClass, $schedule->group);
                        $processed++;
                    }
                }
            });

        $this->info("Reconciled roster Enrollments for {$processed} Exam Schedule(s).");

        return self::SUCCESS;
    }
}
