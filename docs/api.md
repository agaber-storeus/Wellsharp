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

## Public certificate lookup and verification

These HTML routes intentionally require no authentication.

### `GET /iadc_certification`

Renders the certificate search page. Optional query parameter `lookup` is trimmed and normalized to uppercase. If it exactly matches `certificates.certificate_number`, the response redirects (`302`) to the certificate verification route. If it matches the `wellsharp_id` of a user whose current role is Instructor, the page lists that instructor's certificates ordered by student name and newest issuance; each result links to verification. An unknown value returns `200` with a not-found message.

### `GET /verify/certificates/{certificate}`

Uses `certificate_number` route binding. A match returns `200` HTML containing the certificate ID, student name, issue/expiry dates, program/exam labels, provider snapshot, and current issued/revoked status. Student email and WellSharp ID are not rendered. An unknown number returns `404`.

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

Provider table data. Query parameters: `search`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `provider_number`, `name`, `email`, `status`, and `created_at`; page size is 25. Page values below 1 or beyond the filtered last page are clamped to a valid page.

### `GET /admin/subjects/data`

Subject table data. `Course` is the technical model/table naming; the Admin UI labels this domain as Subjects. The legacy `/admin/courses` resource remains available for the broader Subject CRUD pages, but `/admin/courses/data` is not a registered endpoint. Query parameters: `search`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `code`, `name`, `level`, `status`, and `created_at`; page size is 25. Provider is no longer a Subject field or sort option. Page values below 1 or beyond the filtered last page are clamped to a valid page.

### `GET /admin/questions/data`

Question bank data. Query parameters: `search`, `course_id`, `difficulty`, `type`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `question_text`, `course_id`, `type`, `difficulty`, `is_active`, and `created_at`. Types are `true_false`, `mcq`, and `input`; difficulties are `easy`, `medium`, and `hard`; statuses are `active` and `archived`.

### `GET /admin/courses/{course}/questions/data`

Question bank data scoped to a single Subject's question-bank page (as opposed to the cross-Subject `/admin/questions/data` table). Same query parameters and sort values as above, minus `course_id` (implied by the route).

### `GET /admin/exams/data`

Exam definition data. Query parameters: `search`, `course_id`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `name`, `code`, `subject`, `questions_count`, `schedules_count`, `status`, and `created_at`.

### `GET /admin/courses/{course}/exams/data`

Exam definition data scoped to a single Subject's exam list page. Query parameters: `search`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `name`, `code`, `questions_count`, `schedules_count`, `status`, and `created_at`.

### `GET /admin/exam-schedules/data`

Exam schedule data. Query parameters: `search`, `exam_id`, `group_id`, `course_id`, `status`, `sort`, `direction`, and `page`. Allowed sort values are `exam`, `subject`, `group`, `start_date`, `end_date`, `duration_minutes`, and `status`. Each row includes `provider`, `start_mode` (`automatic` or `manual`), and `start_mode_label`.

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

Reveals any account's recoverable password (see [Recoverable password management](#recoverable-password-management) below). The route requires an authenticated, active Admin session.

### `PUT /admin/users/{user}` changed identity fields

The Admin edit form may update `wellsharp_id` (`sometimes|required|string|max:64|alpha_dash|unique`, normalized to uppercase). For a Proctor target, `proctor_id` is required, nullable/string/max 32/`alpha_dash`, and unique in `exam_control_credentials`; it is normalized to uppercase and updates the Proctor's exam-control credential. Changing the WellSharp ID or password increments `session_version`, invalidating the target user's other sessions.

## Recoverable password management

Application account-creation and password-update workflows store a separately encrypted, reversible copy of the supplied login password for Admin account management. The nullable column is not backfilled for legacy/nonstandard records, so an account may have no recoverable copy until a new password is set. The operational endpoint remains limited to Student targets:

```text
POST /admin/users/{user}/reveal-password
POST /{role}/students/{student}/reveal-password
```

Requires policy `viewPassword`: Admin may target any account; an **active** Proctor/Instructor may target Students only. No request body. Success returns `200` with `Cache-Control: no-store` (the response must never be cached or replayed from a shared/proxy cache):

```json
{ "password": "Ab3kq" }
```

