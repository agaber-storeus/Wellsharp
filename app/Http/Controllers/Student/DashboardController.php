<?php

namespace App\Http\Controllers\Student;

use App\Enums\ExamScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\TrainingClass;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', TrainingClass::class);
        $latestSchedule = ExamSchedule::query()
            ->with(['exam.subject', 'group', 'trainingClass'])
            ->where('status', ExamScheduleStatus::Scheduled->value)
            ->where(function ($query): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', today())
                    ->orWhereNotNull('override_started_at');
            })
            ->whereHas('group.students', fn ($query) => $query->where('users.id', auth()->id()))
            ->orderByDesc('start_date')
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->first();

        return view('student.dashboard', [
            'user' => auth()->user()->load('profile'),
            'studentFlowSchedule' => $latestSchedule,
            'flowStep' => 'confirm',
        ]);
    }
}
