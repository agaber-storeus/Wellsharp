@extends($layout)

@section($contentSection)
@php
    $user = auth()->user();
    $backUrl = $user->hasRole('student')
        ? route('student.certificates')
        : ($user->hasRole('proctor')
            ? route('proctor.certificate')
            : ($user->hasRole('instructor') ? route('instructor.certificate') : route('admin.certificates.show', $certificate)));
    $validThrough = $certificate->expires_at?->format('F j, Y')
        ?: $certificate->issued_at?->copy()->addYears(2)->format('F j, Y')
        ?: 'Not configured';
    $fullDocument = $certificate->documents->first(
        fn ($document) => $document->type === \App\Enums\CertificateDocumentType::FullCertificate
    );
    $documentLinks = [
        ['number' => '01', 'id' => 'certificate-full', 'type' => 'Official PDF', 'title' => 'Full Certificate', 'description' => 'Complete two-page IADC certificate'],
        ['number' => '02', 'id' => 'certificate-report', 'type' => 'Assessment', 'title' => 'Knowledge Assessment Report', 'description' => 'Score and completion record'],
        ['number' => '03', 'id' => 'certificate-front', 'type' => 'Completion card', 'title' => 'Card front', 'description' => 'Trainee credential details'],
        ['number' => '04', 'id' => 'certificate-back', 'type' => 'Completion card', 'title' => 'Card back', 'description' => 'Verification and provider guidance'],
    ];
@endphp

<section class="certificate-bundle-page">
  <header class="certificate-bundle-hero">
    <div class="certificate-hero-copy">
      <div class="certificate-kicker"><span class="certificate-kicker-dot"></span> Certificate Bundle <span class="certificate-kicker-divider">/</span> WellSharp credential</div>
      <h1>{{ $certificate->student_name }}</h1>
      <p class="certificate-hero-subtitle">{{ $certificate->subject_name ?: $certificate->exam_name }}</p>
      <div class="certificate-hero-meta">
        <span>Certificate number</span>
        <strong>{{ $certificate->certificate_number }}</strong>
      </div>
    </div>
    <div class="certificate-hero-actions">
      <span class="certificate-status-pill {{ $certificate->status->value }}"><span></span>{{ $certificate->status->label() }}</span>
      <button class="certificate-action primary" type="button" onclick="window.print()"><span aria-hidden="true">↓</span> Print / save</button>
      <a class="certificate-action" href="{{ $backUrl }}">Back to certificates</a>
    </div>
  </header>

  <div class="certificate-summary-grid" aria-label="Certificate summary">
    <div class="certificate-summary-card score">
      <span class="certificate-summary-label">Assessment score</span>
      <strong>{{ number_format((float) $certificate->score, 2) }}<small>%</small></strong>
      <span class="certificate-summary-detail">Passing score {{ $certificate->passing_score }}%</span>
    </div>
    <div class="certificate-summary-card">
      <span class="certificate-summary-label">Issued</span>
      <strong>{{ $certificate->issued_at?->format('M j, Y') ?: '—' }}</strong>
      <span class="certificate-summary-detail">{{ $certificate->documents->count() }} documents in this bundle</span>
    </div>
    <div class="certificate-summary-card">
      <span class="certificate-summary-label">Valid through</span>
      <strong>{{ $certificate->expires_at?->format('M j, Y') ?: '—' }}</strong>
      <span class="certificate-summary-detail">Keep this credential for your records</span>
    </div>
    <div class="certificate-summary-card">
      <span class="certificate-summary-label">Class</span>
      <strong>{{ $certificate->class_number ?: '—' }}</strong>
      <span class="certificate-summary-detail">{{ $certificate->provider_name ?: 'Training provider not configured' }}</span>
    </div>
  </div>

  <div class="certificate-bundle-note">
    <div class="certificate-note-icon" aria-hidden="true">✓</div>
    <div><strong>Credential bundle issued for a passed Class</strong><span>Review the assessment report and both sides of the Course Completion Card below.</span></div>
    <span class="certificate-note-validity">Valid through {{ $validThrough }}</span>
  </div>

  <div class="certificate-documents-heading">
    <div><span class="certificate-section-kicker">Your documents</span><h2>Certificate package</h2></div>
    <span>{{ count($documentLinks) }} official documents · scroll to review</span>
  </div>

  <nav class="certificate-document-nav" aria-label="Certificate documents">
    @foreach($documentLinks as $document)
      <a class="certificate-document-nav-item" href="#{{ $document['id'] }}">
        <span class="certificate-document-number">{{ $document['number'] }}</span>
        <span class="certificate-document-nav-copy"><small>{{ $document['type'] }}</small><strong>{{ $document['title'] }}</strong><em>{{ $document['description'] }}</em></span>
        <span class="certificate-document-arrow" aria-hidden="true">↗</span>
      </a>
    @endforeach
  </nav>

  <div class="certificate-document-stack">
    @if($fullDocument)
      <article id="certificate-full" class="certificate-document-panel">
        <div class="certificate-document-label">Full Certificate</div>
        <iframe class="certificate-full-pdf-preview" title="Full Certificate PDF" src="{{ route('certificates.documents.preview', [$certificate, $fullDocument]) }}"></iframe>
      </article>
    @endif
    @include('certificates.documents.report')
    @include('certificates.documents.front')
    @include('certificates.documents.back')
  </div>
</section>
@endsection
