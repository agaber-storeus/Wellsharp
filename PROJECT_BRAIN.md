# Project Brain — WellSharp

> Baseline for future work in this repository. Confidence tags: **CONFIRMED** (verified in active code), **LIKELY** (strong indicators, not fully traced), **UNCERTAIN** (could not confirm), **CONFLICT** (code disagrees with itself/docs).
>
> This file complements, not replaces, [docs/architecture.md](docs/architecture.md), [docs/api.md](docs/api.md), and [docs/environment.md](docs/environment.md), which are already accurate and detailed — read those first for architecture/API/env specifics. This file adds the business-rule catalog, database map, state machines, and terminology that weren't previously written down. See also [BUSINESS_RULES.md](BUSINESS_RULES.md), [DATABASE_MAP.md](DATABASE_MAP.md), and [BUSINESS_FLOWS.md](BUSINESS_FLOWS.md).

## 1. Executive Summary

WellSharp is a single-tenant **Laravel 12** web application (PHP 8.2+, Blade + Alpine.js, session auth, no separate REST/bearer API) that runs training-provider exam operations end to end: Subjects (stored as `courses`) → Question banks → Exams → Exam Schedules (linked to Student Groups and providers) → Classes (the operational/lifecycle twin of an Exam Schedule) → Student attempts → Scoring → Certificates (4 documents per pass, with public certificate-number verification). **CONFIRMED** (docs/architecture.md, migrations, actions read directly).

## 2. Product Purpose

A training-provider back office + assessment platform: staff configure courses/exams/question banks, schedule exams for groups of students at specific training providers, proctors/instructors run the live session (start/end the Class), students take the exam online, and passing students receive an auto-issued, PDF-downloadable certificate bundle. Not a marketplace/ecommerce/CRM — it is closest to an **LMS/testing-center back office**. **CONFIRMED**.

## 3. Technology Stack

| Layer | Technology | Confidence |
|---|---|---|
| Language/Runtime | PHP 8.2+ | CONFIRMED (composer.json) |
| Framework | Laravel 12.60+ | CONFIRMED |
| DB (prod target) | MySQL 8+ | CONFIRMED (README, docs/environment.md) |
| DB (tests) | SQLite in-memory | CONFIRMED |
| Session | database driver, `sessions` table | CONFIRMED |
| Cache | database driver | CONFIRMED |
| Queue | database driver, but **no domain jobs are queued** — everything domain-related runs synchronously in the request/console command | CONFIRMED (docs/architecture.md, docs/environment.md) |
| Frontend | Blade + Alpine.js + Tailwind v4 + Vite | CONFIRMED (package.json, resources/views) |
| Other JS dep | leaflet (map for provider coordinates) | CONFIRMED (package.json) |
| PDF | phpoffice/phpspreadsheet (Excel import) + a `CertificatePdfService` for certificate PDFs | CONFIRMED |
| Auth | Laravel session guard only — **no Sanctum/Passport, no `routes/api.php`, no bearer tokens** | CONFIRMED |
| Testing | PHPUnit 11, Pest not used, 20 Feature test files + 1 Unit test file | CONFIRMED |

No payment gateway, no SMS/WhatsApp/push notification provider, no cloud storage integration configured beyond optional S3 env vars (unused), no external SDKs. **CONFIRMED** — this is a self-contained system with zero third-party business integrations today.

## 4. Architecture

See [docs/architecture.md](docs/architecture.md) for the full request lifecycle, layer responsibilities, and security boundaries — it is accurate and should be treated as authoritative. Key structural facts not to relitigate:

- Layered as Routes → Middleware → Form Requests → Controllers → **Actions** (transactional workflows, `app/Actions/**`) → **Services** (cross-model logic, `app/Services/**`) → Eloquent Models → DB.
- Public route identifiers are ULIDs (`public_id` column) via `HasPublicUlid`, not sequential integer IDs — except some Admin JSON payloads that intentionally expose numeric IDs for table/form operations.
- `RequestCorrelationId` middleware stamps every request/log with a correlation ID.

## 5. User Roles

