<?php

namespace App\Http\Controllers\Proctor;

use App\Http\Controllers\Controller;
use App\Models\TrainingClass;
use App\Services\OperationalClassMapPointBuilder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(OperationalClassMapPointBuilder $mapPointBuilder): View
    {
        $this->authorize('viewAny', TrainingClass::class);
        $classes = TrainingClass::query()->visibleTo(auth()->user())->with(['course.languages', 'course.level', 'course.stacks', 'course.supplements', 'provider', 'proctor.profile', 'instructor.profile', 'enrollments.student.profile', 'examSchedules.exam', 'examSchedules.attempts.student.profile'])->latest()->get();

        return view('proctor.dashboard', ['classes' => $classes, 'mapPoints' => $mapPointBuilder->build($classes), 'classModalData' => $mapPointBuilder->buildModalData($classes), 'ongoingClasses' => $classes->where('status.value', 'active'), 'upcomingClasses' => $classes->where('status.value', 'planned'), 'pastClasses' => $classes->whereIn('status.value', ['completed', 'cancelled']), 'roleLabel' => 'Proctor']);
    }
}
