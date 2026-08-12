# WellSharp

WellSharp is a Laravel application foundation for managing training and assessment operations. The current slice provides authenticated role-based workspaces and administration for users, training providers, reference data, Subjects (internally stored as Courses), Classes/Exams, enrollments, assessment attempts, scoring, and certificates.

## Current scope

Implemented:

- WellSharp ID and password authentication
- Password hashing, login throttling, logout, session regeneration, and session revocation
- Admin, Proctor, Instructor, and Student roles
- Admin user and role management
- Training providers and course reference values
- Subjects and Subject relationships (technical model/table name: Course/courses)
- Classes, enrollments, and withdrawals
- All-Class Proctor and Instructor dashboards; Classes are not assigned to individual staff members
- Enrollment-scoped Student dashboard
- Question banks per course with admin CRUD, relational options, Excel/CSV preview imports, and audit events
- Student profile and contact fields, Student Groups, many-to-many memberships, reusable Exams, Subject-scoped exam questions, and Exam scheduling
- One shared Exam/Class lifecycle: Admin labels the record as an Exam; Proctor, Instructor, and Student interfaces label the same operational record as a Class. Saving an Exam schedule creates or synchronizes its operational Class automatically; Admin never selects a separate Class bridge.
- Student confirmation, survey persistence, exam instructions, exam attempts, and Proctor/Instructor Class start/end controls with per-user exam-control credentials
- Student exam question rendering, per-question answer autosave, and attempt timers
- Exam scoring, final student submission, certificate issuance, certificate document PDF rendering/download, and admin certificate details
- Audit events, login events, correlation IDs, and sensitive-field redaction

Not implemented yet:

- Certificate revocation workflow
- Advanced reporting and domain queue jobs

## Requirements

- PHP 8.2 or newer
- Composer
- MySQL 8+ for the target environment
- Node.js and npm only when frontend assets need to be built

SQLite is used by the automated test suite. MySQL execution must be verified separately before deployment.

## Installation

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Configure the database, session, cache, queue, mail, and application URL values in `.env`. Never commit `.env` or production secrets.

Run migrations and seed the role/reference data:

```bash
php artisan migrate --seed
```

Create the first Admin interactively. The password is entered as a hidden prompt and is not displayed:

```bash
php artisan wellsharp:create-admin
```

For local interface testing, seed repeatable demo records for the implemented user, provider, subject, question bank, Class/Exam, enrollment, audit, and login workflows:

```bash
php artisan wellsharp:seed-demo
```

The command is restricted to local/testing environments. It creates 13 providers (including active, inactive, and archived records with map coordinates), 18 Subjects, active and inactive reference values, 270 questions with all supported types/difficulties and 360 options, 8 groups with active and removed memberships, 54 reusable exams covering draft/published/archived and static/shuffle order, 108 exam-group assignment history records, 72 Exam Schedules, and 72 Classes covering planned/active/completed/cancelled lifecycles with configured and actual transition timestamps. It also creates 40 active Students plus disabled/minimal edge accounts, active and disabled Proctors, active and archived Instructors, stable per-user exam-control credentials, role-specific profiles, enrolled/withdrawn/completed enrollments, completed/started surveys with answers, submitted/in-progress/expired attempts, passing and failing retakes, issued and revoked certificate bundles (three document rows per issued bundle), audit records, and login records. It creates `DEMO-*` accounts using the development-only password printed by the command; passwords are still stored as hashes.

The Excel question-bank template/import requires PHP `ext-zip` in the runtime environment. CSV import remains available for environments where that extension has not yet been enabled.

Use this command only in a trusted deployment shell. Do not pass passwords as command-line arguments.

## Project documentation

