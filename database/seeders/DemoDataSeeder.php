<?php

namespace Database\Seeders;

use App\Actions\Certificates\IssueCertificateAction;
use App\Enums\CertificateStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamGroupAssignmentStatus;
use App\Enums\ExamQuestionOrderMode;
use App\Enums\ExamScheduleStatus;
use App\Enums\ExamStatus;
use App\Enums\Gender;
use App\Enums\GroupMembershipStatus;
use App\Enums\GroupStatus;
use App\Enums\ProviderStatus;
use App\Enums\QuestionType;
use App\Enums\UserStatus;
use App\Models\AuditEvent;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamGroupAssignment;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Language;
use App\Models\LoginEvent;
use App\Models\Question;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Stack;
use App\Models\StudentSurvey;
use App\Models\Supplement;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use App\Services\ProctorIdGenerator;
use App\Services\StudentSurveyDefinition;
use App\Services\UserIdentityGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'WellSharp-Demo-2026!';

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        $this->call(IadcExamQuestions2026Seeder::class);

        DB::transaction(function (): void {
            $roles = Role::query()->pluck('id', 'key')->all();
            $references = $this->seedReferenceData();
            $providers = $this->seedProviders();
            $users = $this->seedUsers($roles);
            $subjectCodes = collect(IadcExamQuestions2026Seeder::legacySubjects())->pluck('code');
            $courses = Course::query()->whereIn('code', $subjectCodes)->get()->all();
            $questions = Question::query()->whereIn('course_id', collect($courses)->pluck('id'))->get()->all();
            $groups = $this->seedGroups($users['students'], $users['admin']);
            $exams = $this->seedExams($courses, $questions, $users['admin']);
            $this->seedExamGroupAssignments($exams, $groups, $users['admin']);
            $classes = $this->seedClasses($courses, $providers, $users);
            $schedules = $this->seedExamSchedules($exams, $groups, $classes, $users['admin'], $users);

            $this->seedEnrollments($classes, $users['students']);
            $this->seedStudentFlows($schedules, $users['students']);
            $this->seedCertificates();
            $this->seedAuditEvents($users['admin'], $users, $providers, $courses, $classes, $questions, $groups, $exams, $schedules);
            $this->seedLoginEvents($users);
        });

        $this->command?->info('Repeatable WellSharp demo data seeded.');
        $this->command?->info('Demo password for DEMO-* accounts: '.self::DEMO_PASSWORD);
        $this->command?->info("Proctor's IDs are PR-DEMO-PROCTOR-001..008. Instructors do not receive one.");
    }

    /** @return array<string, array<int, object>> */
    private function seedReferenceData(): array
    {
        $references = [
            'levels' => $this->references(CourseLevel::class, ['Foundation', 'Advanced', 'Supervisor', 'Well Control Specialist']),
            'stacks' => $this->references(Stack::class, ['Surface', 'Subsea', 'Surface and Subsea', 'Well Control']),
            'supplements' => $this->references(Supplement::class, ['Core', 'HPHT', 'Managed Pressure', 'Deepwater']),
            'languages' => $this->references(Language::class, ['English', 'Arabic', 'Spanish', 'French']),
        ];

        // Keep one inactive value in each reference list so the configuration
        // screens can be tested without changing the values used by demo courses.
        foreach ([
            'levels' => [CourseLevel::class, 'Legacy Level'],
            'stacks' => [Stack::class, 'Legacy Stack'],
            'supplements' => [Supplement::class, 'Legacy Supplement'],
            'languages' => [Language::class, 'Legacy Language'],
        ] as $key => [$model, $name]) {
            $references[$key][] = $model::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => 99, 'is_active' => false],
            );
        }

        return $references;
    }

    /** @return array<int, object> */
    private function references(string $model, array $names): array
    {
        return collect($names)->values()->map(function (string $name, int $index) use ($model): object {
            return $model::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $index, 'is_active' => true],
            );
        })->all();
    }

    /** @return array<int, TrainingProvider> */
    private function seedProviders(): array
    {
        $providers = [];
        $coordinates = [
            [30.0444, 31.2357],
            [29.3759, 47.9774],
            [9.0820, 8.6753],
            [1.6508, 7.4128],
            [25.2048, 55.2708],
            [24.7136, 46.6753],
            [35.6762, 139.6503],
            [51.5074, -0.1278],
            [32.8872, 13.1913],
            [31.9539, 35.9106],
            [33.5138, 36.2765],
            [41.0082, 28.9784],
        ];
        $providerNames = [
            'Cairo Well Control Academy', 'Gulf Energy Training', 'Nile Drilling Institute', 'Red Sea Technical School',
            'Delta Operations Center', 'Alexandria Safety Institute', 'Desert Well Services', 'International Rig Training',
            'Tripoli Well Operations Center', 'Amman Drilling Academy', 'Damascus Technical Institute', 'Bosphorus Energy School',
        ];
        $providerAddresses = [
            '5th Settlement Training Campus, New Cairo, Egypt',
            'Mubarak Al-Kabeer Street, Kuwait City, Kuwait',
            'Plot 12, Port Harcourt Industrial Layout, Nigeria',
            'Avenida da Independencia, Sao Tome, Sao Tome and Principe',
            'Dubai Academic City, Dubai, United Arab Emirates',
            'King Fahd Road, Riyadh, Saudi Arabia',
            '2-11-3 Marunouchi, Chiyoda City, Tokyo, Japan',
            '25 Canada Square, London, United Kingdom',
            'Al Nasr Street, Tripoli, Libya',
            'Queen Rania Street, Amman, Jordan',
            'Mezzeh Highway, Damascus, Syria',
            'Maslak Mahallesi, Istanbul, Türkiye',
        ];
        foreach (range(1, 12) as $number) {
            $provider = TrainingProvider::query()->updateOrCreate(
                ['provider_number' => sprintf('DEMO-TP-%03d', $number)],
                [
                    'name' => $providerNames[$number - 1],
                    'email' => sprintf('provider%02d@wellsharp.test', $number),
                    'phone' => '+20 100 555 '.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    'address' => $providerAddresses[$number - 1],
                    'latitude' => $coordinates[$number - 1][0],
                    'longitude' => $coordinates[$number - 1][1],
                    'status' => $number === 12 ? ProviderStatus::Inactive : ProviderStatus::Active,
                    'archived_at' => null,
                ],
            );
            $providers[] = $provider;
        }

        $archived = TrainingProvider::query()->updateOrCreate(
            ['provider_number' => 'DEMO-TP-ARCHIVED'],
            ['name' => 'Archived Demo Provider', 'email' => 'archived@wellsharp.test', 'status' => ProviderStatus::Archived, 'archived_at' => now()->subDays(10)],
        );
        $archived->forceFill(['archived_at' => now()->subDays(10)])->save();

        return [...$providers, $archived];
    }

    /** @param array<string, int> $roles
     * @return array{admin: User, proctors: array<int, User>, instructors: array<int, User>, students: array<int, User>}
     */
    private function seedUsers(array $roles): array
    {
        $admin = $this->user('DEMO-ADMIN-001', 'Demo', 'Administrator', Role::ADMIN, $roles[Role::ADMIN], 'demo.admin@wellsharp.test');
        $proctors = [];
        $instructors = [];
        $students = [];

        $proctorNames = [
            ['Omar', 'Hassan'], ['Mariam', 'El-Sayed'], ['Tarek', 'Mahmoud'], ['Nour', 'Khalil'],
            ['Youssef', 'Fahmy'], ['Salma', 'Nabil'], ['Karim', 'Mostafa'], ['Hana', 'Adel'],
        ];
        $instructorNames = [
            ['Ahmed', 'Kotb'], ['Abraham', 'Gerges'], ['Laila', 'Mansour'], ['Peter', 'Morgan'],
            ['Samir', 'Farouk'], ['Dalia', 'Said'], ['Michael', 'Brown'], ['Rania', 'Ibrahim'],
        ];
        $studentNames = [
            ['Mohamed', 'Mostafa'], ['John', 'Carter'], ['Ahmed', 'Saleh'], ['Sara', 'Hassan'],
            ['Daniel', 'Wilson'], ['Mina', 'George'], ['Fatma', 'Ali'], ['Omar', 'Nasser'],
            ['James', 'Taylor'], ['Huda', 'Younis'], ['Khaled', 'Samir'], ['Emily', 'Stone'],
            ['Hany', 'Rashad'], ['Nadia', 'Fouad'], ['William', 'Evans'], ['Menna', 'Tarek'],
            ['Adam', 'Hussein'], ['Mai', 'Gaber'], ['Robert', 'Lee'], ['Aya', 'Saber'],
            ['Hassan', 'Abdelrahman'], ['Maya', 'Johnson'], ['Ibrahim', 'Sayed'], ['Lina', 'Kamel'],
            ['George', 'Thomas'], ['Reem', 'Amin'], ['Mostafa', 'Khaled'], ['Olivia', 'Martin'],
            ['Ehab', 'Othman'], ['Yara', 'Fathy'], ['David', 'Clark'], ['Nada', 'Maher'],
            ['Sherif', 'Hamdy'], ['Grace', 'Walker'], ['Amr', 'Zaki'], ['Mona', 'Saad'],
            ['Steven', 'Hall'], ['Esraa', 'Yehia'], ['Wael', 'Hamed'], ['Emma', 'King'],
        ];

        foreach (range(1, 8) as $number) {
            [$proctorFirstName, $proctorLastName] = $proctorNames[$number - 1];
            [$instructorFirstName, $instructorLastName] = $instructorNames[$number - 1];
            $proctors[] = $this->user(
                sprintf('DEMO-PROCTOR-%03d', $number),
                $proctorFirstName,
                $proctorLastName,
                Role::PROCTOR,
                $roles[Role::PROCTOR],
                sprintf('demo.proctor%02d@wellsharp.test', $number),
                sprintf('PR-DEMO-PROCTOR-%03d', $number),
            );
            $instructors[] = $this->user(
                sprintf('DEMO-INSTRUCTOR-%03d', $number),
                $instructorFirstName,
                $instructorLastName,
                Role::INSTRUCTOR,
                $roles[Role::INSTRUCTOR],
                sprintf('demo.instructor%02d@wellsharp.test', $number),
            );
        }

        foreach (range(1, 40) as $number) {
            [$firstName, $lastName] = $studentNames[$number - 1];
            $students[] = $this->user(sprintf('DEMO-STUDENT-%03d', $number), $firstName, $lastName, Role::STUDENT, $roles[Role::STUDENT], sprintf('demo.student%02d@wellsharp.test', $number));
        }

        // These accounts are intentionally excluded from groups and active
        // assignments. They exercise login rejection and user status filters.
        $disabledStudent = $this->user('DEMO-STUDENT-DISABLED', 'Disabled', 'Student', Role::STUDENT, $roles[Role::STUDENT], 'demo.student.disabled@wellsharp.test');
        $disabledStudent->forceFill(['status' => UserStatus::Disabled, 'session_version' => 2])->save();

        $disabledProctor = $this->user('DEMO-PROCTOR-DISABLED', 'Disabled', 'Proctor', Role::PROCTOR, $roles[Role::PROCTOR], 'demo.proctor.disabled@wellsharp.test', 'PR-DEMO-PROCTOR-DISABLED');
        $disabledProctor->forceFill(['status' => UserStatus::Disabled, 'session_version' => 2])->save();

        // Keep one active student with nullable contact fields to exercise
        // optional-field validation and profile display states.
        $minimalStudent = $this->user('DEMO-STUDENT-MINIMAL', 'Minimal', 'Student', Role::STUDENT, $roles[Role::STUDENT], null);
        $minimalStudent->profile()->update([
            'phone' => null,
            'birthday' => null,
            'address' => null,
            'country' => null,
            'state' => null,
            'city' => null,
            'postal_code' => null,
            'company' => null,
            'position' => null,
            'company_contact' => null,
            'employee_id' => null,
            'age' => null,
            'gender' => null,
        ]);

        $archivedInstructor = $this->user('DEMO-INSTRUCTOR-ARCHIVED', 'Archived', 'Instructor', Role::INSTRUCTOR, $roles[Role::INSTRUCTOR], 'demo.instructor.archived@wellsharp.test');
        $archivedInstructor->forceFill(['status' => UserStatus::Archived, 'archived_at' => now()->subDays(10), 'session_version' => 2])->save();

        return compact('admin', 'proctors', 'instructors', 'students');
    }

    private function user(string $wellsharpId, string $firstName, string $lastName, string $roleKey, int $roleId, ?string $email, ?string $controlId = null): User
    {
        $user = User::query()->firstOrNew(['wellsharp_id' => $wellsharpId]);
        $user->setPasswordAndCiphertext(self::DEMO_PASSWORD, $roleKey);
        $user->forceFill([
            'email' => $email,
            'status' => UserStatus::Active,
            'current_role_id' => $roleId,
            'archived_at' => null,
        ]);
        // Demo accounts keep their fixed, documented WellSharp IDs (DEMO-*) so
        // login credentials in README/docs stay valid across reseeds; only the
        // display-only username is generated, and only once, from the name.
        if (blank($user->username)) {
            $user->username = app(UserIdentityGenerator::class)->generateUsername($firstName, $lastName);
        }
        $user->save();
        $generator = app(ProctorIdGenerator::class);
        if ($generator->eligibleForRole($roleKey)) {
            $user->examControlCredential()->updateOrCreate([], ['control_id' => $controlId ?: $generator->generate()]);
        } else {
            $user->examControlCredential()->delete();
        }
        $user->profile()->updateOrCreate([], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => '+20 100 555 '.str_pad((string) $user->getKey(), 4, '0', STR_PAD_LEFT),
            'birthday' => now()->subYears($roleKey === Role::STUDENT ? 22 + ($user->getKey() % 15) : 30 + ($user->getKey() % 15))->subDays($user->getKey() % 28)->toDateString(),
            'address' => $roleKey === Role::STUDENT ? 'WellSharp Demo Residence '.$user->getKey() : 'WellSharp Demo Office '.$user->getKey(),
            'country' => 'Egypt',
            'state' => $roleKey === Role::STUDENT ? null : 'Cairo',
            'city' => 'Cairo',
            'postal_code' => '11511',
            'company' => $roleKey === Role::STUDENT ? 'Demo Energy Company' : 'WellSharp Training Center',
            'position' => $roleKey === Role::STUDENT ? 'Driller' : ucfirst($roleKey),
            'company_contact' => $roleKey === Role::STUDENT ? '+20 100 000 0000' : null,
            'employee_id' => 'DEMO-'.strtoupper($roleKey).'-'.str_pad((string) $user->getKey(), 4, '0', STR_PAD_LEFT),
            // Gender is a student business field. Staff profiles intentionally
            // remain null so the seed reflects the role-specific forms.
            'gender' => $roleKey === Role::STUDENT
                ? ($user->getKey() % 2 === 0 ? Gender::Female->value : Gender::Male->value)
                : null,
            'age' => $roleKey === Role::STUDENT ? 22 + ($user->getKey() % 15) : null,
        ]);
        RoleAssignment::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'role_id' => $roleId, 'ended_at' => null],
            ['started_at' => now(), 'assigned_by_user_id' => null],
        );

        return $user->fresh(['profile', 'currentRole']);
    }

    /** @param array<int, User> $students */
    private function seedGroups(array $students, User $admin): array
    {
        $names = ['Well Control Morning Group', 'Well Control Evening Group', 'August Cohort', 'September Cohort', 'Advanced Operations Group', 'Subsea Specialists', 'Instructor Preparation Group', 'Archived Demo Group'];
        $groups = [];
        foreach ($names as $index => $name) {
            $number = $index + 1;
            $group = Group::query()->updateOrCreate(
                ['code' => sprintf('DEMO-GROUP-%03d', $number)],
                [
                    'name' => $name,
                    'description' => 'Demo group used to exercise student membership and exam assignment workflows.',
                    'status' => $number === count($names) ? GroupStatus::Archived : GroupStatus::Active,
                    'created_by_user_id' => $admin->getKey(),
                    'updated_by_user_id' => $admin->getKey(),
                ],
            );
            $groups[] = $group;
            foreach (range(0, 7) as $offset) {
                $student = $students[(($index * 4) + $offset) % count($students)];
                GroupMembership::query()->updateOrCreate(
                    ['group_id' => $group->getKey(), 'student_user_id' => $student->getKey(), 'status' => GroupMembershipStatus::Active],
                    ['joined_at' => now()->subDays(30 - $index), 'removed_at' => null, 'created_by_user_id' => $admin->getKey()],
                );
            }
        }
        $removedStudent = $students[0];
        GroupMembership::query()->updateOrCreate(
            ['group_id' => $groups[0]->getKey(), 'student_user_id' => $removedStudent->getKey(), 'status' => GroupMembershipStatus::Removed],
            ['joined_at' => now()->subDays(90), 'removed_at' => now()->subDays(45), 'created_by_user_id' => $admin->getKey()],
        );

        return $groups;
    }

    /** @param array<int, Course> $courses
     * @param  array<int, Question>  $questions
     * @return array<int, Exam>
     */
    private function seedExams(array $courses, array $questions, User $admin): array
    {
        $byCourse = collect($questions)->groupBy('course_id');
        $exams = [];
        foreach ($courses as $index => $course) {
            foreach ([
                'PRACTICE' => [ExamQuestionOrderMode::Static, $index === count($courses) - 1 ? ExamStatus::Archived : ExamStatus::Published],
                'FINAL' => [ExamQuestionOrderMode::Shuffle, $index === count($courses) - 1 ? ExamStatus::Archived : ExamStatus::Published],
                'DRAFT' => [ExamQuestionOrderMode::Static, ExamStatus::Draft],
            ] as $kind => [$mode, $status]) {
                $number = $index + 1;
                $exam = Exam::query()->updateOrCreate(
                    ['code' => sprintf('DEMO-EXAM-%03d-%s', $number, $kind)],
                    [
                        'course_id' => $course->getKey(),
                        'name' => "{$course->name} {$kind} Exam",
                        'description' => 'Demo exam definition for the Subject question bank and scheduling workflows.',
                        'passing_score' => 75,
                        'retake_score' => 60,
                        'question_order_mode' => $mode,
                        'status' => $status,
                        'created_by_user_id' => $admin->getKey(),
                        'updated_by_user_id' => $admin->getKey(),
                    ],
                );
                $exam->examQuestions()->delete();
                foreach ($byCourse->get($course->getKey(), collect())->take(10)->values() as $questionIndex => $question) {
                    $exam->examQuestions()->create([
                        'question_id' => $question->getKey(),
                        'display_order' => $questionIndex + 1,
                        'points' => $question->default_marks,
                    ]);
                }
                $exams[] = $exam;
            }
        }

        return $exams;
    }

    /** @param array<int, Exam> $exams
     * @param  array<int, Group>  $groups
     */
    private function seedExamGroupAssignments(array $exams, array $groups, User $admin): void
    {
        $eligibleGroups = array_values(array_filter($groups, fn (Group $group): bool => $group->status === GroupStatus::Active));
        if (count($eligibleGroups) < 2) {
            return;
        }

        foreach ($exams as $index => $exam) {
            $activeGroup = $eligibleGroups[$index % count($eligibleGroups)];
            ExamGroupAssignment::query()->updateOrCreate(
                ['exam_id' => $exam->getKey(), 'group_id' => $activeGroup->getKey(), 'status' => ExamGroupAssignmentStatus::Active],
                ['assigned_by_user_id' => $admin->getKey(), 'assigned_at' => now()->subDays(14), 'removed_at' => null],
            );

            $removedGroup = $eligibleGroups[($index + 1) % count($eligibleGroups)];
            if ($removedGroup->getKey() !== $activeGroup->getKey()) {
                ExamGroupAssignment::query()->updateOrCreate(
                    ['exam_id' => $exam->getKey(), 'group_id' => $removedGroup->getKey(), 'status' => ExamGroupAssignmentStatus::Removed],
                    ['assigned_by_user_id' => $admin->getKey(), 'assigned_at' => now()->subDays(30), 'removed_at' => now()->subDays(7)],
                );
            }
        }
    }

    /** @param array<int, Exam> $exams
     * @param  array<int, Group>  $groups
     * @param  array{admin: User, proctors: array<int, User>, instructors: array<int, User>, students: array<int, User>}  $users
     * @return array<int, ExamSchedule>
     */
    private function seedExamSchedules(array $exams, array $groups, array $classes, User $admin, array $users): array
    {
        $schedules = [];
        $activeGroups = array_slice($groups, 0, max(1, count($groups) - 1));
        $classesByCourseStatus = collect($classes)->groupBy(fn (TrainingClass $class): string => $class->course_id.'|'.$class->status->value);
        $scenarios = ['ongoing', 'upcoming', 'past', 'cancelled'];

        foreach ($exams as $index => $exam) {
            if ($exam->status === ExamStatus::Draft) {
                // Draft exams are reusable definitions only and cannot be
                // scheduled until an administrator publishes them.
                ExamSchedule::query()->where('exam_id', $exam->getKey())->delete();

                continue;
            }

            $existingSchedules = ExamSchedule::query()
                ->where('exam_id', $exam->getKey())
                ->orderBy('id')
                ->get();
            $existingSchedules->slice(2)->each->delete();

            foreach ([0, 1] as $scheduleIndex) {
                $scenario = $exam->status === ExamStatus::Archived
                    ? ($scheduleIndex === 0 ? 'past' : 'cancelled')
                    : $scenarios[(($index * 2) + $scheduleIndex) % count($scenarios)];
                [$startDate, $endDate, $classStatus, $scheduleStatus] = $this->scheduleWindow($scenario, $index);
                $group = $activeGroups[($index + $scheduleIndex) % count($activeGroups)];
                $linkedClass = $classesByCourseStatus->get($exam->course_id.'|'.$classStatus->value)?->first();

                if (! $linkedClass) {
                    continue;
                }

                $schedule = $existingSchedules->get($scheduleIndex) ?? new ExamSchedule(['exam_id' => $exam->getKey()]);
                $schedule->fill([
                    'group_id' => $group->getKey(),
                    'training_class_id' => $linkedClass->getKey(),
                    'training_provider_id' => $linkedClass->training_provider_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'duration_minutes' => 60 + (($index + $scheduleIndex) % 3) * 30,
                    'status' => $scheduleStatus,
                    'created_by_user_id' => $admin->getKey(),
                    'updated_by_user_id' => $admin->getKey(),
                    'override_started_at' => $scenario === 'ongoing' && $scheduleIndex === 0 ? now()->subHours(2) : null,
                    'override_ended_at' => $scenario === 'past' && $scheduleIndex === 0 ? now()->subDays(28 + ($index % 10)) : null,
                    'override_started_by_user_id' => $scenario === 'ongoing' && $scheduleIndex === 0 ? $users['proctors'][$index % count($users['proctors'])]->getKey() : null,
                    'override_ended_by_user_id' => $scenario === 'past' && $scheduleIndex === 0 ? $users['instructors'][$index % count($users['instructors'])]->getKey() : null,
                ]);
                $schedule->save();
                $schedules[] = $schedule;
            }
        }

        return $schedules;
    }

    /** @return array{0: string, 1: string, 2: ClassStatus, 3: ExamScheduleStatus} */
    private function scheduleWindow(string $scenario, int $index): array
    {
        return match ($scenario) {
            'ongoing' => [now()->subDay()->toDateString(), now()->addDay()->toDateString(), ClassStatus::Active, ExamScheduleStatus::Scheduled],
            'upcoming' => [now()->addDays(7 + ($index % 5))->toDateString(), now()->addDays(9 + ($index % 5))->toDateString(), ClassStatus::Planned, ExamScheduleStatus::Scheduled],
            'past' => [now()->subDays(30 + ($index % 10))->toDateString(), now()->subDays(28 + ($index % 10))->toDateString(), ClassStatus::Completed, ExamScheduleStatus::Completed],
            'cancelled' => [now()->subDays(14 + ($index % 7))->toDateString(), now()->subDays(12 + ($index % 7))->toDateString(), ClassStatus::Cancelled, ExamScheduleStatus::Cancelled],
        };
    }

    /** @param array<int, Course> $courses
     * @param  array<int, TrainingProvider>  $providers
     * @param  array{admin: User, proctors: array<int, User>, instructors: array<int, User>, students: array<int, User>}  $users
     * @return array<int, TrainingClass>
     */
    private function seedClasses(array $courses, array $providers, array $users): array
    {
        $classes = [];
        $number = 0;
        foreach ($courses as $courseIndex => $course) {
            foreach ([ClassStatus::Planned, ClassStatus::Active, ClassStatus::Completed, ClassStatus::Cancelled] as $status) {
                $number++;
                $startsAt = match ($status) {
                    ClassStatus::Planned => now()->addDays(7 + ($courseIndex % 5)),
                    ClassStatus::Active => now()->subDay(),
                    ClassStatus::Completed => now()->subDays(30 + ($courseIndex % 10)),
                    ClassStatus::Cancelled => now()->subDays(14 + ($courseIndex % 7)),
                };
                $trainingClass = TrainingClass::query()->updateOrCreate(
                    ['class_number' => sprintf('DEMO-CLASS-%03d', $number)],
                    [
                        'course_id' => $course->getKey(),
                        'training_provider_id' => $providers[$courseIndex % (count($providers) - 1)]->getKey(),
                        'proctor_id' => $users['proctors'][$number % count($users['proctors'])]->getKey(),
                        'instructor_id' => $users['instructors'][$number % count($users['instructors'])]->getKey(),
                        'status' => $status,
                        'starts_at' => $startsAt,
                        'ends_at' => $startsAt->copy()->addDays(2),
                        'actual_started_at' => match ($status) {
                            ClassStatus::Active => $startsAt->copy()->addMinutes(15),
                            ClassStatus::Completed => $startsAt->copy()->addMinutes(20),
                            default => null,
                        },
                        'actual_ended_at' => $status === ClassStatus::Completed
                            ? $startsAt->copy()->addDays(2)->subMinutes(10)
                            : null,
                        'notes' => $course->name.' training class for testing the '.strtolower($status->value).' lifecycle.',
                    ],
                );
                $classes[] = $trainingClass;
            }
        }

        return $classes;
    }

    /** @param array<int, TrainingClass> $classes
     * @param  array<int, User>  $students
     */
    private function seedEnrollments(array $classes, array $students): void
    {
        foreach ($classes as $index => $trainingClass) {
            $status = match ($trainingClass->status) {
                ClassStatus::Completed => EnrollmentStatus::Completed,
                ClassStatus::Cancelled => EnrollmentStatus::Withdrawn,
                default => EnrollmentStatus::Enrolled,
            };
            foreach (range(0, 5) as $offset) {
                $student = $students[($index * 2 + $offset) % count($students)];
                Enrollment::query()->updateOrCreate(
                    ['class_id' => $trainingClass->getKey(), 'student_user_id' => $student->getKey()],
                    [
                        'status' => $status,
                        'enrolled_at' => $trainingClass->starts_at?->copy()->subDays(21) ?: now(),
                        'withdrawn_at' => $status === EnrollmentStatus::Withdrawn ? $trainingClass->starts_at : null,
                        'skills_score' => $this->skillsScoreFor($trainingClass->status, $offset),
                    ],
                );
            }
        }
    }

    /**
     * A Proctor records the Skills Score by hand once a trainee's hands-on
     * assessment is observed, so only Active/Completed Classes carry one —
     * and even there, a trainee or two is left blank so the demo still
     * exercises the not-yet-graded input state on the Scores & Reports tab.
     */
    private function skillsScoreFor(ClassStatus $status, int $offset): ?int
    {
        if (! in_array($status, [ClassStatus::Active, ClassStatus::Completed], true)) {
            return null;
        }

        if ($status === ClassStatus::Active && $offset >= 3) {
            return null;
        }

        if ($status === ClassStatus::Completed && $offset === 5) {
            return null;
        }

        // Covers the full 0-100 validation range (NavigationController's
        // min:0/max:100 rule) instead of only ever generating high scores:
        // a low score, a couple of mid-range scores, and the max boundary.
        return match ($offset % 6) {
            0 => 45,
            1 => 68,
            2 => 82,
            3 => 91,
            4 => 100,
            default => 76,
        };
    }

    /** @param array<int, ExamSchedule> $schedules
     * @param  array<int, User>  $students
     */
    private function seedStudentFlows(array $schedules, array $students): void
    {
        $questions = app(StudentSurveyDefinition::class)->questions();

        foreach ($schedules as $schedule) {
            $members = GroupMembership::query()
                ->where('group_id', $schedule->group_id)
                ->where('status', GroupMembershipStatus::Active->value)
                ->orderBy('student_user_id')
                ->get()
                ->map(fn (GroupMembership $membership): ?User => collect($students)->first(fn (User $student): bool => $student->getKey() === $membership->student_user_id))
                ->filter()
                ->values();

            if ($schedule->status === ExamScheduleStatus::Completed) {
                foreach ($members->take(2)->values() as $memberIndex => $student) {
                    $this->seedCompletedSurvey($schedule, $student, $questions);
                    $this->seedAttempt($schedule, $student, ExamAttemptStatus::Submitted, 1, $memberIndex === 0);
                    if ($memberIndex === 0) {
                        // The first trainee passes on the initial attempt and
                        // again on a retake, producing two certificate bundles.
                        $this->seedAttempt($schedule, $student, ExamAttemptStatus::Submitted, 2, true);
                    } else {
                        // The second trainee fails both attempts, proving that
                        // failed submissions do not receive certificates.
                        $this->seedAttempt($schedule, $student, ExamAttemptStatus::Submitted, 2, false);
                    }
                }

                continue;
            }

            if ($schedule->status !== ExamScheduleStatus::Scheduled) {
                continue;
            }

            foreach ($members as $memberIndex => $student) {
                $completed = $memberIndex % 3 !== 2 || $student->getKey() === $students[0]->getKey();
                $survey = StudentSurvey::query()->updateOrCreate(
                    ['student_user_id' => $student->getKey(), 'exam_schedule_id' => $schedule->getKey()],
                    [
                        'status' => $completed ? 'completed' : 'started',
                        'contact_confirmed_at' => now()->subDays(2),
                        'completed_at' => $completed ? now()->subDay() : null,
                    ],
                );

                if ($completed) {
                    foreach ($questions as $question) {
                        $survey->answers()->updateOrCreate(
                            ['question_key' => $question['key']],
                            ['answer' => $this->surveyAnswer($question, $student)],
                        );
                    }
                }
            }

            if ($schedule->start_date?->isPast() && $schedule->end_date?->isFuture()) {
                $student = $members->first();
                if ($student) {
                    $this->seedAttempt($schedule, $student, ExamAttemptStatus::InProgress, 1);
                }

                $expiredStudent = $members->get(1);
                if ($expiredStudent) {
                    $this->seedAttempt($schedule, $expiredStudent, ExamAttemptStatus::Expired, 1, false);
                }
            }
        }
    }

    /** @param array<int, array<string, mixed>> $questions */
    private function seedCompletedSurvey(ExamSchedule $schedule, User $student, array $questions): void
    {
        $survey = StudentSurvey::query()->updateOrCreate(
            ['student_user_id' => $student->getKey(), 'exam_schedule_id' => $schedule->getKey()],
            [
                'status' => 'completed',
                'contact_confirmed_at' => now()->subDays(35),
                'completed_at' => now()->subDays(34),
            ],
        );

        foreach ($questions as $question) {
            $survey->answers()->updateOrCreate(
                ['question_key' => $question['key']],
                ['answer' => $this->surveyAnswer($question, $student)],
            );
        }
    }

    /** @param array{key: string, label: string, type: string, options?: array<int, string>, required: bool} $question */
    private function surveyAnswer(array $question, User $student): string
    {
        if ($question['type'] === 'textarea') {
            return 'The training was well organized and the practical examples were useful.';
        }

        if ($question['type'] === 'text') {
            return 'Ahmed Kotb';
        }

        $options = $question['options'] ?? [];

        return $options[array_key_exists($student->getKey() % max(1, count($options)), $options) ? $student->getKey() % count($options) : 0] ?? '';
    }

    private function seedAttempt(ExamSchedule $schedule, User $student, ExamAttemptStatus $status, int $attemptNumber, bool $correct = true): void
    {
        $startedAt = match ($status) {
            ExamAttemptStatus::Submitted => now()->subDays(25),
            ExamAttemptStatus::Expired => now()->subHours(4),
            ExamAttemptStatus::InProgress => now()->subMinutes(18),
        };
        $attempt = ExamAttempt::query()->updateOrCreate(
            ['exam_schedule_id' => $schedule->getKey(), 'student_user_id' => $student->getKey(), 'attempt_number' => $attemptNumber],
            [
                'exam_id' => $schedule->exam_id,
                'status' => $status,
                'started_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addMinutes($schedule->duration_minutes ?: 90),
                'submitted_at' => $status === ExamAttemptStatus::Submitted ? $startedAt->copy()->addMinutes(72) : null,
            ],
        );

        $attempt->attemptQuestions()->delete();
        $exam = $schedule->exam()->with(['examQuestions.question.options'])->firstOrFail();
        foreach ($exam->examQuestions as $index => $examQuestion) {
            $answer = $status === ExamAttemptStatus::Submitted || $status === ExamAttemptStatus::Expired || $index < 3
                ? ($correct ? $this->answerForQuestion($examQuestion->question) : $this->wrongAnswerForQuestion($examQuestion->question))
                : null;
            ExamAttemptQuestion::create([
                'exam_attempt_id' => $attempt->getKey(),
                'question_id' => $examQuestion->question_id,
                'display_order' => $index + 1,
                'points' => $examQuestion->points ?: $examQuestion->question->default_marks,
                'answer' => $answer,
                'answered_at' => $answer === null ? null : $startedAt->copy()->addMinutes(min($index + 2, 60)),
                'option_order' => $this->optionOrderFor($examQuestion->question, $exam->question_order_mode),
            ]);
        }
    }

    /**
     * Freezes a per-student randomized MCQ option order when the exam
     * shuffles question order, mirroring StartExamAttemptAction::optionOrder()
     * exactly so demo attempts exercise the same option_order field real
     * student exam-taking produces. Static exams and non-MCQ questions keep
     * the option bank's natural display order (null).
     */
    private function optionOrderFor(Question $question, ExamQuestionOrderMode $mode): ?array
    {
        if ($mode !== ExamQuestionOrderMode::Shuffle || $question->type !== QuestionType::Mcq || $question->options->count() < 2) {
            return null;
        }

        return $question->options->shuffle()->pluck('public_id')->all();
    }

    private function answerForQuestion(Question $question): ?string
    {
        return match ($question->type) {
            QuestionType::Mcq => $question->options->firstWhere('is_correct', true)?->public_id,
            QuestionType::TrueFalse => $question->correct_answer_boolean ? 'true' : 'false',
            QuestionType::Input => $question->correct_answer_text,
        };
    }

    private function wrongAnswerForQuestion(Question $question): ?string
    {
        return match ($question->type) {
            QuestionType::Mcq => $question->options->firstWhere('is_correct', false)?->public_id ?: 'invalid-answer',
            QuestionType::TrueFalse => $question->correct_answer_boolean ? 'false' : 'true',
            QuestionType::Input => 'Incorrect demo answer',
        };
    }

    private function seedCertificates(): void
    {
        $issuer = app(IssueCertificateAction::class);
        ExamAttempt::query()
            ->where('status', ExamAttemptStatus::Submitted)
            ->get()
            ->each(fn (ExamAttempt $attempt): ?object => $issuer->execute($attempt));

        // Keep one deterministic revoked certificate for testing status
        // filters, audit/history views, and access restrictions. Only DEMO
        // certificates are touched, so real records are never modified.
        $demoCertificates = Certificate::query()
            ->where('student_wellsharp_id', 'like', 'DEMO-STUDENT-%')
            ->orderBy('certificate_number')
            ->get();

        if ($demoCertificates->isEmpty()) {
            return;
        }

        $demoCertificates->each(fn (Certificate $certificate): bool => (bool) $certificate->update([
            'status' => CertificateStatus::Issued,
            'revoked_at' => null,
            'revocation_reason' => null,
        ]));

        $revoked = $demoCertificates->first();
        $revoked->update([
            'status' => CertificateStatus::Revoked,
            'revoked_at' => now()->subDays(3),
            'revocation_reason' => 'Demo record: certificate revoked for audit and status-filter testing.',
        ]);

        AuditEvent::query()->updateOrCreate(
            ['action' => 'certificate.revoked', 'subject_type' => Certificate::class, 'subject_id' => (string) $revoked->getKey()],
            [
                'actor_user_id' => null,
                'before_state' => ['status' => CertificateStatus::Issued->value],
                'after_state' => $revoked->only(['certificate_number', 'status', 'revoked_at', 'revocation_reason']),
                'occurred_at' => now()->subDays(3),
            ],
        );
    }

    /** @param array{admin: User, proctors: array<int, User>, instructors: array<int, User>, students: array<int, User>} $users
     * @param  array<int, TrainingProvider>  $providers
     * @param  array<int, Course>  $courses
     * @param  array<int, TrainingClass>  $classes
     * @param  array<int, Question>  $questions
     * @param  array<int, Group>  $groups
     * @param  array<int, Exam>  $exams
     * @param  array<int, ExamSchedule>  $schedules
     */
    private function seedAuditEvents(User $admin, array $users, array $providers, array $courses, array $classes, array $questions, array $groups, array $exams, array $schedules): void
    {
        $subjects = collect([$admin, ...$users['proctors'], ...$users['instructors'], ...$users['students'], ...$providers, ...$courses, ...$classes, ...$questions, ...$groups, ...$exams, ...$schedules])->take(220);
        foreach ($subjects->values() as $index => $subject) {
            AuditEvent::query()->updateOrCreate(
                ['action' => $index % 2 === 0 ? 'demo.record.created' : 'demo.record.updated', 'subject_type' => $subject::class, 'subject_id' => (string) $subject->getKey()],
                ['actor_user_id' => $admin->getKey(), 'after_state' => Arr::except($subject->toArray(), ['password', 'remember_token']), 'occurred_at' => now()->subMinutes($index)],
            );
        }
    }

    /** @param array{admin: User, proctors: array<int, User>, instructors: array<int, User>, students: array<int, User>} $users */
    private function seedLoginEvents(array $users): void
    {
        $allUsers = collect([$users['admin'], ...$users['proctors'], ...$users['instructors'], ...$users['students']]);
        foreach ($allUsers->take(20) as $user) {
            LoginEvent::query()->firstOrCreate(
                ['user_id' => $user->getKey(), 'wellsharp_id' => $user->wellsharp_id, 'outcome' => 'success'],
                ['occurred_at' => now()->subHours($user->getKey() % 48), 'ip_address' => '127.0.0.1', 'user_agent' => 'WellSharp Demo Seeder'],
            );
        }
        LoginEvent::query()->firstOrCreate(
            ['user_id' => null, 'wellsharp_id' => 'DEMO-UNKNOWN', 'outcome' => 'invalid_credentials'],
            ['occurred_at' => now()->subHours(3), 'ip_address' => '127.0.0.1', 'user_agent' => 'WellSharp Demo Seeder'],
        );
    }
}