Four roles, stored in `roles` table with string keys, referenced via `App\Models\Role::ADMIN|PROCTOR|INSTRUCTOR|STUDENT`. A user has one `current_role_id` (single active role at a time) plus a `role_assignments` history table recording role changes with `started_at`/`ended_at`. **CONFIRMED** (`database/migrations/0000_12_31_000000_create_roles_table.php`, `2026_08_07_000001_...role_assignments...`, `app/Models/Role.php`).

| Role | Purpose | Create | Read | Update | Delete | Key Restrictions |
|---|---|---|---|---|---|---|
| **Admin** | Full back-office configuration and oversight | Users, Providers, Subjects/Courses, Question banks, Exams, Exam Schedules, Groups, Classes (implicitly via schedule sync), Certificates (view/issue-adjacent) | Everything | Everything | Archive/disable (soft — see §8) | Every Policy's `before()` grants Admin unconditional access (`return $actor->isAdmin() ? true : null;`) |
| **Proctor** | Runs live Class sessions, reporting | Nothing admin-side | Classes assigned to them only (`classes.proctor_id`), reports, certificates (operational scope) | Class lifecycle via exam-control (`start`/`end`) directly on their own assigned Classes — owns a Proctor's ID but does not need to enter it | Nothing | `TrainingClassPolicy::control()`/`view()` (`ControlOperationalExamAction`/`ProctorIdVerifier`), scoped via `TrainingClass::scopeVisibleTo()` |
| **Instructor** | Same operational surface as Proctor | same as Proctor | Classes assigned to them only (`classes.instructor_id`) | Class lifecycle via exam-control (`start`/`end`) on their own assigned Classes, gated on entering an active, eligible Proctor's ID (Instructors never own one themselves) | Nothing | `TrainingClassPolicy::control()` — own/another Instructor's credential always rejected; own/another Instructor's *Class* also rejected (`ControlOperationalExamAction`/`ProctorIdVerifier`), scoped via `TrainingClass::scopeVisibleTo()` |
| **Student** | Takes exams, views own certificates | Survey answers, attempt answers (autosave) | Own enrollments/attempts/certificates only | Own attempt answers while `in_progress` and unexpired | Nothing | `EnrollmentPolicy::view()` restricted to `student_user_id === actor`; attempt ownership checked in Actions (`abort_unless($attempt->student_user_id === $student->getKey(), 403)`) |

Role crossing is blocked by the `current.role:<role>` middleware (docs/architecture.md) — a signed-in user cannot browse another role's workspace even if role-history exists. **CONFIRMED**.

## 6. Permissions (Policy Layer)

Almost every domain Policy (`CoursePolicy`, `ExamPolicy`, `ExamSchedulePolicy`, `GroupPolicy`, `QuestionPolicy`, `TrainingProviderPolicy`, `UserPolicy`, `AuditPolicy`) follows the same pattern: `before()` grants Admin everything, and every other ability (`viewAny`, `view`, `create`, `update`, `delete`) explicitly returns `false` for non-Admins. **CONFIRMED** — read all 10 files in `app/Policies/`. This means: **these resources are Admin-only at the policy layer; Proctor/Instructor/Student never reach them via `authorize()`.**

The one policy with real multi-role logic is `TrainingClassPolicy`:
- `viewAny`: Proctor, Instructor, or Student.
- `view`: Proctor/Instructor see only a Class assigned to them (`proctor_id`/`instructor_id`, via `TrainingClass::scopeVisibleTo()`); Student sees a Class only if they have an `enrolled`-status enrollment in it.
- `control` (start/end), `viewStudentPasswords`: only the Class's own assigned Proctor/Instructor (BUSINESS_RULES.md BR-007a, added 2026-08-23 — was previously "any active one," see BR-007).
- `create`/`update`/`delete`: always `false` for Proctor/Instructor/Student (Classes are never directly authored by them — see §9 core rule); Admin bypasses via `before()` and does directly author Classes (`TrainingClassController::store/update`, and the Exam Schedule flow via `ExamClassSynchronizer::sync()`).

`AuditPolicy` denies `viewAny`/`view` to everyone including via the general ability check — audit is Admin-only through `before()`, with no operational role given access. **CONFIRMED**.

