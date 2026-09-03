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
- Student confirmation, survey persistence, exam instructions, and exam attempts; Proctor/Instructor Class start/end controls with Instructor Proctor-ID verification
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

The command is restricted to local/testing environments. It creates 13 providers (including active, inactive, and archived records with map coordinates), 18 Subjects, active and inactive reference values, 270 questions with all supported types/difficulties and 360 options, 8 groups with active and removed memberships, 54 reusable exams covering draft/published/archived and static/shuffle order, 108 exam-group assignment history records, 72 Exam Schedules, and 72 Classes covering planned/active/completed/cancelled lifecycles with configured and actual transition timestamps. It also creates 40 active Students plus disabled/minimal edge accounts, active and disabled Proctors, active and archived Instructors, a stable Proctor's ID per Proctor, role-specific profiles, enrolled/withdrawn/completed enrollments, completed/started surveys with answers, submitted/in-progress/expired attempts, passing and failing retakes, issued and revoked certificate bundles (three document rows per issued bundle), audit records, and login records. It creates `DEMO-*` accounts using the development-only password printed by the command; passwords are still stored as hashes.

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

`composer run dev` starts the dev server, queue listener, log tail, Vite, and the Laravel Scheduler together (see [Scheduled tasks](#scheduled-tasks-laravel-scheduler) below) using `npx concurrently`.

## Scheduled tasks (Laravel Scheduler)

`routes/console.php` registers `wellsharp:process-exam-schedules` on Laravel's Scheduler (`->everyMinute()->withoutOverlapping()`). Registering a scheduled task only defines *when* it should run; something still has to invoke the scheduler itself every minute, in every environment. `php artisan schedule:list` shows what is registered, but it does not run anything on its own.

Symptom if nothing invokes the scheduler: Classes created by Exam scheduling stay `planned` forever, even after `starts_at` has passed, because `wellsharp:process-exam-schedules` never executes.

### Local development

Run the scheduler continuously in its own terminal:

```bash
php artisan schedule:work
```

This is already included when you run `composer run dev`. Do not manually run `php artisan wellsharp:process-exam-schedules` as a substitute — `schedule:work` reproduces production's once-a-minute cadence and exercises the same code path Class start/end transitions rely on.

### Production

Production must invoke `php artisan schedule:run` every minute via the deployment environment's process manager. This repository does not currently ship a Docker/Supervisor/systemd configuration, so the standard approach is a single cron entry on the deployment host:

```cron
* * * * * cd /path/to/wellsharp && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/wellsharp` with the deployed application path, and run it as the same OS user that owns the application files/PHP-FPM pool. If the deployment target instead uses Supervisor, systemd, or a PaaS (Forge, Envoyer, Vapor, a container orchestrator, etc.), use that platform's native scheduler/cron integration instead of installing a second, competing cron entry — only one process should be invoking `schedule:run`/`schedule:work` per environment.

`withoutOverlapping()` prevents a slow run from overlapping the next tick; its lock is stored in the configured cache store (`CACHE_STORE`, `database` by default here), so it works correctly across the separate PHP processes that cron spawns each minute.

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
- Legacy class staff-assignment tables/models remain for migration and historical display compatibility, but they are not used to authorize, filter, or require Proctor/Instructor access. The business rule is that any active Proctor may control any Class directly, and any active Instructor may do so by providing an active Proctor's ID.
- First-admin provisioning is available through `wellsharp:create-admin` and should be run from a trusted deployment shell.
- Exam definitions, Subject question composition, publication validation, Exam scheduling, Student flow, per-question autosave, timers, scoring, final submission, release/finalization, staff reporting, certificate issuance, PDF document rendering/download, admin certificate details, and shared Exam/Class lifecycle controls are implemented.

## Class/Exam control rules

Classes and Exams are the same operational record. The Admin interface calls it an Exam; Proctor, Instructor, and Student interfaces call it a Class. A Class is never assigned to a particular Proctor or Instructor.

- Only the Proctor role owns a **Proctor's ID**: a unique credential (`exam_control_credentials.control_id`) generated automatically the moment a user's active role becomes Proctor, and revoked the moment they leave the Proctor role (including a change to Instructor). Instructors, Students, and Admins never own one.
- Any active Proctor may manually start or end any Class directly — no Proctor's ID entry is required, since the system already knows they are an authenticated Proctor.
- An active Instructor may manually start or end any Class by entering a Proctor's ID belonging to a currently active, eligible Proctor, as a dual-control/oversight check. An Instructor's own credential (they don't have one) or another Instructor's is always rejected.
- Manual start/end can happen before the configured schedule and stores `actual_started_at`/`actual_ended_at` while preserving configured `starts_at`/`ends_at`.
- `wellsharp:process-exam-schedules` runs every minute in the Laravel scheduler. It automatically starts planned Classes at `starts_at` and ends active Classes at `ends_at`.
- Manual and automatic transitions share one transaction/row-lock action, are idempotent, and write distinct audit actions (`class.manual_start`, `class.automatic_start`, `class.manual_end`, and `class.automatic_end`). Instructor-triggered audit events also carry the verified Proctor's identity (`verified_proctor_user_id`/`verified_proctor_wellsharp_id`).
