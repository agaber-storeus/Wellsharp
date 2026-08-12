@php
    $attempt = $certificate->attempt;
    $subject = $certificate->exam?->subject;
    $stack = $subject?->stacks?->pluck('name')->join(', ');
@endphp
<article id="certificate-report" class="certificate-document-panel">
  <div class="certificate-document-label">Knowledge Assessment Report</div>
  <div class="score-report-modal certificate-document-surface">
    <div class="report-top"><img src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" /><button class="print-btn" type="button" onclick="window.print()">Print / Save</button></div>
    <h2>Knowledge Assessment Report</h2>
    <dl class="report-fields">
      <dt>Name:</dt><dd>{{ $certificate->student_name }}</dd>
      <dt>Assessment:</dt><dd>{{ $certificate->exam_name }}</dd>
      <dt>Stack:</dt><dd>{{ $stack ?: 'Not configured' }}</dd>
      <dt>Assessment Date:</dt><dd>{{ $certificate->issued_at?->format('F j, Y g:i A') ?: 'Not configured' }}</dd>
      <dt>Score:</dt><dd>{{ number_format((float) $certificate->score, 2) }}%</dd>
    </dl>
    <h3>Instructions</h3>
    <p>Thank you for completing the IADC Well Control Knowledge Assessment. You scored {{ number_format((float) $certificate->score, 2) }} percent and passed the course.</p>
    <p>Your assessment was completed for {{ $certificate->subject_name }}. Please keep this report with your Course Completion Card.</p>
    @if($attempt?->attemptQuestions?->isNotEmpty())
      <h3>Assessment Summary</h3>
      <p>{{ $attempt->attemptQuestions->whereNotNull('answer')->count() }} of {{ $attempt->attemptQuestions->count() }} questions answered.</p>
    @endif
  </div>
</article>
