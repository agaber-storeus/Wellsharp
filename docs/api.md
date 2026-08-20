# WellSharp HTTP and JSON Contract

## Important scope

This project does not expose a separate REST API namespace. There is no `routes/api.php`; all routes are web routes protected by Laravel session authentication, CSRF protection for state-changing browser requests, and role middleware. The JSON endpoints below are same-origin endpoints used by the Blade/Alpine.js interfaces.

Base URL: `{{APP_URL}}`

Authentication is established by submitting `POST /login`. API consumers that are not browser clients must preserve the Laravel session cookie and send the CSRF token for state-changing requests. A Bearer-token contract is **not implemented**.

## Common responses

Collection JSON endpoints use:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "total": 0,
    "from": null,
    "to": null
  }
}
```

Laravel validation failures use the standard structure:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field": ["The field is required."]
  }
}
```

For unauthenticated browser requests Laravel redirects to `/login`; authorization failures are HTTP `403`; route-model lookup failures are HTTP `404`; application validation/business-rule failures are generally HTTP `422`. Exact HTML error rendering is controlled by Laravel's exception handler.

## Authentication

### `GET /login`

Returns the login form. No authentication required.

### `POST /login`

Authenticates using a WellSharp ID and password and redirects to the role landing page.

Request fields:

| Field | Rules |
| --- | --- |
| `wellsharp_id` | required, string, max 64; normalized to uppercase/trimmed |
| `password` | required, string |

Successful behavior is an HTTP redirect. Failed credentials return to `/login`; repeated failures are rate-limited after five attempts per normalized ID/IP key for 60 seconds and return HTTP `429`.

### `POST /logout`

Requires an authenticated session. Invalidates the session and regenerates the CSRF token, then redirects to `/login`.

## Admin JSON collection endpoints

All endpoints in this section require an authenticated active user with current role `admin`. Policies are also invoked by the controller.

### `GET /admin/users/data`

Search, filter, sort, and paginate users.

Query parameters: `search`, `role_id`, `status`, `sort`, `direction`, `page`. Allowed sort values are `name`, `wellsharp_id`, `role`, `status`, and `created_at`; direction is `asc` or `desc`; default page size is 25.

### `GET /admin/students/data`

Student-only table data. Query parameters: `search`, `gender`, `group_id`, `sort`, `direction`, `page`, and `per_page`. Allowed `per_page` values are 15, 30, 50, and 100; default is 15. Supported sort values are `wellsharp_id`, `name`, `age`, `gender`, `groups_count`, `status`, and `created_at`.

### `GET /admin/groups/data`

Group table data. Query parameters: `search`, `status`, `sort`, `direction`, `page`, and `per_page`. `per_page` accepts 15, 30, 50, or 100. Supported sort values are `name`, `code`, `students_count`, `exam_schedules_count`, `status`, and `created_at`.

### `GET /admin/groups/search?q={term}`

Returns up to 25 active Groups matching name or code. Terms shorter than two characters return `[]`.

### `GET /admin/providers/data`

Provider table data. Query parameters: `search`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `provider_number`, `name`, `email`, `status`, and `created_at`.

### `GET /admin/subjects/data`

Subject table data. `Course` is the technical model/table naming; the Admin UI labels this domain as Subjects. The legacy `/admin/courses` resource remains available for the broader Subject CRUD pages, but `/admin/courses/data` is not a registered endpoint. Query parameters: `search`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `code`, `name`, `provider`, `level`, `status`, and `created_at`.

### `GET /admin/questions/data`

Question bank data. Query parameters: `search`, `course_id`, `difficulty`, `type`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `question_text`, `course_id`, `type`, `difficulty`, `is_active`, and `created_at`. Types are `true_false`, `mcq`, and `input`; difficulties are `easy`, `medium`, and `hard`; statuses are `active` and `archived`.

### `GET /admin/exams/data`

Exam definition data. Query parameters: `search`, `course_id`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `name`, `code`, `subject`, `questions_count`, `schedules_count`, `status`, and `created_at`.

### `GET /admin/exam-schedules/data`

Exam schedule data. Query parameters: `search`, `exam_id`, `group_id`, `course_id`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `exam`, `subject`, `group`, `start_date`, `end_date`, `duration_minutes`, and `status`.

### `GET /admin/classes/data`

Admin Class data. Query parameters: `search`, `status`, `course_id`, `provider_id`, `sort`, `direction`, and `page`. Allowed sort values are `class_number`, `course`, `provider`, `starts_at`, `ends_at`, `status`, and `created_at`.

