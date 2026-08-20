# Business Flows — WellSharp

## 1. Exam authoring → scheduling → Class sync

```mermaid
flowchart TD
    A[Admin creates Subject/Course] --> B[Admin builds Question bank for Course]
    B --> C[Admin creates Exam: name, code, question_order_mode, status=draft]
    C --> D[Admin adds Questions to Exam via exam_questions]
    D --> E[Admin publishes Exam: status=published]
    E --> F[Admin creates Exam Schedule: exam + group + start/end date + duration_minutes]
    F --> G[ExamClassSynchronizer.sync]
    G --> H{Matching Class exists?<br/>same course_id + exact start/end dates}
    H -- yes --> I[Reuse existing classes row]
    H -- no --> J[Create new classes row, status=planned]
    I --> K[exam_schedules.training_class_id set]
    J --> K
```

## 2. Student assessment attempt lifecycle

```mermaid
flowchart TD
    A[Student logs in] --> B[Confirm contact info]
    B --> C[Complete persisted survey]
    C --> D[Open exam instructions for schedule]
    D --> E{StartExamAttemptAction.assertStudentCanStart}
    E -- "not in schedule's Group" --> X1[403 Forbidden]
    E -- "schedule not 'scheduled'" --> X2[422 not available]
    E -- "before start_date, no override" --> X3[422 not yet available]
    E -- "past end_date, no override" --> X4[422 schedule ended]
    E -- "already has a submitted attempt" --> X5[422 second attempt not available]
    E -- ok --> F{Existing in_progress attempt<br/>not expired?}
    F -- yes --> G[Resume existing attempt]
    F -- no, expired --> H[Expire old attempt, create new one]
    F -- none --> H
    H --> I[Order questions: static or shuffle]
    I --> J[Create exam_attempt_questions snapshot]
    J --> K[Student answers autosave per question<br/>PATCH .../answer]
    K --> L{Student submits}
    L --> M[SubmitExamAttemptAction]
    M -- "expired at submit time" --> N[status=expired, reject]
    M -- ok --> O[status=submitted, ExamScoringService.calculate]
    O --> P{score >= passing_score?}
    P -- no --> Q[No certificate]
    P -- yes --> R[IssueCertificateAction: create certificate + 3 documents]
```

## 3. Class start / end — manual (Proctor/Instructor) and automatic (scheduler)

```mermaid
flowchart TD
    subgraph Manual
        A0{Actor role?}
        A0 -- Proctor --> A4[ControlOperationalExamAction.execute source=manual]
        A0 -- Instructor --> A1[Instructor submits action=start/end + a Proctor's ID] --> A2{ProctorIdVerifier:<br/>ID belongs to an active, eligible Proctor?}
        A2 -- no --> A3[422 rejected]
        A2 -- yes --> A4
    end
    subgraph Automatic
        B1[wellsharp:process-exam-schedules, every minute] --> B2[Find planned Classes with starts_at <= now]
        B1 --> B3[Find active Classes with ends_at <= now]
        B2 --> B4[ControlOperationalExamAction.executeAutomatic action=start]
        B3 --> B5[ControlOperationalExamAction.executeAutomatic action=end]
    end
    A4 --> C{action}
    B4 --> C
    B5 --> C
    C -- start --> D{Class status == planned?}
    D -- no, already active --> D1[No-op]
    D -- no, other --> D2[422 only a scheduled Class can be started]
    D -- yes --> D3[Class -> active, actual_started_at set]
    D3 --> D4[Every scheduled ExamSchedule under Class stamped;<br/>manual source also sets override_started_at]
    C -- end --> E{Class status == active?}
    E -- no, already completed --> E1[No-op]
    E -- no, other --> E2[422 only an active Class can be ended]
    E -- yes --> E3[Class -> completed, actual_ended_at set]
    E3 --> E4[Every scheduled ExamSchedule -> completed]
    E4 --> E5[Every in_progress ExamAttempt under those schedules<br/>force-submitted]
    E5 --> E6[IssueCertificateAction run per force-submitted attempt]
```

## Notes

- The Manual and Automatic branches converge on the **same** `ControlOperationalExamAction::execute()` — there is exactly one implementation of Class start/end business logic, differentiated only by `source` and whether an `actor` is attributed.
- Ending a Class is the single highest-blast-radius operation in the system: it can force-submit multiple students' in-progress attempts and issue certificates as a side effect, in one DB transaction (`DB::transaction` wraps the whole thing — all-or-nothing, no partial-completion risk).
