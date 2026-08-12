<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Exams\CancelExamScheduleAction;
use App\Actions\Exams\SaveExamScheduleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamScheduleRequest;
use App\Http\Requests\Admin\UpdateExamScheduleRequest;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamScheduleController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExamSchedule::class);
        $query = $this->filteredQuery($request);
        $sort = (string) $request->input('sort', 'start_date');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['exam', 'subject', 'group', 'start_date', 'end_date', 'duration_minutes', 'status'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'start_date';

        if ($sort === 'exam') {
            $query->join('exams as schedule_exam_sort', 'schedule_exam_sort.id', '=', 'exam_schedules.exam_id')->select('exam_schedules.*')->orderBy('schedule_exam_sort.name', $direction);
        } elseif ($sort === 'subject') {
            $query->join('exams as schedule_subject_exam_sort', 'schedule_subject_exam_sort.id', '=', 'exam_schedules.exam_id')->join('courses as schedule_subject_sort', 'schedule_subject_sort.id', '=', 'schedule_subject_exam_sort.course_id')->select('exam_schedules.*')->orderBy('schedule_subject_sort.name', $direction);
        } elseif ($sort === 'group') {
            $query->leftJoin('student_groups as schedule_group_sort', 'schedule_group_sort.id', '=', 'exam_schedules.group_id')->select('exam_schedules.*')->orderBy('schedule_group_sort.name', $direction);
        } else {
            $query->orderBy('exam_schedules.'.$sort, $direction);
        }

        $schedules = $query->paginate(25, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => $schedules->getCollection()->map(fn (ExamSchedule $schedule): array => $this->schedulePayload($schedule))->values(),
            'meta' => [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'total' => $schedules->total(),
                'from' => $schedules->firstItem(),
                'to' => $schedules->lastItem(),
            ],
        ]);
    }

    public function index(?Exam $exam = null): View
    {
        $this->authorize('viewAny', ExamSchedule::class);
        $search = trim((string) request('search'));
        $examId = request('exam_id');
        $groupId = request('group_id');
        $status = request('status');
        $subjectId = request('course_id');
        $examId = $examId ?: $exam?->getKey();
        $schedules = $this->filteredQuery(request()->merge(['exam_id' => $examId]))->latest('start_date')->paginate(25)->withQueryString();
        $exams = Exam::query()->with('subject')->where('status', 'published')->orderBy('name')->get();
        $groups = Group::query()->where('status', 'active')->orderBy('name')->get();
        $subjects = Course::query()->where('status', 'active')->orderBy('name')->get();

        $initialSchedules = $schedules->getCollection()->map(fn (ExamSchedule $schedule): array => $this->schedulePayload($schedule))->values();
        $initialMeta = ['current_page' => $schedules->currentPage(), 'last_page' => $schedules->lastPage(), 'total' => $schedules->total(), 'from' => $schedules->firstItem(), 'to' => $schedules->lastItem()];

        return view('admin.exam-schedules.index', compact('schedules', 'initialSchedules', 'initialMeta', 'exams', 'groups', 'subjects', 'search', 'examId', 'groupId', 'status', 'subjectId'));
    }

    public function create(?Exam $exam = null): View
    {
        $this->authorize('create', ExamSchedule::class);
        $exams = Exam::query()->with(['subject'])->withCount('questions')->where('status', 'published')->orderBy('name')->get();
        $groups = Group::query()->where('status', 'active')->orderBy('name')->get();

        return view('admin.exam-schedules.create', ['exams' => $exams, 'groups' => $groups, 'selectedExamId' => request('exam_id') ?: $exam?->id, 'schedule' => new ExamSchedule]);
    }

    public function store(StoreExamScheduleRequest $request, SaveExamScheduleAction $action): RedirectResponse
    {
        $this->authorize('create', ExamSchedule::class);
        $action->execute(null, $request->validated());

        return redirect()->route('admin.exam-schedules.index')->with('status', 'Schedule created.');
    }

    public function edit(ExamSchedule $schedule): View
    {
        $this->authorize('update', $schedule);
        $exams = Exam::query()->with('subject')->withCount('questions')->where('status', 'published')->orderBy('name')->get();
        $groups = Group::query()->where('status', 'active')->orderBy('name')->get();

        return view('admin.exam-schedules.edit', ['exams' => $exams, 'groups' => $groups, 'schedule' => $schedule->load('exam.subject')]);
    }

    public function update(UpdateExamScheduleRequest $request, ExamSchedule $schedule, SaveExamScheduleAction $action): RedirectResponse
    {
        $this->authorize('update', $schedule);
        $action->execute($schedule, $request->validated());

        return redirect()->route('admin.exam-schedules.index')->with('status', 'Schedule updated.');
    }

    public function cancel(ExamSchedule $schedule, CancelExamScheduleAction $action): RedirectResponse
    {
        $this->authorize('delete', $schedule);
        $action->execute($schedule);

        return back()->with('status', 'Schedule cancelled.');
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $examId = $request->input('exam_id');
        $groupId = $request->input('group_id');
        $status = $request->input('status');
        $subjectId = $request->input('course_id');

        return ExamSchedule::query()->with(['exam.subject', 'group'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereHas('exam', fn ($exam) => $exam->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('group', fn ($group) => $group->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('exam.subject', fn ($subject) => $subject->where('name', 'like', "%{$search}%"));
            }))
            ->when($examId, fn ($query) => $query->where('exam_id', $examId))
            ->when($groupId, fn ($query) => $query->where('group_id', $groupId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($subjectId, fn ($query) => $query->whereHas('exam', fn ($exam) => $exam->where('course_id', $subjectId)));
    }

    private function schedulePayload(ExamSchedule $schedule): array
    {
        return [
            'id' => $schedule->getKey(),
            'exam_id' => $schedule->exam_id,
            'exam' => $schedule->exam?->name,
            'exam_url' => $schedule->exam ? route('admin.exams.show', $schedule->exam) : null,
            'subject' => $schedule->exam?->subject?->name,
            'group' => $schedule->group?->name,
            'start_date' => $schedule->start_date?->format('Y-m-d'),
            'end_date' => $schedule->end_date?->format('Y-m-d'),
            'duration' => $schedule->duration_minutes ? $schedule->duration_minutes.' min' : '—',
            'status' => $schedule->status->value,
            'status_label' => $schedule->status->label(),
            'edit_url' => route('admin.exam-schedules.edit', $schedule),
            'cancel_url' => route('admin.exam-schedules.cancel', $schedule),
        ];
    }
}