### `GET /admin/certificates/data`

Certificate table data. Query parameters: `search`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `certificate_number`, `student_name`, `subject_name`, `class_number`, `provider_name`, `score`, `issued_at`, and `status`.

## Admin JSON actions

### Course reference configuration

All require Admin role and support `Accept: application/json`.

```text
POST   /admin/subject-configuration/{type}
PATCH  /admin/subject-configuration/{type}/{item}
PATCH  /admin/subject-configuration/{type}/{item}/toggle
PATCH  /admin/subject-configuration/{type}/reorder
```

`type` must be `levels`, `stacks`, `supplements`, or `languages`.

Create/update body:

```json
{
  "name": "Managed Pressure",
  "sort_order": 0
}
```

`name` is required, string, max 160; `sort_order` is nullable integer, min 0. Create returns `201` with `{ message, row }`. Duplicate names return `422` with `errors.name`. Toggle returns `{ message, row }`. Reorder body is:

```json
{ "order": [3, 1, 2] }
```

The IDs must be distinct existing IDs for that configuration type. Success returns `{ message, rows }`; invalid IDs return `422`.

### `POST /admin/users/{user}/reveal-password`

Reveals a Student's recoverable password (see [Student password recovery](#student-password-recovery) below for the shared behavior and business rules). Admin may reveal any Student's password regardless of Admin's own active status.

## Student password recovery

Student accounts (only) keep a separately encrypted, reversible copy of their current login password so staff can hand it to a Student who cannot self-reset. Both endpoints below share identical behavior:

```text
POST /admin/users/{user}/reveal-password
POST /{role}/students/{student}/reveal-password
```

Requires policy `viewPassword`: the target account's current role must be Student, and the actor must be Admin, or an **active** Proctor/Instructor. No request body. Success returns `200` with `Cache-Control: no-store` (the response must never be cached or replayed from a shared/proxy cache):

```json
{ "password": "Ab3kq" }
```

A target that isn't a Student (policy denial) returns `403`; a Student with no recoverable password (legacy account created before this feature) returns `404`. Every successful reveal is written to the audit log as `student.password_viewed` (actor, actor role, and target Student — never the plaintext password, its hash, or its ciphertext), regardless of which role performed it. The password is never embedded in any page's HTML/JSON by default (including the Proctor/Instructor Class Dashboard roster, which carries only a `revealUrl` per Student) — only this endpoint, called on an explicit staff "Reveal" action, ever returns the plaintext value, and only to that one request; the client keeps it in transient UI state only (no localStorage/sessionStorage/cookie/URL persistence) until the staff member clicks "Hide" or navigates away.

## Operational JSON endpoints

These routes are available under both `/proctor` and `/instructor`. They require an active authenticated user with the corresponding current role.

### `POST /{role}/proctor-id/verify`

Verifies a Proctor's ID: it must belong to a currently active, eligible Proctor. Used by the Instructor oversight flow (a Proctor never needs to verify their own ID to control a Class). Body:

```json
{ "proctor_id": "PR-DEMO-PROCTOR-001" }
```

`proctor_id` is required, string, max 32. Success returns `200`:

```json
{ "message": "Proctor ID verified.", "proctor_name": "Omar Hassan" }
```

An ID belonging to another user returns `422`.

### `POST /{role}/classes/{trainingClass}/exam-control`

Starts or ends the shared operational Class/Exam. The route-model identifier is the Class public ULID. Body:

```json
{
  "action": "start",
  "proctor_id": "PR-DEMO-PROCTOR-001"
}
```

`action` is required and must be `start` or `end`. `proctor_id` is required only when the authenticated user's current role is Instructor, and must resolve to a currently active, eligible Proctor (never the Instructor's own or another Instructor's credential — Instructors don't own one). A Proctor omits `proctor_id` entirely. A planned Class can be started; an active Class can be ended; invalid transitions return `422`. Success returns `200` with the Class status and number of schedules controlled.

### `POST /{role}/students/{student}/reveal-password`