Operational role restriction elsewhere is enforced by **route middleware** (`current.role:proctor`, etc.) rather than fine-grained policies — e.g. Question/Exam/Group CRUD is only reachable via `/admin/*` routes, so the "always false" policy answers for non-Admins are largely a defense-in-depth backstop. **LIKELY** (inferred from route file structure; not exhaustively traced per-controller).

## 7. Main Modules

1. **Identity & Access** — Users, Roles, Role Assignments, Profiles, Session/login events, Audit events.
2. **Reference Data** — Training Providers, Course Levels/Stacks/Supplements/Languages (the "Subject configuration" reference tables).
3. **Subjects (Courses)** — `courses` table; UI label is "Subject", code/model/table is `Course`.
4. **Question Bank** — Questions + Options per Course, with Excel/CSV import, difficulty/type taxonomy, image support.
5. **Exams** — reusable assessment definitions scoped to a Course, with ordered Questions (`exam_questions`), draft/published/archived lifecycle.
6. **Groups** — Student Groups with membership history (`group_memberships`), used as the audience for Exam assignment.
7. **Exam Scheduling** — `exam_schedules` links an Exam + Group to a date/time window and a synchronized operational `classes` row.
8. **Classes (Operational)** — the shared Exam/Class record Proctors/Instructors start and end; source of the student-facing "Class" concept.
9. **Enrollment** — Students enrolled/withdrawn/completed per Class.
10. **Student Assessment Flow** — survey, exam instructions, attempt start, per-question autosave, submit, scoring.
11. **Certificates** — auto-issued on passing submission; 4 documents (Full Certificate, Knowledge Assessment Report, Completion Card front/back) rendered to PDF, with a public lookup/verification route and dynamic verification QRs on Completion Card Back and both official Full Certificate pages.
12. **Reporting/Export** — Operational reporting dashboards and certificate CSV export for Proctor/Instructor.

## 8. Database Entities

See [DATABASE_MAP.md](DATABASE_MAP.md) for the full table-by-table breakdown and ER diagram.

## 9. Business Rules

See [BUSINESS_RULES.md](BUSINESS_RULES.md) for the numbered catalog (BR-001…).

Core rule worth stating up front because it shapes nearly every workflow (**CONFIRMED**, `docs/architecture.md` + `app/Services/ExamClassSynchronizer.php`):

> **Exam and Class are two labels for one operational record.** Admin authors an "Exam" (definition) and an "Exam Schedule" (date window + Group). Saving that schedule auto-creates-or-reuses a `classes` row (matched by course + start/end date, else created) via `ExamClassSynchronizer::sync()`. Proctor/Instructor/Student interfaces only ever see the "Class" label for this same row. There is no manual bridge table and no way for Admin to pick an existing Class — it's always synchronized from the schedule.

## 10. Main Workflows

See [BUSINESS_FLOWS.md](BUSINESS_FLOWS.md) for Mermaid diagrams of: Exam authoring → scheduling → sync; Student assessment attempt lifecycle; Class start/end (manual and automatic) with certificate issuance side-effect.

## 11. Status Machines

All enums live in `app/Enums/*.php` as PHP backed enums (string-valued), stored as plain strings in DB columns (no DB-level enum type observed in migrations — status columns are `string(24)`). Full transition tables are in [BUSINESS_RULES.md](BUSINESS_RULES.md) §State Machines.

| Entity | States |
|---|---|
| `User` (`UserStatus`) | active → disabled / archived |
| `Course` (`CourseStatus`) | active ⇄ retired |
| `Exam` (`ExamStatus`) | draft → published → archived |
| `ExamSchedule` (`ExamScheduleStatus`) | scheduled → completed / cancelled |
| `TrainingClass` (`ClassStatus`) | planned → active → completed / cancelled |
| `Enrollment` (`EnrollmentStatus`) | enrolled → withdrawn / completed |
| `ExamAttempt` (`ExamAttemptStatus`) | in_progress → submitted / expired |
| `Certificate` (`CertificateStatus`) | issued → revoked (**revocation workflow itself is NOT implemented** — README says so explicitly; the enum/column exist but no action sets `revoked`) |
| `Group` (`GroupStatus`) | active ⇄ archived |
| `GroupMembership`/`ExamGroupAssignment` (shared shape) | active → removed |
| `ProviderStatus` | active / inactive / archived |
| `StaffAssignmentStatus` | active / ended |