- [Architecture](docs/architecture.md) — application boundaries, request flow, roles, and domain relationships.
- [HTTP and JSON contract](docs/api.md) — authenticated web routes, same-origin JSON endpoints, validation, and business rules.
- [Environment reference](docs/environment.md) — supported environment variables and deployment configuration.
- [OpenAPI contract](openapi/openapi.yaml) — machine-readable documentation for the implemented JSON/action endpoints.
- [Postman collection](postman/wellsharp.postman_collection.json) — request examples for local session-authenticated testing.

The OpenAPI and Postman artifacts describe the existing session-authenticated web application. They do not imply a separate bearer-token API or a `routes/api.php` surface.

## Local development

```bash
php artisan serve
```

The application uses Blade views and Vite assets. Run `npm install` and `npm run build` when frontend assets are required.

## Tests and quality checks

```bash
php artisan test
vendor/bin/pint --test
composer audit
```

The test suite uses an in-memory SQLite database, array cache/session stores, and a synchronous queue. It does not prove MySQL compatibility.

## Queue and cache

The production example configuration uses database-backed sessions, cache, and queues. The `jobs` and `failed_jobs` tables are included. No domain-specific jobs are implemented yet; configure and monitor a queue worker before enabling asynchronous work.

## Production security notes

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Serve the application over HTTPS and set `SESSION_SECURE_COOKIE=true`.
- Use strong, non-committed database and application credentials.
- Restrict access to the `public/` directory; Laravel source, storage, and prototype reference files must not be web-accessible.
- Configure log retention and monitoring without logging passwords, tokens, or session data.
- Validate the full migration and test suite against the deployed MySQL version.

## Repository layout

- `app/Actions` — transactional business operations
- `app/Http/Controllers` — request coordination and responses
- `app/Http/Requests` — HTTP authorization and validation
- `app/Models` — persistence models and relationships
- `app/Policies` — resource authorization
- `app/Services/AuditRecorder.php` — audit persistence and redaction
- `database/migrations` — schema
- `database/seeders` — roles and reference data
- `resources/views` — Blade UI
- `routes` — web and role-specific routes
- `tests` — feature, unit, database, and performance-regression tests

The original static prototype files remain in the repository as reference material and are intentionally outside `public/`. The legacy `exam_group_assignments` table/model is retained for migration compatibility; new group eligibility is represented by `exam_schedules`.

## Known conditions

- MySQL migrations and the repeatable demo seed have been verified in the configured environment.
- Class lifecycle transitions are centralized in `ControlOperationalExamAction`; a separate state-machine package is not used.
- Legacy class staff-assignment tables/models remain for migration and historical display compatibility, but they are not used to authorize, filter, or require Proctor/Instructor access. The business rule is that any active Proctor or Instructor may control any Class with their own credential.
- First-admin provisioning is available through `wellsharp:create-admin` and should be run from a trusted deployment shell.
- Exam definitions, Subject question composition, publication validation, Exam scheduling, Student flow, per-question autosave, timers, scoring, final submission, release/finalization, staff reporting, certificate issuance, PDF document rendering/download, admin certificate details, and shared Exam/Class lifecycle controls are implemented.

## Class/Exam control rules

Classes and Exams are the same operational record. The Admin interface calls it an Exam; Proctor, Instructor, and Student interfaces call it a Class. A Class is never assigned to a particular Proctor or Instructor.

- Any active Proctor or Instructor may manually start or end any Class using the credential belonging to that authenticated user.
- Each eligible staff user receives one unique `exam_control_credentials.control_id` (displayed as the exam-control/Proctor ID). Another staff member's ID is rejected.
- Manual start/end can happen before the configured schedule and stores `actual_started_at`/`actual_ended_at` while preserving configured `starts_at`/`ends_at`.
- `wellsharp:process-exam-schedules` runs every minute in the Laravel scheduler. It automatically starts planned Classes at `starts_at` and ends active Classes at `ends_at`.
- Manual and automatic transitions share one transaction/row-lock action, are idempotent, and write distinct audit actions (`class.manual_start`, `class.automatic_start`, `class.manual_end`, and `class.automatic_end`).
