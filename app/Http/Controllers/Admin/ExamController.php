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
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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

    public function index(Request $request, Course $course): View
    {
        $this->authorize('viewAny', Exam::class);
        $search = trim((string) $request->input('search'));
        $status = $request->input('status');
        $exams = $course->exams()->withCount(['questions', 'groups', 'schedules'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            }))
            ->when(in_array($status, array_column(ExamStatus::cases(), 'value'), true), fn ($query) => $query->where('status', $status))
            ->latest()->paginate(15)->withQueryString();

        $initialExams = $exams->getCollection()->map(fn (Exam $exam): array => $this->examPayload($exam))->values();

        return view('admin.exams.index', compact('course', 'exams', 'initialExams', 'search', 'status'));
    }

    public function courseData(Request $request, Course $course): JsonResponse
    {
        $this->authorize('viewAny', Exam::class);
        $search = trim((string) $request->input('search'));
        $status = $request->input('status');
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['name', 'code', 'questions_count', 'schedules_count', 'status', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

        $exams = $course->exams()->withCount(['questions', 'groups', 'schedules'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            }))
            ->when(in_array($status, array_column(ExamStatus::cases(), 'value'), true), fn ($query) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate(15, ['*'], 'page', max(1, (int) $request->input('page', 1)));

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
        $subjects = $this->examSubjects();
        $questions = $course->questions()->with(['course', 'options'])->where('is_active', true)->orderBy('question_text')->get();

        return view('admin.exams.create', ['course' => $course, 'subjects' => $subjects, 'selectedCourseId' => $course->id, 'questionBanks' => $this->questionBanks($subjects), 'allowSubjectChange' => false, 'exam' => new Exam(['question_order_mode' => ExamQuestionOrderMode::Static, 'status' => ExamStatus::Draft]), 'questions' => $questions, 'groups' => Group::query()->where('status', 'active')->orderBy('name')->get(), ...$this->staffOptions()]);
    }

    public function createGlobal(): View
    {
        $this->authorize('create', Exam::class);
        $subjects = $this->examSubjects();
        $course = Course::query()->find(request('course_id'));
        $questions = $course?->questions()->with(['course', 'options'])->where('is_active', true)->orderBy('question_text')->get() ?? collect();

        return view('admin.exams.create', ['course' => $course, 'subjects' => $subjects, 'selectedCourseId' => request('course_id'), 'questionBanks' => $this->questionBanks($subjects), 'allowSubjectChange' => true, 'exam' => new Exam(['question_order_mode' => ExamQuestionOrderMode::Static, 'status' => ExamStatus::Draft]), 'questions' => $questions, 'groups' => Group::query()->where('status', 'active')->orderBy('name')->get(), ...$this->staffOptions()]);
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
        $subjects = $this->examSubjects();
        $questions = $course->questions()->with(['course', 'options'])->where('is_active', true)->orderBy('question_text')->get();
        $exam->load('examQuestions');

        return view('admin.exams.edit', compact('course', 'exam', 'questions') + ['subjects' => $subjects, 'questionBanks' => $this->questionBanks($subjects), 'selectedCourseId' => $course->id, 'groups' => Group::query()->where('status', 'active')->orderBy('name')->get()] + $this->staffOptions());
    }

    public function editGlobal(Exam $exam): View
    {
        $this->authorize('update', $exam);
        $course = $exam->subject;
        $subjects = $this->examSubjects();
        $questions = $course->questions()->with(['course', 'options'])->where('is_active', true)->orderBy('question_text')->get();
        $exam->load('examQuestions');

        return view('admin.exams.edit', compact('course', 'exam', 'questions') + ['subjects' => $subjects, 'questionBanks' => $this->questionBanks($subjects), 'selectedCourseId' => $course->id, 'groups' => Group::query()->where('status', 'active')->orderBy('name')->get()] + $this->staffOptions());
    }

    private function staffOptions(): array
    {
        return [
            'proctors' => User::query()->with('profile')->whereHas('currentRole', fn ($role) => $role->where('key', Role::PROCTOR))->where('status', 'active')->whereNull('archived_at')->orderBy('wellsharp_id')->get(),
            'instructors' => User::query()->with('profile')->whereHas('currentRole', fn ($role) => $role->where('key', Role::INSTRUCTOR))->where('status', 'active')->whereNull('archived_at')->orderBy('wellsharp_id')->get(),
        ];
    }

    private function examSubjects(): Collection
    {
        return Course::query()
            ->where('status', 'active')
            ->with(['questions' => fn ($query) => $query->where('is_active', true)->orderBy('question_text')])
            ->orderBy('name')
            ->get();
    }

    private function questionBanks(Collection $subjects): array
    {
        return $subjects->mapWithKeys(fn (Course $subject): array => [
            (string) $subject->id => $subject->questions->map(fn ($question): array => [
                'id' => (string) $question->id,
                'text' => $question->question_text,
                'subject' => $subject->name,
                'type' => $question->type?->value,
                'difficulty' => $question->difficulty?->value,
                'image_url' => $question->question_image_path ? Storage::disk('public')->url($question->question_image_path) : null,
            ])->values()->all(),
        ])->all();
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

    public function archive(Request $request, Exam $exam, AuditRecorder $audit): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $exam);
        abort_if($exam->status === ExamStatus::Archived, 422, 'This exam is already archived.');

        $before = ['status' => $exam->status->value];
        $exam->forceFill(['status' => ExamStatus::Archived, 'updated_by_user_id' => auth()->id()])->save();
        $audit->record('exam.archived', $exam, $before, ['status' => ExamStatus::Archived->value]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Exam archived.', 'status' => ExamStatus::Archived->value, 'status_label' => ExamStatus::Archived->label()]);
        }

        return back()->with('status', 'Exam archived.');
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
            'certificate_validity_years' => $exam->certificate_validity_years,
            'questions_count' => $exam->questions_count,
            'schedules_count' => $exam->schedules_count,
            'question_order_mode_label' => $exam->question_order_mode->label(),
            'status' => $exam->status->value,
            'status_label' => $exam->status->label(),
            'can_archive' => $exam->status !== ExamStatus::Archived,
            'exam_url' => route('admin.exams.show', $exam),
            'edit_url' => route('admin.exams.edit', $exam),
            'archive_url' => route('admin.exams.archive', $exam),
            'schedule_url' => route('admin.exam-schedules.create', ['exam_id' => $exam->getKey()]),
        ];
    }
}
