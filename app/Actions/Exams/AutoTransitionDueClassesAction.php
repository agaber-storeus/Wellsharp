<?php

namespace App\Actions\Exams;

use App\Models\TrainingClass;

/**
 * Flips every due Class between planned/active/completed based on its own
 * starts_at/ends_at, via the same ControlOperationalExamAction::executeAutomatic()
 * path the `wellsharp:process-exam-schedules` scheduled command uses. Extracted
 * so both the command and the operational-request middleware share one
 * implementation instead of duplicating the sweep: the command covers
 * production (real cron), the middleware covers any environment where that
 * cron/scheduler isn't actually running, so a Class's displayed status never
 * depends on whether someone remembered to start `schedule:work`.
 */
class AutoTransitionDueClassesAction
{
    public function __construct(private readonly ControlOperationalExamAction $control) {}

    /** @return array{started: int, ended: int} */
    public function execute(): array
    {
        $started = 0;
        $ended = 0;
        $now = now();

        TrainingClass::query()
            ->where('status', 'planned')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($classes) use ($now, &$started): void {
                foreach ($classes as $class) {
                    if ($this->control->executeAutomatic($class, 'start', $now)['changed']) {
                        $started++;
                    }
                }
            });

        TrainingClass::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($classes) use ($now, &$ended): void {
                foreach ($classes as $class) {
                    if ($this->control->executeAutomatic($class, 'end', $now)['changed']) {
                        $ended++;
                    }
                }
            });

        return ['started' => $started, 'ended' => $ended];
    }
}
