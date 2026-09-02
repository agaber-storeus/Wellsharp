<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Courses\SaveCourseAction;
use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Language;
use App\Models\Stack;
use App\Models\Supplement;
use App\Services\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    private const PER_PAGE = 25;

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Course::class);
        $query = $this->filteredQuery($request);
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['code', 'name', 'level', 'status', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

        if ($sort === 'level') {
            $query->leftJoin('course_levels as course_level_sort', 'course_level_sort.id', '=', 'courses.course_level_id')
                ->addSelect('courses.*')
                ->orderBy('course_level_sort.name', $direction);
        } else {
            $query->orderBy('courses.'.$sort, $direction);
        }

        $courses = $query->paginate(self::PER_PAGE, ['*'], 'page', $this->safePage($query, $request));

        return response()->json([
            'data' => $courses->getCollection()->map(fn (Course $course): array => $this->coursePayload($course))->values(),
            'meta' => $this->paginationMeta($courses),
        ]);
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Course::class);
        $query = $this->filteredQuery($request);
        [$sort, $direction] = $this->sortValues($request);
        $this->applySort($query, $sort, $direction);
        $courses = $query->paginate(self::PER_PAGE, ['*'], 'page', $this->safePage($query, $request))->withQueryString();
        $initialCourses = $courses->getCollection()->map(fn (Course $course): array => $this->coursePayload($course))->values();

        return view('admin.courses.index', [
            'courses' => $courses,
            'initialCourses' => $initialCourses,
            'initialMeta' => $this->paginationMeta($courses),
            'search' => trim((string) request('search')),
            'status' => request('status'),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Course::class);

        return view('admin.courses.create', ['course' => new Course, ...$this->formData()]);
    }

    public function store(StoreCourseRequest $request, SaveCourseAction $action): RedirectResponse
    {
        $this->authorize('create', Course::class);
        $course = $action->execute(null, $request->validated());

        return redirect()->route('admin.courses.show', $course)->with('status', 'Subject created.');
    }

    public function show(Course $course): View
    {
        $this->authorize('view', $course);

        return view('admin.courses.show', ['course' => $course->load('level', 'stacks', 'supplements', 'languages')]);
    }

    public function edit(Course $course): View
    {
        $this->authorize('update', $course);

        return view('admin.courses.edit', ['course' => $course->load('stacks', 'supplements', 'languages'), ...$this->formData()]);
    }

    public function update(UpdateCourseRequest $request, Course $course, SaveCourseAction $action): RedirectResponse
    {
        $this->authorize('update', $course);
        $course = $action->execute($course, $request->validated());

        return redirect()->route('admin.courses.show', $course)->with('status', 'Subject updated.');
    }

    public function archive(Request $request, Course $course, AuditRecorder $audit): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $course);
        $before = $course->toArray();
        $course->forceFill(['status' => CourseStatus::Retired, 'archived_at' => now()])->save();
        $audit->record('course.archived', $course, $before, $course->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Subject archived.', 'status' => CourseStatus::Retired->value, 'status_label' => 'Archived']);
        }

        return back()->with('status', 'Subject retired.');
    }

    public function unarchive(Request $request, Course $course, AuditRecorder $audit): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $course);
        abort_unless($course->archived_at !== null || $course->status === CourseStatus::Retired, 422, 'This Subject is not archived.');
        $before = $course->toArray();
        $course->forceFill(['status' => CourseStatus::Active, 'archived_at' => null])->save();
        $audit->record('course.unarchived', $course, $before, $course->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Subject unarchived and restored to Active.', 'status' => CourseStatus::Active->value, 'status_label' => CourseStatus::Active->label()]);
        }

        return back()->with('status', 'Subject unarchived and restored to Active.');
    }

    private function formData(): array
    {
        return [
            'levels' => CourseLevel::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'stacks' => Stack::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'supplements' => Supplement::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'languages' => Language::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $status = $request->input('status');

        return Course::query()->with(['level'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('courses.code', 'like', "%{$search}%")
                    ->orWhere('courses.name', 'like', "%{$search}%");
            }))
            ->when($status, fn ($query) => $query->where('courses.status', $status));
    }

    private function safePage($query, Request $request): int
    {
        $requested = max(1, (int) $request->input('page', 1));
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));

        return min($requested, $lastPage);
    }

    /** @return array{0: string, 1: string} */
    private function sortValues(Request $request): array
    {
        $sort = (string) $request->input('sort', 'created_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowed = ['code', 'name', 'level', 'status', 'created_at'];

        return [in_array($sort, $allowed, true) ? $sort : 'created_at', $direction];
    }

    private function applySort($query, string $sort, string $direction): void
    {
        if ($sort === 'level') {
            $query->leftJoin('course_levels as course_level_sort', 'course_level_sort.id', '=', 'courses.course_level_id')
                ->addSelect('courses.*')
                ->orderBy('course_level_sort.name', $direction);
        } else {
            $query->orderBy('courses.'.$sort, $direction);
        }
    }

    private function coursePayload(Course $course): array
    {
        $archived = $course->archived_at !== null || $course->status === CourseStatus::Retired;

        return [
            'id' => $course->getKey(),
            'code' => $course->code,
            'name' => $course->name,
            'level' => $course->level?->name ?: 'Not assigned',
            'status' => $course->status->value,
            'status_label' => $course->status->label(),
            'archived' => $archived,
            'can_archive' => $course->status === CourseStatus::Active && $course->archived_at === null,
            'archive_url' => route('admin.courses.archive', $course),
            'can_unarchive' => $archived,
            'unarchive_url' => route('admin.courses.unarchive', $course),
            'view_url' => route('admin.courses.show', $course),
        ];
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
