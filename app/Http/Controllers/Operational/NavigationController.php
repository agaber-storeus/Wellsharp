<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Users\UpdateOwnProfileAction;
use App\Enums\CertificateDocumentType;
use App\Enums\StaffAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\UpdateProfileRequest;
use App\Models\Certificate;
use App\Models\CourseLevel;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\TrainingClass;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\OperationalClassMapPointBuilder;
use App\Services\OperationalReportingService;
use Illuminate\Http\JsonResponse;
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

    public function revealStudentPassword(User $student, AuditRecorder $audit): JsonResponse
    {
        $this->authorize('viewPassword', $student);
        $password = $student->revealPassword();
        abort_if($password === null, 404, 'No recoverable password is stored for this account.');

        $audit->record('student.password_viewed', $student, null, ['viewed_by_role' => auth()->user()->currentRole?->key]);

        return response()->json(['password' => $password])->header('Cache-Control', 'no-store');
    }

    /**
     * Batch Student-password lookup for the Class Dashboard roster, so the
     * Password column can display plaintext directly (per current business
     * requirement) without embedding it in the page's initial HTML/JSON and
     * without one HTTP request per Student. Scoped strictly to the requested
     * Class's own Enrollments - never a global Student query - and audited
     * as a single roster-level event (see BUSINESS_RULES.md BR-043) rather
     * than one event per Student, since that would flood the audit log on
     * every dashboard open for a large roster without adding traceability
     * (actor, role, Class, and count are already enough to reconstruct what
     * happened).
     */
    public function classStudentPasswords(TrainingClass $trainingClass, AuditRecorder $audit): JsonResponse
    {
        $this->authorize('viewStudentPasswords', $trainingClass);

        $students = $trainingClass->enrollments()
            ->with('student.currentRole')
            ->get()
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->values();

        $payload = $students->map(fn (User $student): array => [
            'student_id' => $student->public_id,
            'password' => $student->currentRole?->key === Role::STUDENT ? $student->revealPassword() : null,
        ])->values();

        $audit->record('student_passwords.class_roster_viewed', $trainingClass, null, [
            'class_id' => $trainingClass->getKey(),
            'actor_role' => auth()->user()->currentRole?->key,
            'student_count' => $payload->count(),
        ]);

        return response()->json(['students' => $payload])->header('Cache-Control', 'no-store');
    }

    public function updateSkillsScore(Request $request, Enrollment $enrollment, AuditRecorder $audit): JsonResponse
    {
        $this->authorize('updateSkillsScore', $enrollment);
        $validated = $request->validate(['skills_score' => ['required', 'integer', 'min:0', 'max:100']]);

        $before = ['skills_score' => $enrollment->skills_score];
        $enrollment->update(['skills_score' => $validated['skills_score']]);
        $audit->record('enrollment.skills_score_updated', $enrollment, $before, ['skills_score' => $enrollment->skills_score]);

        return response()->json(['skills_score' => $enrollment->skills_score]);
    }

    public function analytics(Request $request, OperationalReportingService $reports): View
    {
        $classes = $reports->accessibleClasses(auth()->user());

        $classesJson = $classes->map(function (TrainingClass $trainingClass): array {
            return [
                'course_id' => $trainingClass->course_id,
                'course_level_id' => $trainingClass->course?->course_level_id,
                'starts_at' => $trainingClass->starts_at?->toDateString(),
                'enrollments_count' => (int) $trainingClass->enrollments_count,
                'staff_roles' => $trainingClass->staffAssignments
                    ->where('status', StaffAssignmentStatus::Active)
                    ->pluck('assignment_role')
                    ->map(fn ($role) => $role->value)
                    ->unique()
                    ->values()
                    ->all(),
            ];
        })->values();

        return view('operational.analytics', [
            'workspaceClass' => 'analytics-classes-workspace',
            'classesJson' => $classesJson,
            'courseLevels' => CourseLevel::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'initialFilters' => $request->only(['role', 'date_range', 'course_level_id', 'period', 'graph_type']),
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

        $initialCertificates = $certificates->getCollection()->map(fn (Certificate $certificate): array => $this->certificatePayload($certificate))->values();

        return view('operational.certificate', [
            'workspaceClass' => 'certificate-workspace',
            'certificates' => $certificates,
            'initialCertificates' => $initialCertificates,
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

    public function certificateData(Request $request): JsonResponse
    {
        $classes = $this->accessibleClasses();
        $sort = (string) $request->input('sort', 'issued_at');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['student_name', 'issued_at', 'provider_name', 'instructor_name', 'subject_name'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'issued_at';

        $certificates = $this->certificateQuery($request, $classes)
            ->reorder($sort, $direction)
            ->paginate((int) $request->query('per_page', 200) === 25 ? 25 : 200, ['*'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => $certificates->getCollection()->map(fn (Certificate $certificate): array => $this->certificatePayload($certificate))->values(),
            'meta' => [
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'total' => $certificates->total(),
                'from' => $certificates->firstItem(),
                'to' => $certificates->lastItem(),
            ],
        ]);
    }

    private function certificatePayload(Certificate $certificate): array
    {
        $frontDocument = $certificate->documents->firstWhere('type', CertificateDocumentType::CompletionCardFront);
        $courseLabel = collect([
            $certificate->subject_name ?: $certificate->exam?->subject?->name,
            $certificate->exam?->subject?->level?->name,
            $certificate->exam?->subject?->stacks?->pluck('name')->join(', '),
        ])->filter()->join(', ');

        return [
            'id' => $certificate->getKey(),
            'student_name' => $certificate->student_name,
            'student_email' => $certificate->student_email ?: null,
            'issued_at' => $certificate->issued_at?->format('Y-m-d g:i A'),
            'provider' => $certificate->provider_name ?: $certificate->provider?->name ?: null,
            'instructor' => $certificate->instructor_name ?: $certificate->instructor?->display_name ?: null,
            'course' => $courseLabel ?: $certificate->exam_name,
            'certificate_number' => $certificate->certificate_number,
            'preview_url' => $frontDocument ? route('certificates.documents.preview', [$certificate, $frontDocument]) : null,
            'download_url' => $frontDocument ? route('certificates.documents.download', [$certificate, $frontDocument]) : null,
        ];
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
