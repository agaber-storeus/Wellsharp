<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Users\UpdateOwnProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\UpdateProfileRequest;
use App\Models\Certificate;
use App\Models\Role;
use App\Models\TrainingClass;
use App\Models\User;
use App\Services\OperationalClassMapPointBuilder;
use App\Services\OperationalReportingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NavigationController extends Controller
{
    public function profile(): View
    {
        $user = auth()->user()->load(['profile', 'currentRole', 'examControlCredential']);
        $certificate = Certificate::query()
            ->with('documents')
            ->where('student_user_id', $user->getKey())
            ->where('status', 'issued')
            ->latest('issued_at')
            ->first();

        return view('operational.profile', [
            'workspaceClass' => 'profile-workspace',
            'user' => $user,
            'certificate' => $certificate,
            'profilePhotoUrl' => $user->profile?->profile_photo_path
                ? Storage::disk('public')->url($user->profile->profile_photo_path)
                : null,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request, UpdateOwnProfileAction $action): RedirectResponse
    {
        $updatedUser = $action->execute(auth()->user(), $request->validated());
        $request->session()->put('auth.session_version', $updatedUser->session_version);

        return back()->with('status', 'Your profile was updated successfully.');
    }

    public function analytics(OperationalReportingService $reports): View
    {
        $classes = $reports->accessibleClasses(auth()->user());
        $attempts = $reports->filteredAttempts($classes, request());
        $scoredAttempts = $attempts->filter(fn ($attempt): bool => $attempt->score !== null);
        $monthCounts = $classes->filter(fn ($trainingClass) => $trainingClass->starts_at)
            ->groupBy(fn ($trainingClass) => $trainingClass->starts_at->format('Y-m'))
            ->map->count()
            ->sortKeys();

        return view('operational.analytics', [
            'workspaceClass' => 'analytics-classes-workspace',
            'totalClasses' => $classes->count(),
            'averageClassSize' => round($classes->avg(fn ($trainingClass) => $trainingClass->enrollments_count), 1),
            'monthCounts' => $monthCounts,
            'scoredAttempts' => $scoredAttempts->count(),
            'passedAttempts' => $scoredAttempts->where('passed', true)->count(),
            'failedAttempts' => $scoredAttempts->where('passed', false)->count(),
            'averageScore' => $scoredAttempts->isEmpty() ? 0 : round((float) $scoredAttempts->avg('score'), 2),
            'classesPerWeek' => $classes->filter(fn ($class) => $class->starts_at?->greaterThanOrEqualTo(now()->subWeek()))->count(),
            'classesPerMonth' => $classes->filter(fn ($class) => $class->starts_at?->greaterThanOrEqualTo(now()->subMonth()))->count(),
        ]);
    }

    public function analyticsSearch(): View
    {
        $classes = app(OperationalReportingService::class)->accessibleClasses(auth()->user());

        return view('operational.analytics-search', [
            'workspaceClass' => 'assessment-results-workspace',
            'courses' => $classes->pluck('course')->unique('id')->sortBy('name'),
        ]);
    }

    public function assessmentResults(): View
    {
        $classes = $this->accessibleClasses();
        $results = $classes->groupBy(fn ($trainingClass) => $trainingClass->course->name)->map(function ($courseClasses, $courseName): array {
            $trainees = $courseClasses->sum('enrollments_count');

            return ['name' => $courseName, 'trainees' => $trainees, 'passed' => 0, 'failed' => 0, 'rate' => '0%', 'average' => '0%'];
        })->values();

        return view('operational.assessment-results', [
            'workspaceClass' => 'assessment-results-workspace',
            'courses' => $classes->pluck('course')->unique('id')->sortBy('name'),
            'results' => $results,
        ]);
    }

    public function classes(OperationalClassMapPointBuilder $mapPointBuilder): View
    {
        $classes = $this->accessibleClasses();

        return view('operational.classes', [
            'workspaceClass' => 'classes-workspace',
            'classes' => $classes,
            'mapPoints' => $mapPointBuilder->build($classes),
            'classModalData' => $mapPointBuilder->buildModalData($classes),
            'ongoingClasses' => $classes->where('status.value', 'active'),
            'upcomingClasses' => $classes->where('status.value', 'planned'),
            'pastClasses' => $classes->whereIn('status.value', ['completed', 'cancelled']),
        ]);
    }

    public function browse(Request $request, OperationalClassMapPointBuilder $mapPointBuilder): View
    {
        $classes = $this->accessibleClasses();

        return view('operational.browse', $this->browseViewData($request, $classes, $mapPointBuilder));
    }

    public function browseResults(Request $request, OperationalClassMapPointBuilder $mapPointBuilder): View
    {
        $classes = $this->accessibleClasses();

        return view('operational.browse-results', $this->browseViewData($request, $classes, $mapPointBuilder));
    }

    /** @param Collection<int, TrainingClass> $classes */
    private function browseViewData(Request $request, Collection $classes, OperationalClassMapPointBuilder $mapPointBuilder): array
    {
        return [
            'workspaceClass' => 'browse-results-workspace',
            'browseRows' => $classes->map(fn (TrainingClass $trainingClass): array => $this->browseRow($trainingClass, $mapPointBuilder))->values()->all(),
            'courses' => $classes->pluck('course')->unique('id')->sortBy('name'),
            'instructors' => User::query()
                ->whereHas('currentRole', fn ($query) => $query->where('key', Role::INSTRUCTOR))
                ->with('profile')
                ->orderBy('id')
                ->get(),
            'classModalData' => $mapPointBuilder->buildModalData($classes),
            'filters' => $request->only(['search', 'start_date', 'end_date', 'exam_date', 'city', 'country', 'state', 'course_id', 'instructor_id', 'retakes']),
        ];
    }

    private function browseRow(TrainingClass $trainingClass, OperationalClassMapPointBuilder $mapPointBuilder): array
    {
        $instructor = 'Any eligible Instructor';
        $proctor = 'Any eligible Proctor';
        $location = $trainingClass->provider?->address ?: 'Not assigned';
        $retakes = $trainingClass->examSchedules
            ->flatMap(fn ($schedule) => $schedule->attempts)
            ->where('attempt_number', '>', 1)
            ->pluck('student_user_id')
            ->unique()
            ->count();
        $durationDays = $trainingClass->starts_at && $trainingClass->ends_at
            ? max(1, $trainingClass->starts_at->copy()->startOfDay()->diffInDays($trainingClass->ends_at->copy()->startOfDay()) + 1)
            : null;
        $examRanges = $trainingClass->examSchedules
            ->map(fn ($schedule): array => [
                'start' => $schedule->start_date?->toDateString(),
                'end' => ($schedule->end_date ?: $schedule->start_date)?->toDateString(),
            ])
            ->filter(fn (array $range): bool => filled($range['start']))
            ->values()
            ->all();
        $status = $trainingClass->status->value;
        $state = $status === 'active' ? 'open' : ($status === 'planned' ? 'notstarted' : 'ended');

        return [
            'id' => $trainingClass->public_id,
            'class_number' => $trainingClass->class_number,
            'status' => $status,
            'status_label' => $trainingClass->status->label(),
            'state' => $state,
            'provider' => $trainingClass->provider?->name ?: 'Not assigned',
            'instructor' => $instructor,
            'instructor_ids' => [],
            'location' => $location,
            'subject' => $trainingClass->course->name,
            'course_id' => (string) $trainingClass->course_id,
            'exam_date' => $mapPointBuilder->examAvailability($trainingClass),
            'exam_ranges' => $examRanges,
            'starts_at' => $trainingClass->starts_at?->toDateString(),
            'ends_at' => $trainingClass->ends_at?->toDateString(),
            'course_dates' => $this->browseDateRange($trainingClass),
            'users' => (int) $trainingClass->enrollments_count,
            'retakes' => $retakes,
            'duration_days' => $durationDays,
            'duration_label' => $durationDays ? $durationDays.' '.($durationDays === 1 ? 'day' : 'days') : 'Not configured',
            'deployment' => 'Not configured',
            'proctor' => $proctor,
            'search' => strtolower(implode(' ', array_filter([
                $trainingClass->public_id,
                $trainingClass->class_number,
                $trainingClass->status->label(),
                $trainingClass->provider?->name,
                $trainingClass->provider?->address,
                $trainingClass->course->name,
                $instructor,
                $proctor,
            ]))),
        ];
    }

    private function browseDateRange(TrainingClass $trainingClass): string
    {
        if (! $trainingClass->starts_at && ! $trainingClass->ends_at) {
            return 'Not scheduled';
        }

        if (! $trainingClass->starts_at) {
            return $trainingClass->ends_at->format('M j, Y');
        }

        if (! $trainingClass->ends_at) {
            return $trainingClass->starts_at->format('M j, Y');
        }

        return $trainingClass->starts_at->format('M j').' - '.$trainingClass->ends_at->format('M j, Y');
    }

    public function certificate(Request $request): View
    {
        $classes = $this->accessibleClasses();
        $certificates = $this->certificateQuery($request, $classes)
            ->paginate((int) $request->query('per_page', 200) === 25 ? 25 : 200)
            ->withQueryString();

        return view('operational.certificate', [
            'workspaceClass' => 'certificate-workspace',
            'certificates' => $certificates,
            'providers' => $classes->pluck('provider')->filter()->unique('id')->sortBy('name'),
            'instructors' => User::query()
                ->whereHas('currentRole', fn ($query) => $query->where('key', Role::INSTRUCTOR))
                ->with('profile')
                ->orderBy('id')
                ->get(),
            'levels' => $classes->pluck('course.level')->filter()->unique('id')->sortBy('name'),
            'supplements' => $classes->flatMap(fn ($class) => $class->course->supplements)->filter()->unique('id')->sortBy('name'),
            'filters' => $request->only(['first_name', 'last_name', 'email', 'certificate_id', 'start_date', 'end_date', 'class_id', 'provider_id', 'instructor_id', 'level_id', 'supplement_id', 'per_page']),
        ]);
    }

    public function certificateExport(Request $request): StreamedResponse
    {
        $classes = $this->accessibleClasses();
        $certificates = $this->certificateQuery($request, $classes)->get();

        return response()->streamDownload(function () use ($certificates): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Certificate ID', 'Student', 'Email', 'Issued', 'Provider', 'Instructor', 'Subject', 'Class', 'Score', 'Status']);
            foreach ($certificates as $certificate) {
                fputcsv($output, [
                    $certificate->certificate_number,
                    $certificate->student_name,
                    $certificate->student_email,
                    $certificate->issued_at?->format('Y-m-d'),
                    $certificate->provider_name ?: $certificate->provider?->name,
                    $certificate->instructor_name ?: $certificate->instructor?->display_name,
                    $certificate->subject_name ?: $certificate->exam?->subject?->name,
                    $certificate->class_number,
                    $certificate->score,
                    $certificate->status?->label(),
                ]);
            }
            fclose($output);
        }, 'wellsharp-certificates.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function certificateQuery(Request $request, Collection $classes)
    {
        $classIds = $classes->pluck('id');

        return Certificate::query()
            ->with([
                'student.profile',
                'exam.subject.level',
                'exam.subject.stacks',
                'exam.subject.supplements',
                'provider',
                'instructor.profile',
                'documents',
            ])
            ->whereIn('training_class_id', $classIds)
            ->when($request->filled('first_name'), function ($query) use ($request): void {
                $value = trim((string) $request->input('first_name'));
                $query->where(function ($query) use ($value): void {
                    $query->where('student_name', 'like', '%'.$value.'%')
                        ->orWhereHas('student.profile', fn ($profile) => $profile->where('first_name', 'like', '%'.$value.'%'));
                });
            })
            ->when($request->filled('last_name'), function ($query) use ($request): void {
                $value = trim((string) $request->input('last_name'));
                $query->where(function ($query) use ($value): void {
                    $query->where('student_name', 'like', '%'.$value.'%')
                        ->orWhereHas('student.profile', fn ($profile) => $profile->where('last_name', 'like', '%'.$value.'%'));
                });
            })
            ->when($request->filled('email'), fn ($query) => $query->where('student_email', 'like', '%'.trim((string) $request->input('email')).'%'))
            ->when($request->filled('certificate_id'), fn ($query) => $query->where('certificate_number', 'like', '%'.trim((string) $request->input('certificate_id')).'%'))
            ->when($request->filled('class_id'), fn ($query) => $query->where(function ($query) use ($request): void {
                $value = trim((string) $request->input('class_id'));
                $query->where('class_number', 'like', '%'.$value.'%')->orWhereHas('trainingClass', fn ($class) => $class->where('public_id', 'like', '%'.$value.'%'));
            }))
            ->when($request->filled('provider_id'), fn ($query) => $query->where('training_provider_id', $request->input('provider_id')))
            ->when($request->filled('instructor_id'), fn ($query) => $query->where('instructor_user_id', $request->input('instructor_id')))
            ->when($request->filled('level_id'), fn ($query) => $query->whereHas('exam.subject', fn ($subject) => $subject->where('course_level_id', $request->input('level_id'))))
            ->when($request->filled('supplement_id'), fn ($query) => $query->whereHas('exam.subject.supplements', fn ($supplement) => $supplement->whereKey($request->input('supplement_id'))))
            ->when($request->filled('start_date'), fn ($query) => $query->whereDate('issued_at', '>=', $request->input('start_date')))
            ->when($request->filled('end_date'), fn ($query) => $query->whereDate('issued_at', '<=', $request->input('end_date')))
            ->latest('issued_at');
    }

    /** @return Collection<int, TrainingClass> */
    private function accessibleClasses()
    {
        return TrainingClass::query()
            ->with(['course.languages', 'course.level', 'course.stacks', 'course.supplements', 'provider', 'enrollments.student.profile', 'examSchedules.exam', 'examSchedules.attempts.student.profile'])
            ->withCount('enrollments')
            ->latest()
            ->get();
    }

    private function hasRetakes(TrainingClass $trainingClass): bool
    {
        return $trainingClass->examSchedules
            ->flatMap(fn ($schedule) => $schedule->attempts)
            ->where('attempt_number', '>', 1)
            ->isNotEmpty();
    }
}