See [Student password recovery](#student-password-recovery) above.

### `POST /{role}/enrollments/{enrollment}/skills-score`

Records a trainee's hands-on Skills Score for one Enrollment (separate from the Knowledge Exam score, which comes from `exam_attempts`). Body:

```json
{ "skills_score": 85 }
```

`skills_score` is required, integer, 0-100. Requires policy `updateSkillsScore`: an active Proctor/Instructor (Admin also via policy `before()`). Writes audit action `enrollment.skills_score_updated`. Success returns `200`:

```json
{ "skills_score": 85 }
```

### `GET /{role}/analytics/results/export`

Streams `wellsharp-assessment-comparison.xlsx`: the same aggregated assessment/retake comparison rows shown on the Analytics → Results page, honoring the same server-side filters as the page itself. Not a JSON endpoint.

### `GET /{role}/analytics/attempts/{attempt}/summary`

Short, trainee-facing JSON summary of one scored attempt (name, assessment, stack, score, pass/fail, and up to a few missed-topic notes) — powers the Score Report popup on the Class Dashboard's Scores & Reports tab. Requires the same visibility check as `GET /{role}/analytics/attempts/{attempt}` (`OperationalReportingService::canViewAttempt`). An attempt with no score yet returns an empty `topics` array and `null` score.

### `GET /{role}/certificate/data`

JSON-backed variant of the `/{role}/certificate` lookup page (search/filter certificates without a full page reload). Same filters as the CSV export below.

## Student JSON endpoints

Student endpoints require an active authenticated user with current role `student`, and attempt endpoints verify ownership.

### `PATCH /student/attempts/{attempt}/questions/{attemptQuestion}/answer`

Autosaves one answer. Body:

```json
{ "answer": "true" }
```

`answer` is present, nullable, string, max 5000. True/false answers must be `true` or `false`; MCQ answers must match an option public ID. Empty string clears an answer. Success returns:

```json
{
  "saved": true,
  "answered": true,
  "answered_count": 4
}
```

The attempt must be in progress and unexpired. Ownership failures are `403`; mismatched attempt/question resources are `404`; invalid or unavailable answers are `422`.

### `POST /student/attempts/{attempt}/expire`

Expires the authenticated student's attempt after its stored expiration time. Success returns `{ "expired": true }`. Calling it before expiry returns `422`.

## Download and export responses

### Certificate documents

```text
GET /certificates/{certificate}/documents/{document}
GET /certificates/{certificate}/documents/{document}/view
GET /certificates/{certificate}/documents/{document}/download
GET /certificates/{certificate}/documents/{document}/preview
```

These are authenticated web routes, not JSON endpoints. `documents/{document}` renders a certificate document page with the surrounding app chrome; `documents/{document}/view` (`standalone`) renders the same completion-card document with no app chrome, for the Class Dashboard's Front/Back buttons to open in a new tab; `download` renders the real branded PDF (`CertificatePdfService`) and returns `200` with `Content-Type: application/pdf` and an `attachment` disposition; `preview` renders the identical PDF `inline` instead, for the Options column's "Preview Certificate" action. `preview`/`download` are only meaningful for the two Completion Card document types. The document must belong to the certificate (`404` otherwise). Admins may view all certificates; students may view their own; active Proctors/Instructors may view certificates.

### Operational certificate CSV

```text
GET /{role}/certificate/export
```

Returns `wellsharp-certificates.csv` for Classes visible to the active Proctor/Instructor. Filters include `first_name`, `last_name`, `email`, `certificate_id`, `start_date`, `end_date`, `class_id`, `provider_id`, `instructor_id`, `level_id`, and `supplement_id`.

## HTML workflow routes

The following are page/redirect routes rather than JSON APIs:

- Admin CRUD pages under `/admin` for users, Students, providers, Subjects, Groups, Questions, Exams, schedules, Classes, and certificates.
- Proctor/Instructor pages under `/{role}/profile`, `/analytics`, `/analytics/search`, `/analytics/results`, `/classes`, `/browse`, `/browse/results`, and `/certificate`.
- Student flow under `/student/schedules/{schedule}/confirm`, `/survey`, `/survey/form`, `/proctor`, `/start`, `/attempts/{attempt}`, `/submit`, and `/report`.

State-changing browser forms use Laravel's CSRF token and return redirects with session flash messages unless the controller explicitly supports JSON.

## Business rules consumers must preserve

- Exam and Class are two interface labels for the same operational domain; do not create a second bridge record.
- Schedule availability is date-based; per-student duration starts when the attempt starts.
- Static Exam order is shared; shuffle order is persisted per student attempt.
- Students must confirm contact information and complete the survey before starting.
- Only the Proctor role owns a Proctor's ID; a Proctor controls a Class directly, an Instructor must supply an active Proctor's ID belonging to someone else.
- Passing submitted attempts are scored and receive three certificate documents; failed attempts do not receive certificates.
