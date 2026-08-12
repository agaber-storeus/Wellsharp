<?php

namespace App\Console\Commands;

use App\Actions\Exams\ControlOperationalExamAction;
use App\Models\TrainingClass;
use Illuminate\Console\Command;

class ProcessExamSchedules extends Command
{
    protected $signature = 'wellsharp:process-exam-schedules';

    protected $description = 'Automatically start and end scheduled Training Classes';

    public function handle(ControlOperationalExamAction $control): int
    {
        $started = 0;
        $ended = 0;
        $now = now();

        TrainingClass::query()
            ->where('status', 'planned')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($classes) use ($control, $now, &$started): void {
                foreach ($classes as $class) {
                    if ($control->executeAutomatic($class, 'start', $now)['changed']) {
                        $started++;
                    }
                }
            });

        TrainingClass::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($classes) use ($control, $now, &$ended): void {
                foreach ($classes as $class) {
                    if ($control->executeAutomatic($class, 'end', $now)['changed']) {
                        $ended++;
                    }
                }
            });

        $this->info("Automatic class transitions: {$started} started, {$ended} ended.");

        return self::SUCCESS;
    }
}