**UNCERTAIN / gap**: Certificate revocation — enum case `Revoked` and `revoked_at`/`revocation_reason` columns exist (migration `2026_08_10_000002_create_certificates_table.php`), but no Action/controller sets a certificate to `Revoked` (README §Not implemented yet confirms this). Treat any future "revoke certificate" request as **net-new feature work**, not a bug fix.

## 12. Financial Logic

**None exists.** No price, tax, commission, wallet, invoice, payment, or refund concept anywhere in the schema, models, or code. `decimal` columns present (`score`, `points`) are assessment scoring values, not money. **CONFIRMED** by absence — grepped models/migrations/enums for financial terms found nothing.

## 13. Authentication

- WellSharp ID + password login (`POST /login`), Laravel session guard, no MFA/OTP/social login. **CONFIRMED** (docs/api.md).
- Passwords: Laravel `hashed` cast, never serialized in payloads.
- Rate limiting: 5 attempts / normalized ID+IP key / 60s → HTTP 429.
- `session_version` column on `users` + `session.version` middleware: bumping it (on role/password/status change) invalidates all other active sessions for that user — a forced-logout mechanism. **CONFIRMED** (docs/architecture.md, `users.session_version`).
- `active.user` middleware logs out disabled/archived users mid-session.
- Separate from login: the **Proctor's ID** (`exam_control_credentials` table, one `control_id` per Proctor — never an Instructor) gates the start/end-Class action — this is a second, narrower "who is physically running this session" credential, not a login mechanism. **CONFIRMED**.
- **Deliberate business exception, confirmed 2026-09-03**: Application account-creation and password-update paths store an app-key-encrypted, reversible copy (`users.password_ciphertext`) for every role, separate from the hash used for login. The nullable column is not backfilled, so legacy/nonstandard records may lack a recoverable copy. Admins may reveal passwords for any account; active Proctors/Instructors may reveal Student passwords only. `UserPolicy::viewPassword()` gates access and every successful reveal is audited (`student.password_viewed` or `user.password_viewed`). See BUSINESS_RULES.md BR-037..BR-042. Plaintext is never stored directly or embedded in initial page data.

## 14. API Architecture

No REST/bearer API. Session-authenticated, same-origin JSON endpoints consumed by Alpine.js, fully documented in [docs/api.md](docs/api.md) and `openapi/openapi.yaml`. Treat that file as the API source of truth; do not re-derive it here.

## 15. Admin Dashboard

CRUD-heavy Blade admin under `/admin/*` for: Users, Students, Providers, Subjects (Courses), Subject configuration reference values (levels/stacks/supplements/languages), Groups, Questions, Exams, Exam Schedules, Classes, Certificates. Table endpoints follow a consistent `{search, filters, sort, direction, page[, per_page]} → {data, meta}` contract (docs/api.md). **CONFIRMED**.

The Exam create/edit question picker (`resources/views/admin/exams/_form.blade.php`) additionally has a client-side "Auto-select questions" tool (Alpine.js, added 2026-08-16): total-count or per-difficulty-count random selection from the Subject's active question bank, with an optional type filter and a replace-vs-add-to-selection toggle. It runs entirely in the browser against the same already-rendered question list used for manual selection — no new route, endpoint, or validation rule was added; `SaveExamAction`/`StoreExamRequest` are unchanged, since the tool only pre-fills the existing `question_ids[]`/`display_orders[]` form fields.

## 16. Mobile / Frontend

No mobile app or separate SPA. Server-rendered Blade + Alpine.js only. Student flow screens: confirm contact info → survey → instructions → start attempt → per-question autosave → submit → report/certificate. **CONFIRMED** (docs/architecture.md Assessment flow, `routes/student.php`).

## 17. Notifications

**None implemented.** `MAIL_MAILER` defaults to `log`; docs/environment.md explicitly states "No mail notification workflow is implemented." No SMS/push/WhatsApp. **CONFIRMED**.

## 18. Integrations

None. See §3 — no payment, SMS, cloud storage (beyond optional unused S3 env), or third-party SDK integration exists in the codebase today. **CONFIRMED**.

## 19. Background Jobs

