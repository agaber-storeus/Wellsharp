# Business Rules — WellSharp

Numbered catalog. Confidence: CONFIRMED unless noted. Each rule cites its implementing file.

## Identity / Access

**BR-001** — A user has exactly one active role at a time (`users.current_role_id`); historical role changes are recorded in `role_assignments` but do not grant concurrent multi-role access.
Source: `database/migrations/2026_08_07_000001_create_role_assignments_and_user_profiles_tables.php`, `app/Http/Middleware` (`current.role`).

**BR-002** — A disabled or archived user is logged out on their next request, even mid-session.
Source: `active.user` middleware (docs/architecture.md).

**BR-003** — Any change to a user's role, password, or status invalidates that user's other active sessions via `session_version` increment.
Source: `users.session_version`, `session.version` middleware.

**BR-004** — Login is rate-limited to 5 attempts per normalized WellSharp-ID+IP key per 60 seconds (HTTP 429 on excess).
Source: `docs/api.md` §Authentication, `app/Http/Controllers/Auth/LoginController.php`.

**BR-005** — Every Admin-scoped domain policy (Course, Exam, ExamSchedule, Group, Question, TrainingProvider, User, Audit) grants Admin unconditional access and denies every ability to all other roles by default.
Source: `app/Policies/*.php` (`before()` pattern).

## Proctor's ID (exam-control credential)

**BR-006** — Only the Proctor role owns a Proctor's ID (`exam_control_credentials.control_id`); it is generated automatically the moment a user's active role becomes Proctor, and revoked the moment they leave the Proctor role (Instructor included — Instructors never own one). A Proctor starts/ends a Class directly, with no credential entry. An Instructor must supply a Proctor's ID belonging to a currently active, eligible Proctor; their own credential (they don't have one), another Instructor's, or a disabled/archived Proctor's is rejected with a validation error.
Source: `app/Actions/Users/CreateUserAction`, `app/Actions/Users/ChangeUserRoleAction`, `app/Services/ProctorIdGenerator`, `app/Actions/Exams/ControlOperationalExamAction::executeManual`, `app/Services/ProctorIdVerifier`.

**BR-007** — *(Superseded 2026-08-23, see BR-007a)* Previously: any active Proctor or Instructor could control (start/end) any Class.