For Proctor/Instructor callers, a target that isn't a Student returns `403`. An account with no recoverable password returns `404`. Every successful reveal is audited as `student.password_viewed` for Student targets or `user.password_viewed` for Admin reveals of staff accounts; audit data never includes the plaintext password, hash, or ciphertext. The password is not embedded in initial page HTML/JSON and is returned only by an explicit reveal request with transient client-side display.

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

See [Recoverable password management](#recoverable-password-management) above.

### `POST /{role}/classes/{trainingClass}/student-passwords`

Batch-reveals passwords for the distinct Students enrolled in one Class Dashboard. Requires an active Proctor/Instructor assigned to the Class (`viewStudentPasswords` policy). No request body. Success returns `200` with `Cache-Control: no-store`:

```json
{
  "students": [
    { "student_id": "01KZMH1TX1B89J3BTTSPBW04VXJ", "password": "Ab3kq" },
    { "student_id": "01KZMH1TX1B89J3BTTSPBW04VYK", "password": null }
  ]
}
```

`student_id` is the User public ULID; `password` is null when the enrolled account is no longer a Student or has no recoverable copy. The request writes one `student_passwords.class_roster_viewed` audit event containing actor/Class/count metadata and no password values. Unauthorized or unassigned callers receive `403`.

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

These are authenticated web routes, not JSON endpoints. `documents/{document}` renders a certificate document page with the surrounding app chrome; `documents/{document}/view` (`standalone`) renders a completion card without app chrome for operational Front/Back actions; `download` renders the PDF (`CertificatePdfService`) and returns `200` with `Content-Type: application/pdf`, an `attachment` disposition, and no-cache headers; `preview` renders the identical PDF `inline` with the same cache protection. Supported document types are `full_certificate`, `knowledge_assessment_report`, `completion_card_front`, and `completion_card_back`. The Full Certificate matches the official two-page PDF template and contains generated verification QRs on both pages; Completion Card Back also contains its generated verification QR. The document must belong to the certificate (`404` otherwise). Admins may view all certificates; students may view their own; active Proctors/Instructors may view certificates.

### Operational certificate CSV

```text
GET /{role}/certificate/export
```

Returns `wellsharp-certificates.csv` for Classes visible to the active Proctor/Instructor. Filters include `first_name`, `last_name`, `email`, `certificate_id`, `start_date`, `end_date`, `class_id`, `provider_id`, `instructor_id`, `level_id`, and `supplement_id`.

## HTML workflow routes

The following are page/redirect routes rather than JSON APIs:

- Admin CRUD pages under `/admin` for users, Students, providers, Subjects, Groups, Questions, Exams, schedules, Classes, and certificates.
- Public certificate search at `/iadc_certification` and certificate-number verification at `/verify/certificates/{certificate}`.
- Proctor/Instructor pages under `/{role}/profile`, `/analytics`, `/analytics/search`, `/analytics/results`, `/classes`, `/browse`, `/browse/results`, and `/certificate`.
- Student flow under `/student/schedules/{schedule}/confirm`, `/survey`, `/survey/form`, `/proctor`, `/start`, `/attempts/{attempt}`, `/submit`, and `/report`.

State-changing browser forms use Laravel's CSRF token and return redirects with session flash messages unless the controller explicitly supports JSON.

Adding a Student to an existing Group (including through Student create/edit) immediately synchronizes Enrollment rows for that Group's linked, non-completed/non-cancelled Classes. Removing Group membership does not automatically withdraw an existing Enrollment.

## Business rules consumers must preserve

- Exam and Class are two interface labels for the same operational domain; do not create a second bridge record.
- Schedule availability is date-based; per-student duration starts when the attempt starts. Manual-start schedules also require a staff start override before students can open them.
- Static Exam order is shared; shuffle order is persisted per student attempt.
- Manual-selection Exams keep a persisted, shared question bank; random-selection Exams keep none and draw `question_count` active Subject questions per student at attempt start (forcing static order), fixed for that attempt once created.
- Students must confirm contact information and complete the survey before starting.
- Only the Proctor role owns a Proctor's ID; a Proctor controls a Class directly, an Instructor must supply an active Proctor's ID belonging to someone else.
- Passing submitted attempts are scored and receive four certificate documents; failed attempts do not receive certificates.