One scheduled console command: `wellsharp:process-exam-schedules` (`app/Console/Commands/ProcessExamSchedules.php`), intended to run every minute via Laravel's scheduler (not configured to run itself — deployment must wire up `php artisan schedule:run` via cron). It:
- Finds `planned` Classes whose `starts_at <= now()` and auto-starts them.
- Finds `active` Classes whose `ends_at <= now()` and auto-ends them (which also force-submits any still-`in_progress` attempts and triggers certificate issuance for passers).

Both paths funnel through the same `ControlOperationalExamAction::executeAutomatic()` used by the manual Proctor/Instructor UI action, just with `source = 'automatic'` and no actor. **CONFIRMED** — read the full file.

No other jobs, no Horizon, no queued domain work (`QUEUE_CONNECTION=database` but nothing dispatches jobs onto it for business logic).

## 20. Settings

No dedicated "settings" table beyond the Course reference-value tables (Levels/Stacks/Supplements/Languages), which are Admin-editable but scoped to Subject/Course metadata, not global system config (VAT %, commission, etc. — none of that concept exists here). Everything else configurable is via `.env` (see docs/environment.md). **CONFIRMED**.

## 21. Important Validation

- A Proctor's ID must resolve to a currently active, eligible Proctor when supplied by an Instructor; a Proctor is not asked for one at all (`ProctorIdVerifier`/`ControlOperationalExamAction::executeManual`).
- Attempt answer autosave: True/False must be `true`/`false`; MCQ must match an existing option's `public_id`; attempt must be `in_progress` and unexpired.
- Student can only start an attempt if actively a member of the schedule's Group, schedule is `scheduled`, and current date is within the schedule's start/end window (unless an `override_started_at` exists from a manual Class start).
- A student who already has a `submitted` attempt for a schedule cannot start a new one ("A second attempt is not available").
- An expired in-progress attempt is transparently replaced with a new attempt on next start.

## 22. Critical Dependencies

```text
TrainingClass (operational)
 ├── Course (via course_id)
 ├── ExamSchedule (1..N, via exam_schedules.training_class_id — synced, not authored)
 │     ├── Exam
 │     │     └── ExamQuestion → Question → QuestionOption
 │     ├── Group → GroupMembership → User(student)
 │     └── ExamAttempt (per student, per schedule)
 │           ├── ExamAttemptQuestion (frozen per-attempt question snapshot: order + points)
 │           └── Certificate (1:1, only if passed)
 │                 └── CertificateDocument (4 per certificate)
 ├── Enrollment (per student)
 ├── proctor_id / instructor_id (direct FK columns → User — the real staff-assignment mechanism, BR-007a)
 └── ClassStaffAssignment (unused legacy table, never wired to authorization — superseded by proctor_id/instructor_id)
```

Changing the Exam/Class synchronization rule, the attempt-question snapshot model, or the certificate 1:1-per-attempt constraint would ripple through most of the codebase — treat these as load-bearing invariants.

## 23. Source of Truth

| Concept | Source of truth |
|---|---|
| Class lifecycle status | `classes.status` (`ClassStatus`) |
| Exam Schedule status | `exam_schedules.status` (`ExamScheduleStatus`) — kept in sync with Class status by `ControlOperationalExamAction`, not fully independent |
| Attempt score/pass | `exam_attempts.score` / `exam_attempts.passed`, computed by `ExamScoringService::calculate()` from `exam_attempt_questions` + live `Question` correctness data at scoring time |
| Certificate pass/fail gate | `IssueCertificateAction` re-runs `ExamScoringService::calculate()` at issuance time (not just trusting the stored `passed` flag) |
| Which questions belong to an attempt | `exam_attempt_questions` — a **snapshot** taken at attempt-start time (order + points), independent of later edits to `exam_questions` |
| Exam/Class identity | `exam_schedules.training_class_id` — set by `ExamClassSynchronizer`, never user-edited |

No duplicated/conflicting sources of truth were found for these concepts. **CONFIRMED** for the paths traced; not exhaustively checked for every field (e.g. `student_name`/`exam_name` are copied onto `certificates` as a historical snapshot at issuance — intentional denormalization, not a sync risk, since certificates are immutable once issued).

## 24. Known Technical Debt

