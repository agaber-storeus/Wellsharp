<?php

namespace App\Console\Commands;

use App\Actions\Exams\AutoTransitionDueClassesAction;
use Illuminate\Console\Command;

class ProcessExamSchedules extends Command
{
    protected $signature = 'wellsharp:process-exam-schedules';

    protected $description = 'Automatically start and end scheduled Training Classes';

    public function handle(AutoTransitionDueClassesAction $action): int
    {
        $result = $action->execute();

        $this->info("Automatic class transitions: {$result['started']} started, {$result['ended']} ended.");

        return self::SUCCESS;
    }
}
