# WellSharp Architecture

## Scope

WellSharp is a Laravel MVC web application for administering training Subjects, Exams, Classes, schedules, question banks, student assessment attempts, scoring, reporting, and certificates. The application has four role workspaces:

- Admin: manages users, providers, Subjects, question banks, Exams, schedules, Groups, Classes, and certificates.
- Proctor: operates Classes, controls lifecycle transitions, views reporting, and looks up certificates.
- Instructor: has the same operational Class/reporting surface as a Proctor.
- Student: confirms contact information, completes the survey, starts an available Exam, saves answers, submits the attempt, and views certificates.

The codebase uses session-authenticated web routes. There is no `routes/api.php` and no Sanctum/Passport API guard. JSON endpoints are same-origin endpoints used by the Blade/Alpine.js interfaces.

## Request lifecycle

```text
Browser request
    ↓
routes/web.php + role route files
    ↓
web middleware, session auth, active-user check, session-version check, role middleware
    ↓
Form Request validation and policy authorization
    ↓
Controller
    ↓
Action or Service (where a transactional workflow is needed)
    ↓
Eloquent model / relationship
    ↓
MySQL in production or SQLite in tests
    ↓
Blade view, redirect, streamed CSV/PDF, or JSON response
```

`RequestCorrelationId` is appended to the web middleware stack and adds an `X-Correlation-ID` response header while placing the value in the log context.

## Layer responsibilities

### Routes

`routes/web.php` contains authentication, certificate document, shared operational, and logout routes. `routes/admin.php`, `routes/proctor.php`, `routes/instructor.php`, and `routes/student.php` are registered by `bootstrap/app.php` and contain the role-specific surfaces.

### Middleware

- `auth`: Laravel session authentication.
- `active.user`: logs out inactive or archived users.
- `session.version`: invalidates sessions after role/password/status changes.
- `current.role:<role>`: prevents a signed-in user from crossing role workspaces.
- `RequestCorrelationId`: adds request correlation to logs and responses.

### Controllers

Controllers coordinate validated requests and return views, redirects, JSON, streamed CSV, or PDF responses. They do not act as a repository layer; the application uses Eloquent queries directly.

### Form Requests

Requests in `app/Http/Requests` perform input authorization and validation. Important examples include `StoreExamRequest`, `StoreExamScheduleRequest`, `QuestionRequest`, `ControlExamRequest`, and `LoginRequest`.

### Actions

Transactional business workflows live in `app/Actions`, including exam scheduling, student attempt start/submit, exam control, release/scoring, certificate issuance, question import, group membership, user creation, and profile updates.

### Services

Services encapsulate cross-model logic such as `StudentExamFlowService`, `ExamScoringService`, `OperationalReportingService`, `QuestionExcelService`, `QuestionImageService`, `CertificatePdfService`, and the exam-control credential services.

### Models

The application uses Eloquent models and relationships. Public route identifiers use ULIDs for most operational resources through `HasPublicUlid`; some admin JSON payloads intentionally expose numeric internal IDs for form/table operations.

## Core domain rule: Exam and Class

An Admin calls the assessment definition an Exam and operational role interfaces call the same operational record a Class. An Exam Schedule links an Exam and Group to an operational `classes` row. There is no separate user-selected bridge in the current implementation.

Any active Proctor or Instructor may control any Class. The authenticated staff member must provide their own value from `exam_control_credentials.control_id`; another staff member's control ID is rejected. Manual controls can happen before configured dates and store `actual_started_at` or `actual_ended_at`. `wellsharp:process-exam-schedules` performs schedule-gated automatic transitions every minute.

## Assessment flow

```text
Admin creates Subject → creates Question bank → creates/publishes Exam
    → assigns Exam to an active Group through an Exam Schedule
    → schedule synchronizes the operational Class

Student confirms contact information
    → completes persisted survey
    → opens exam instructions
    → starts attempt
    → answers autosave per question
    → submits attempt
    → scoring runs and a passing attempt receives three certificate documents

Proctor/Instructor
    → may start/end the Class with their own control ID
    → may view reports and release/scored attempts
```

Question order is chosen when the attempt is created: `static` preserves the Exam question order; `shuffle` creates and persists a per-student attempt order. Exam duration belongs to the schedule and starts when the student starts an attempt.

## External integrations and asynchronous work

No third-party API integration, webhook, event/listener, domain queue job, Horizon configuration, or notification provider is implemented. The queue connection defaults to the database, but the current domain actions run synchronously. The scheduler command is registered in `routes/console.php` and must be invoked by Laravel's scheduler in production.

## Frontend communication

Blade templates under `resources/views` render the role workspaces. Alpine.js behavior calls same-origin JSON data/action endpoints with the Laravel CSRF token. The server returns standard Laravel validation responses for invalid requests; successful table endpoints use `{ data, meta }`, while action endpoints return small task-specific JSON objects.

## Security boundaries

- Passwords are handled by Laravel's `hashed` cast and are never returned in payloads.
- Authentication uses the session guard; no bearer token contract exists.
- Admin routes require the Admin current role and policy authorization.
- Operational routes require the current Proctor or Instructor role; reporting is available to active operational users.
- Student attempt routes verify the attempt owner and the attempt's state.
- Certificate viewing is Admin-wide, Student-owner-only for students, and active operational-role accessible for Proctors/Instructors.
- Question correct answers are hidden by the `Question` model's serialization settings and are only used server-side for scoring.

## Relevant source locations

| Area | Source |
| --- | --- |
| Application bootstrap and middleware | `bootstrap/app.php`, `app/Http/Middleware/` |
| Routes | `routes/web.php`, `routes/admin.php`, `routes/proctor.php`, `routes/instructor.php`, `routes/student.php` |
| Business workflows | `app/Actions/`, `app/Services/` |
| Persistence | `app/Models/`, `database/migrations/` |
| Demo scenarios | `database/seeders/DemoDataSeeder.php` |
| Feature verification | `tests/Feature/` |