From README "Not implemented yet": certificate revocation workflow, advanced reporting, domain queue jobs. Also: `class_staff_assignments` table/`ClassStaffAssignment` model/`AssignClassStaffAction` — confirmed 2026-08-23 to be fully unused/unwired (see BUSINESS_RULES.md BR-007a); superseded by `classes.proctor_id`/`instructor_id`. Left in place, not deleted — a candidate for removal in a future cleanup pass.

## 25. Security Considerations

Documented thoroughly in docs/architecture.md §Security boundaries. Notable additional point: the Proctor's ID (`control_id`, owned only by the Proctor role) is a shared-secret gate on a high-impact action (ending a Class force-submits all in-progress attempts and issues certificates) — worth extra scrutiny in any future change to `ControlOperationalExamAction` or `ProctorIdVerifier`.

## 26. Potential Bugs / Risks

- **Ending a Class force-submits every in-progress attempt for its schedules**, even attempts started seconds ago, with no grace period — by design, but a foot-gun if a Proctor ends the wrong Class. No confirmation-of-consequences step was observed in the JSON contract (docs/api.md's `/exam-control` endpoint doesn't mention a preview/warning).
- Certificate `expires_at` is snapshotted at issuance from `exams.certificate_validity_years`, falling back to 2 years when unset; later Exam edits do not alter existing certificates.
- `ExamClassSynchronizer::sync()` matches an existing Class by `course_id` + exact `starts_at`/`ends_at` date match; two schedules for the same course on the same days will silently share one Class. This is likely intentional (multiple schedules/groups under one physical class session) but worth confirming with the user before assuming otherwise.

## 27. Needs Business Confirmation

- ~~`class_staff_assignments` / `AssignClassStaffAction` usage~~ — **Resolved 2026-08-23**: confirmed unused/unwired; see BUSINESS_RULES.md BR-007a. The real ownership rule (mandatory one Proctor + one Instructor per Class, access scoped accordingly) is implemented via `classes.proctor_id`/`instructor_id`.
- **Certificate revocation**: table/enum support it, no workflow implemented — confirm whether this is planned near-term before designing around it.
- Exact use of `ExamGroupAssignment` vs. `ExamSchedule.group_id` — both a Group↔Exam link (`exam_group_assignments`) and a Group column directly on `exam_schedules` exist. Not fully traced whether `exam_group_assignments` is a prerequisite gate for scheduling or an independent record-keeping table. **CONFLICT-shaped risk** — worth resolving before touching Group/Exam assignment logic.

## 28. Terminology Dictionary

| Business/UI term | Code/DB term | Notes |
|---|---|---|
| Subject | `Course` / `courses` | Explicitly documented rename in README/docs |
| Class (Proctor/Instructor/Student view) | `TrainingClass` / `classes` | Same row as "Exam" from Admin's view |
| Exam (Admin view) | `Exam` + `ExamSchedule` (synced to a `classes` row) | See §9 core rule |
| Proctor's ID | `exam_control_credentials.control_id` | Owned only by Proctors; Instructors submit one (belonging to a Proctor) in the `proctor_id` request field without ever owning one themselves (`docs/api.md` `POST /{role}/proctor-id/verify`) |
| Student Group | `Group` / `student_groups` | — |
| Question bank | `Question` / `questions` + `QuestionOption` / `question_options` | — |

## 29. Important Files

| Area | File |
|---|---|
| Core sync rule | `app/Services/ExamClassSynchronizer.php` |
| Class start/end + auto certificate issuance | `app/Actions/Exams/ControlOperationalExamAction.php` |
| Automatic scheduler | `app/Console/Commands/ProcessExamSchedules.php` |
| Attempt start | `app/Actions/Exams/StartExamAttemptAction.php` |
| Attempt submit | `app/Actions/Exams/SubmitExamAttemptAction.php` |
| Scoring | `app/Services/ExamScoringService.php` |
| Certificate issuance | `app/Actions/Certificates/IssueCertificateAction.php` |
| Proctor's ID check | `app/Services/ProctorIdVerifier.php` |
| Demo data / realistic fixtures | `database/seeders/DemoDataSeeder.php` |
| Architecture reference | `docs/architecture.md` |
| HTTP/JSON contract reference | `docs/api.md` |
| Env reference | `docs/environment.md` |
