@php
    $subject = $certificate->exam?->subject;
    $courseName = collect([$certificate->subject_name, $subject?->level?->name, $subject?->stacks?->pluck('name')->join(', ')])->filter()->join(', ');
    $supplement = $subject?->supplements?->pluck('name')->join(', ');
    $provider = $certificate->provider ?: $certificate->trainingClass?->provider;
    $completionDate = $certificate->issued_at?->format('d F Y') ?: 'Not configured';
    $expirationDate = ($certificate->expires_at ?: $certificate->issued_at?->copy()->addYears(2))?->format('d F Y') ?: 'Not configured';
@endphp
<main class="completion-card certificate-standalone exact-card-front">
  <h2>IADC WellSharp Course Completion Card</h2>
  <p><strong>Trainee Name</strong><span>{{ $certificate->student_name }}</span></p>
  <p><strong>Course Name</strong><span>{{ $courseName ?: $certificate->exam_name }}</span></p>
  <p><strong>Supplement Name</strong><span>{{ $supplement ?: '' }}</span></p>
  <p><strong>Completion Date</strong><span>{{ $completionDate }}</span><strong>Expiration Date</strong><span>{{ $expirationDate }}</span></p>
  <p><strong>Provider</strong><span>{{ $certificate->provider_name ?: $provider?->name ?: 'Not configured' }}</span></p>
  <p><strong>Provider #</strong><span>{{ $provider?->provider_number ?: 'Not configured' }}</span><strong>Phone #</strong><span>{{ $provider?->phone ?: 'Not configured' }}</span></p>
  <p><strong>Instructor Name</strong><span>{{ $certificate->instructor_name ?: 'Not configured' }}</span></p>
  <footer>Certificate Number: <span>{{ $certificate->certificate_number }}</span></footer>
</main>
