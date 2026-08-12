<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Exams\SaveExamAction;
use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamRequest;
use App\Http\Requests\Admin\UpdateExamRequest;
use App\Models\Course;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Exam::class);
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['name', 'code', 'subject', 'questions_count', 'schedules_count', 'status', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $query = $this->filteredQuery($request);

        if ($sort === 'subject') {
            $query->join('courses as exam_subject_sort', 'exam_subject_sort.id', '=', 'exams.course_id')->addSelect('exams.*')->orderBy('exam_subject_sort.name', $direction);
        } else {
            $query->orderBy('exams.'.$sort, $direction);
        }

        $exams = $query->paginate(25, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => $exams->getCollection()->map(fn (Exam $exam): array => $this->examPayload($exam))->values(),
            'meta' => [
                'current_page' => $exams->currentPage(),
                'last_page' => $exams->lastPage(),
                'total' => $exams->total(),
                'from' => $exams->firstItem(),
                'to' => $exams->lastItem(),
            ],
        ]);
    }

    public function index(Course $course): View
    {
        $this->authorize('viewAny', Exam::class);
        $exams = $course->exams()->withCount(['questions', 'groups', 'schedules'])->latest()->paginate(15);

        return view('admin.exams.index', compact('course', 'exams'));
    }

    public function all(): View
    {
        $this->authorize('viewAny', Exam::class);
        $search = trim((string) request('search'));
        $courseId = request('course_id');
        $status = request('status');
        $exams = $this->filteredQuery(request())->latest('exams.created_at')->paginate(25)->withQueryString();
        $initialExams = $exams->getCollection()->map(fn (Exam $exam): array => $this->examPayload($exam))->values();
        $initialMeta = ['current_page' => $exams->currentPage(), 'last_page' => $exams->lastPage(), 'total' => $exams->total(), 'from' => $exams->firstItem(), 'to' => $exams->lastItem()];

        return view('admin.exams.all', ['exams' => $exams, 'initialExams' => $initialExams, 'initialMeta' => $initialMeta, 'subjects' => Course::query()->orderBy('name')->get(), 'search' => $search, 'courseId' => $courseId, 'status' => $status]);
    }

    public function create(Course $course): View
    {
        $this->authorize('create', Exam::class);
        $questions = $course->questions()->with(['course', 'options'])->where('is_active', true)->orderBy('question_text')->get();

        return view('admin.exams.create', ['course' => $course, 'subjects' => Course::query()->where('status', 'active')->orderBy('name')->get(), 'selectedCourseId' => $course->id, 'exam' => new Exam(['question_order_mode' => ExamQuestionOrderMode::Static, 'status' => ExamStatus::Draft]), 'questions' => $questions]);
    }

    public function createGlobal(): View
    {
        $this->authorize('create', Exam::class);
        $course = Course::query()->find(request('course_id'));
        $questions = $course?->questions()->with(['course', 'options'])->where('is_active', true)->orderBy('question_text')->get() ?? collect();

        return view('admin.exams.create', ['course' => $course, 'subjects' => Course::query()->where('status', 'active')->orderBy('name')->get(), 'selectedCourseId' => request('course_id'), 'exam' => new Exam(['question_order_mode' => ExamQuestionOrderMode::Static, 'status' => ExamStatus::Draft]), 'questions' => $questions]);
    }

    public function store(StoreExamRequest $request, Course $course, SaveExamAction $action): RedirectResponse
    {
        $this->authorize('create', Exam::class);
        $exam = $action->execute(null, $course, $request->validated());

        return redirect()->route('admin.courses.exams.show', [$course, $exam])->with('status', 'Exam created.');
    }

    public function storeGlobal(StoreExamRequest $request, SaveExamAction $action): RedirectResponse
    {
        $this->authorize('create', Exam::class);
        $course = Course::query()->findOrFail($request->validated('course_id'));
        $exam = $action->execute(null, $course, $request->validated());

        return redirect()->route('admin.exams.show', $exam)->with('status', 'Exam created.');
    }

    public function show(Course $course, Exam $exam): View
    {
        $this->ensureCourseExam($course, $exam);
        $this->authorize('view', $exam);
        $exam->load(['subject', 'examQuestions.question.options', 'schedules.group']);

        return view('admin.exams.show', compact('course', 'exam'));
    }

    public function showGlobal(Exam $exam): View
    {
        $this->authorize('view', $exam);
        $exam->load(['subject', 'examQuestions.question.options', 'schedules.group']);

        return view('admin.exams.show', ['course' => $exam->subject, 'exam' => $exam]);
    }

    public function edit(Course $course, Exam $exam): View
    {
        $this->ensureCourseExam($course, $exam);
        $this->authorize('update', $exam);
        $questions = $course->questions()->with(['course', 'options'])->where('is_active', true)->orderBy('question_text')->get();
        $exam->load('examQuestions');

        return view('admin.exams.edit', compact('course', 'exam', 'questions') + ['subjects' => Course::query()->where('status', 'active')->orderBy('name')->get(), 'selectedCourseId' => $course->id]);
    }

    public function editGlobal(Exam $exam): View
    {
        $this->authorize('update', $exam);
        $course = $exam->subject;
        $questions = $course->questions()->with(['course', 'options'])->where('is_active', true)->orderBy('question_text')->get();
        $exam->load('examQuestions');

        return view('admin.exams.edit', compact('course', 'exam', 'questions') + ['subjects' => Course::query()->where('status', 'active')->orderBy('name')->get(), 'selectedCourseId' => $course->id]);
    }

    public function update(UpdateExamRequest $request, Course $course, Exam $exam, SaveExamAction $action): RedirectResponse
    {
        $this->ensureCourseExam($course, $exam);
        $this->authorize('update', $exam);
        $action->execute($exam, $course, $request->validated());

        return redirect()->route('admin.courses.exams.show', [$course, $exam])->with('status', 'Exam updated.');
    }

    public function updateGlobal(UpdateExamRequest $request, Exam $exam, SaveExamAction $action): RedirectResponse
    {
        $this->authorize('update', $exam);
        $course = Course::query()->findOrFail($request->validated('course_id'));
        abort_unless($exam->course_id === $course->getKey(), 422, 'An existing exam cannot be moved between Subjects.');
        $action->execute($exam, $course, $request->validated());

        return redirect()->route('admin.exams.show', $exam)->with('status', 'Exam updated.');
    }

    private function ensureCourseExam(Course $course, Exam $exam): void
    {
        abort_unless($exam->course_id === $course->getKey(), 404);
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $courseId = $request->input('course_id');
        $status = $request->input('status');

        return Exam::query()->with('subject')->withCount(['questions', 'schedules'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('exams.name', 'like', "%{$search}%")
                    ->orWhere('exams.code', 'like', "%{$search}%")
                    ->orWhereHas('subject', fn ($subject) => $subject->where('name', 'like', "%{$search}%"));
            }))
            ->when($courseId, fn ($query) => $query->where('exams.course_id', $courseId))
            ->when($status, fn ($query) => $query->where('exams.status', $status));
    }

    private function examPayload(Exam $exam): array
    {
        return [
            'id' => $exam->getKey(),
            'name' => $exam->name,
            'code' => $exam->code,
            'subject' => $exam->subject?->name,
            'passing_score' => $exam->passing_score,
            'retake_score' => $exam->retake_score,
            'questions_count' => $exam->questions_count,
            'schedules_count' => $exam->schedules_count,
            'status' => $exam->status->value,
            'status_label' => $exam->status->label(),
            'exam_url' => route('admin.exams.show', $exam),
            'edit_url' => route('admin.exams.edit', $exam),
            'schedule_url' => route('admin.exam-schedules.create', ['exam_id' => $exam->getKey()]),
        ];
    }
}