**BR-007a** — Every Class has exactly one assigned Proctor (`classes.proctor_id`) and one assigned Instructor (`classes.instructor_id`), enforced as required on all Class/Exam-Schedule creation surfaces (Admin Classes form, Exam Schedule form, and the Exam form's inline first-schedule bundle). A Proctor/Instructor may only view, control (start/end), view Student passwords for, record Skills Score on, or view/release exam-attempt reports for a Class assigned to them — not any Class. Admin is unrestricted. `TrainingClass::scopeVisibleTo()` is the single query-level enforcement point; `TrainingClassPolicy::view()/control()/viewStudentPasswords()`, `EnrollmentPolicy::updateSkillsScore()`, and `OperationalReportingService::canViewAttempt()` are the corresponding per-action policy checks. Direct URL/ID access to another staff member's Class returns 403, not just omission from a list (IDOR-protected).
Source: `app/Models/TrainingClass.php` (`scopeVisibleTo`), `app/Policies/TrainingClassPolicy.php`, `app/Policies/EnrollmentPolicy.php`, `app/Services/OperationalReportingService.php`. Both DB columns are nullable (existing Classes created before this rule cannot be deterministically backfilled) — required-ness is enforced at the Form Request layer, not a DB constraint. The pre-existing `class_staff_assignments` table/`ClassStaffAssignment` model/`AssignClassStaffAction` were never wired to any authorization check and are now fully superseded by `proctor_id`/`instructor_id` — left in place as unused legacy code, not deleted.

## Exam / Class Lifecycle (core domain rule)

**BR-008** — An Exam and a Class are the same operational record viewed through two role-specific labels. Saving an Exam Schedule creates or reuses (never lets the user pick) a `classes` row.
Source: `app/Services/ExamClassSynchronizer::sync()`.

**BR-009** — `ExamClassSynchronizer` matches an existing Class by `course_id` + `training_provider_id` + exact date match on `starts_at`/`ends_at` before creating a new one; multiple schedules share a Class only when Subject, provider, and calendar dates match. Changing a schedule's provider updates its linked Class if unshared, or relinks/creates a provider-matching Class if other schedules still use the old Class.
Source: `app/Services/ExamClassSynchronizer::sync()`.

**BR-010** — A Class can only be started from `planned` and only ended from `active`; calling start on an already-`active` Class or end on an already-`completed` Class is a silent no-op (`changed: false`), not an error. Any other status transition attempt throws a validation error.
Source: `app/Actions/Exams/ControlOperationalExamAction::execute()`.

**BR-011** — Automatic (scheduler-driven) start only fires once `starts_at <= now()` and skips any Class that has a linked `start_mode=manual` schedule; automatic end still fires for any active Class once `ends_at <= now()`. Manual start/end by staff can happen **before** the configured time (early start/end is allowed for humans, not for the scheduler).
Source: `ControlOperationalExamAction::execute()` (`source === 'automatic'` guard clauses).

**BR-012** — When a Class starts, every `scheduled`-status ExamSchedule under it is stamped; if the start was manual, `override_started_at`/`override_started_by_user_id` are recorded (this override is what lets students start attempts early — see BR-016).
Source: `ControlOperationalExamAction::execute()` (start branch).

**BR-013** — When a Class ends: every `scheduled` ExamSchedule under it is marked `completed`; every still-`in_progress` ExamAttempt under those schedules is force-submitted (`status=submitted`, `submitted_at=<end time>`); certificate issuance is attempted for each force-submitted attempt.
Source: `ControlOperationalExamAction::execute()` (end branch) — this is the highest-blast-radius single action in the codebase (see PROJECT_BRAIN.md §26).

**BR-014** — The scheduler command `wellsharp:process-exam-schedules` (intended to run every minute) drives eligible automatic start/end transitions using the exact same transition action as manual staff control, just with `source='automatic'` and no actor attributed. A Class containing any manual-start schedule is not automatically started.
Source: `app/Console/Commands/ProcessExamSchedules.php`.

## Student Assessment Flow

**BR-015** — A student may only start an attempt for an Exam Schedule if they hold an `active`-status `GroupMembership` in that schedule's Group.
Source: `app/Actions/Exams/StartExamAttemptAction::assertStudentCanStart()`.

**BR-016** — A `start_mode=manual` schedule remains blocked until staff control records `override_started_at`. For an automatic schedule without an override, a student can only start within the schedule's `start_date`–`end_date` window (`start_date` future → blocked; `end_date` end-of-day past → blocked).
Source: `StartExamAttemptAction::assertStudentCanStart()`.

**BR-017** — A student who already has a `submitted` attempt for a schedule cannot start another attempt for it — one finished attempt per schedule per student, no retakes via the normal flow.
Source: `StartExamAttemptAction::execute()`.
Note: README mentions demo data with "passing and failing retakes" — reconcile before assuming retakes are impossible in all paths; this rule applies to the `StartExamAttemptAction` path specifically.

**BR-018** — An `in_progress` attempt whose `expires_at` has passed is transparently expired and replaced by a fresh attempt on the student's next start call (not surfaced as an error to the student at start time).
Source: `StartExamAttemptAction::execute()`.

**BR-019** — Attempt duration (`expires_at`) is computed as `started_at + schedule.duration_minutes` — duration lives on the **schedule**, but the clock starts per-student when they personally start the attempt, not at the schedule's global start time.
Source: `StartExamAttemptAction::expiresAt()`.

**BR-020** — Question order for an attempt is decided once, at attempt-creation time: `static` mode reuses the Exam's stored `display_order`; `shuffle` mode produces and persists a per-student random order into `exam_attempt_questions`.
Source: `StartExamAttemptAction::orderQuestions()`.

**BR-020a** — MCQ answer-option order follows the same toggle: under `shuffle` mode, each MCQ question's options are independently randomized per student and frozen into `exam_attempt_questions.option_order` (a JSON array of option `public_id`s) at attempt-creation time; under `static` mode (or for non-MCQ questions, or MCQ questions with fewer than 2 options), `option_order` stays `null` and rendering falls back to the option bank's own `display_order`.
Source: `StartExamAttemptAction::optionOrder()`, `ExamAttemptQuestion::orderedOptions()`, `resources/views/student/exam.blade.php`. Added 2026-08-16 as a follow-up to the question-order review (BR-020) — see BUSINESS_FLOWS.md and the migration `2026_08_16_000001_add_option_order_to_exam_attempt_questions.php`.

**BR-021** — `exam_attempt_questions` is a **snapshot** (question set, order, points) taken at attempt-start time; later edits to the Exam's question list do not retroactively change an already-started attempt.
Source: schema (`exam_attempt_questions` has its own `display_order`/`points`, distinct from `exam_questions`).

**BR-022** — An answer to a True/False question must literally be the string `"true"` or `"false"`; an MCQ answer must equal an existing option's `public_id` for that question; empty string clears a saved answer. Autosave is only accepted while `status = in_progress` and unexpired.
Source: `docs/api.md` §`PATCH /student/attempts/{attempt}/questions/{attemptQuestion}/answer`.

**BR-023** — Submitting an attempt is only valid while `status = in_progress`; if `expires_at` has already passed at submit time, the attempt is instead flipped to `expired` and submission is rejected.
Source: `app/Actions/Exams/SubmitExamAttemptAction::execute()`.

## Scoring

**BR-024** — Score = `round((earned_points / possible_points) * 100, 2)`, where a question's `earned_points` = its `points` if correct, else 0; `possible_points` sums every attempt question's `points` (falling back to `question.default_marks` or `1` if null).
Source: `app/Services/ExamScoringService::calculate()`.

**BR-025** — Pass/fail: `score >= exam.passing_score` (passing_score is an unsigned tiny-int on `exams`, i.e. a percentage threshold 0–255 in principle, practically 0–100).
Source: `ExamScoringService::calculate()`.

**BR-026** — Correctness rules per question type: MCQ — selected option's `public_id` matches an option flagged `is_correct`; True/False — lowercased answer string equals `"true"`/`"false"` matching `correct_answer_boolean`; Input (free text) — `Question::normalizeText(answer) === Question::normalizeText(correct_answer_text)` (a normalized/case-insensitive compare).
Source: `ExamScoringService::isCorrect()`.

**BR-027** — Scoring is **re-computed, not just read**, at both submission time and certificate-issuance time (issuance re-runs `ExamScoringService::calculate()` rather than trusting the stored `passed` column) — so any Question/answer-key edit between submit and issuance changes the outcome.
Source: `SubmitExamAttemptAction`, `IssueCertificateAction::execute()`.
Confidence: CONFIRMED behavior; flag as a **risk** if a future request assumes score is immutable post-submission — it currently is not, until a certificate is actually issued.

## Certificates

**BR-028** — A certificate is only ever created for a `submitted` attempt whose **effective score** (BR-035) is `>=` the exam's passing score when re-evaluated at issuance time; failing/unsubmitted attempts never produce one.
Source: `IssueCertificateAction::execute()`.

**BR-029** — Certificate issuance is idempotent per attempt: `exam_attempt_id` is unique on `certificates`; a second issuance attempt for the same attempt returns the existing certificate (and ensures its four documents exist) rather than duplicating.
Source: `certificates` migration (`unique` on `exam_attempt_id`), `IssueCertificateAction::execute()`.

**BR-030** — Every issued certificate gets exactly four `CertificateDocument` rows (Full Certificate, Knowledge Assessment Report, Completion Card Front, Completion Card Back), created via `firstOrCreate` keyed on `(certificate_id, type)`. The migration adding Full Certificate backfills that document for existing certificates.
Source: `IssueCertificateAction::ensureDocuments()`, `CertificateDocumentType` enum.

**BR-031** — Certificates carry a **denormalized snapshot** of student name/email/ID, exam name/code, subject name, class number, group name, provider name at issuance time — later renames of the student/exam/etc. do not retroactively change an issued certificate.
Source: `certificates` migration columns, `IssueCertificateAction::execute()`.

**BR-032** — Certificate expiration is `issued_at + exams.certificate_validity_years` (nullable, per-Exam; falls back to the project's original 2-year default when an Exam has none configured). Expiration is computed once, at issuance, and snapshotted onto the certificate like the rest of BR-031 — changing an Exam's `certificate_validity_years` afterward never alters certificates already issued under the old value.
Source: `IssueCertificateAction::execute()`/`expirationDate()` (`$issuedAt->copy()->addYears($validityYears ?? 2)`), `exams.certificate_validity_years` (migration `2026_08_23_000001`).

**BR-033** — Certificate revocation (`CertificateStatus::Revoked`, `revoked_at`, `revocation_reason` columns) has no implementing workflow — schema-ready, feature-absent. Treat any "revoke a certificate" request as new feature work.
Source: absence of any Action/controller writing `CertificateStatus::Revoked`; confirmed against README "Not implemented yet" list.

**BR-034** — Certificate viewing: Admin can view all; Student can view only their own; active Proctor/Instructor can view certificates within their operational scope.
Source: `docs/architecture.md` §Security boundaries.

**BR-035** — `enrollments.skills_score` is a **manual override of the trainee's final/effective percentage**, not a second/parallel score. `effective_score = skills_score ?? knowledge_exam_score`; `passed = effective_score >= exam.passing_score`. It overrides in both directions — it can turn a failing Knowledge Exam into a pass, or a passing one into a fail. `null` means "no override, use the Knowledge Exam result"; it is a distinct, legal value from `0` (a real override). The raw Knowledge Exam result (`exam_attempts.score`/`passed`) is never modified by an override.
Source: `App\Services\EffectiveScoreService::resolve()`, the single canonical implementation of this formula.

**BR-036** — Setting or clearing a Skills Score reconciles certificate eligibility through the real `IssueCertificateAction` (never an ad-hoc write): `App\Actions\Classes\UpdateEnrollmentSkillsScoreAction` re-runs it against the enrollment's latest attempt after every change. An override that newly clears the passing threshold issues a certificate that didn't exist before. An override that drops a trainee below the threshold does **not** retract or modify an already-issued certificate — certificates are immutable snapshots once issued (BR-031) and this domain has no implemented revocation workflow (BR-033), so an already-issued certificate is left exactly as it was; only *future* eligibility decisions (e.g. a later re-issuance attempt) see the trainee as failing.
Source: `App\Actions\Classes\UpdateEnrollmentSkillsScoreAction`, `IssueCertificateAction::execute()`.

## Question Bank

**BR-035** — Question correct-answer fields are excluded from the model's default JSON serialization and are only read server-side during scoring — never sent to the student's browser.
Source: `docs/architecture.md` §Security boundaries (Question model hidden attributes).

**BR-036** — Excel question-bank import requires the PHP `ext-zip` extension; CSV import is offered as a fallback when that extension is unavailable.
Source: README §Installation.

## State Machines

```text
User (UserStatus):        active → disabled
                           active → archived
                           (disabled/archived are terminal in the UI paths observed; no "reactivate" traced in this pass)

Course (CourseStatus):    active ⇄ retired

Exam (ExamStatus):        draft → published → archived

ExamSchedule (ExamScheduleStatus):
                           scheduled → completed   (via Class end, BR-013)
                           scheduled → cancelled    (via CancelExamScheduleAction — not traced in depth this pass)

TrainingClass (ClassStatus):
                           planned → active   (BR-010, BR-011, BR-012)
                           active  → completed (BR-010, BR-011, BR-013)
                           planned/active → cancelled (via CancelTrainingClassAction — not traced in depth this pass)

Enrollment (EnrollmentStatus):
                           enrolled → withdrawn
                           enrolled → completed

ExamAttempt (ExamAttemptStatus):
                           in_progress → submitted   (BR-023, or forced by BR-013)
                           in_progress → expired      (BR-018, BR-023)

Certificate (CertificateStatus):
                           issued → revoked   [NOT IMPLEMENTED — BR-033]

Group / GroupMembership / ExamGroupAssignment:
                           active ⇄ archived (Group)
                           active → removed (Membership / ExamGroupAssignment)
```

## Recoverable Password Management

**BR-037** — Every account's login password is stored hashed (`users.password`, `hashed` cast) and verified via `Hash::check()` at login. This remains the authentication credential.
Source: `app/Models/User.php` casts, `app/Actions/Auth/AuthenticateUserAction.php`.

**BR-038** — Additionally, account creation and password-update workflows keep a separately **encrypted** (not hashed — reversible) copy of the plaintext password in `users.password_ciphertext` (`Crypt::encryptString()`, decryptable with the current or configured previous app keys). This applies to Admin, Proctor, Instructor, and Student accounts. The nullable column is not backfilled by this branch, so legacy or nonstandard records may have no recoverable copy and return `404` until a new password is set.
Source: `User::setPasswordAndCiphertext()`, called from `CreateUserAction`, `UpdateUserAction`, `DemoDataSeeder::user()`.
**Why this exists**: Users cannot self-reset passwords, and Admins must be able to recover credentials for account management; operational staff also need to hand Students working credentials at check-in. A plain-text `password` column was explicitly rejected. The encrypted-copy approach keeps the authentication path hash-only while allowing policy-controlled recovery on demand.

**BR-039** — An Admin may reveal any account's password. An **active** Proctor/Instructor may reveal a password only when the target account currently has the Student role; operational staff cannot reveal staff-account passwords.
Source: `app/Policies/UserPolicy::viewPassword()`.

**BR-040** — Revealing a password is a live decrypt-and-return action (`POST .../reveal-password`, JSON `{password}`), not a value ever embedded in a page's HTML/JSON by default — the Admin user-show page, user table JSON, and the Proctor/Instructor class-roster modal all carry a reveal *URL*, never the password itself, until the staff member explicitly clicks "Reveal"/"Reveal password".
Source: `app/Http/Controllers/Admin/UserController::revealPassword()`, `app/Http/Controllers/Operational/NavigationController::revealStudentPassword()`, `resources/views/admin/users/show.blade.php`, `public/js/proctor-class-modal-laravel.js`.

**BR-041** — Every reveal is written to the audit log with the actor and their role. Student reveals use `student.password_viewed`; Admin reveals of staff accounts use `user.password_viewed`.
Source: same controller methods as BR-040, via `AuditRecorder`.

**BR-042** — Changing any account's password re-encrypts the new value into `password_ciphertext`, so the recoverable copy matches the current login password. Changing roles preserves the existing ciphertext because recoverability applies to every role.
Source: `UpdateUserAction::execute()`, `ChangeUserRoleAction::execute()`.

**BR-043** — The Class Dashboard may request all enrolled Student passwords in one explicit `POST /{role}/classes/{trainingClass}/student-passwords` call. The policy limits it to the assigned active Proctor/Instructor; the response is `no-store`, contains only Student public IDs and nullable recovered passwords, and writes one roster-level `student_passwords.class_roster_viewed` audit event without password values.
Source: `NavigationController::classStudentPasswords()`, `TrainingClassPolicy::viewStudentPasswords()`, `ClassRosterStudentPasswordsTest`.

**BR-044** — `/iadc_certification` and `/verify/certificates/{certificate_number}` are public, unauthenticated lookup routes. A certificate-number lookup redirects to its verification page; an Instructor WellSharp ID lookup lists that instructor's certificate snapshots. Verification exposes certificate status and selected snapshot fields but not student email or WellSharp ID.
Source: `CertificateLookupController`, `CertificateVerificationController`, public Blade views, and `CertificateManagementTest`.

**BR-045** — Every generated template page clears its sample QR artwork. Completion Card Back PDFs and both pages of the two-page Full Certificate replace it with a generated QR pointing to the public certificate-number verification route. PDF responses use no-cache headers.
Source: `CertificatePdfService`, `CertificateQrCodeService`, `CertificateDocumentController`.

**BR-046** — Adding an active Student to a Group immediately creates or reactivates an Enrollment for every non-completed/non-cancelled Class already linked to that Group's Exam Schedules, so operational rosters update without re-saving the schedule. This synchronization only adds/reactivates; removing Group membership does not withdraw an existing Enrollment.
Source: `AddStudentsToGroupAction`, `SyncStudentGroupsAction`, `SyncGroupEnrollmentsAction::executeForGroup()`, `ClassDashboardRosterTest`.

**BR-047** — Imported question markup is preserved in `questions.question_text`, but user-facing question lists, Exam screens, and scoring/report breakdowns use `display_question_text`, which decodes entities, strips HTML tags/non-breaking spaces, and collapses whitespace.
Source: `Question::getDisplayQuestionTextAttribute()`, `QuestionController`, `ExamScoringService`, Student Exam view.

## Needs Business Confirmation

- ~~Whether `class_staff_assignments` / `AssignClassStaffAction` actually restricts *who* can be assigned/visible for a Class~~ — **Resolved 2026-08-23**: it did not (never wired to any authorization check); see BR-007a. The table/model/Action remain as unused legacy code — removing them is a follow-up cleanup decision, not yet done.
- Whether `ExamGroupAssignment` (`exam_group_assignments`) is a prerequisite gate before a Group can be scheduled via `exam_schedules.group_id`, or an independent audit/record-keeping trail. Both exist; relationship not fully traced.
- Cancellation side-effects (`CancelExamScheduleAction`, `CancelTrainingClassAction`) — not read in this pass; before modifying cancellation behavior, read those two files first.
