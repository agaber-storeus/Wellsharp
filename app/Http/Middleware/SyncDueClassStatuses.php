<?php

namespace App\Http\Middleware;

use App\Actions\Exams\AutoTransitionDueClassesAction;
use Closure;
use Illuminate\Http\Request;

/**
 * Runs the same due-Class sweep as `wellsharp:process-exam-schedules` on
 * every Proctor/Instructor request, so a Class's status reflects its own
 * starts_at/ends_at even in an environment where the scheduled command isn't
 * actually running (no cron/`schedule:work` configured). Idempotent and
 * row-locked (see AutoTransitionDueClassesAction), so running it opportunistically
 * on top of the real cron is safe - it just finds nothing left to do.
 */
class SyncDueClassStatuses
{
    public function __construct(private readonly AutoTransitionDueClassesAction $action) {}

    public function handle(Request $request, Closure $next)
    {
        $this->action->execute();

        return $next($request);
    }
}
