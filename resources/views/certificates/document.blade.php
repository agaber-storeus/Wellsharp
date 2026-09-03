@extends($layout)

@section($contentSection)
@php
    $documentType = $document->type;
@endphp

<section class="certificate-single-document-page">
  <div class="certificate-single-document-head">
    <div>
      <span class="certificate-section-kicker">Certificate document</span>
      <h1>{{ $document->title }}</h1>
      <p>{{ $certificate->student_name }} · {{ $certificate->certificate_number }}</p>
    </div>
    <div class="certificate-single-document-actions">
      <a class="certificate-action primary" href="{{ route('certificates.documents.download', [$certificate, $document]) }}"><span aria-hidden="true">↓</span> Download PDF</a>
      <a class="certificate-action light" href="{{ route('certificates.show', $certificate) }}">Back to bundle</a>
    </div>
  </div>

  <div class="certificate-document-stack">
    @if($documentType === \App\Enums\CertificateDocumentType::FullCertificate)
      <article class="certificate-document-panel" id="certificate-full-card">
        <div class="certificate-document-label">Full Completion Certificate</div>
        <iframe class="certificate-full-pdf-preview" title="Full Certificate PDF" src="{{ route('certificates.documents.preview', [$certificate, $document]) }}"></iframe>
      </article>
    @elseif($documentType === \App\Enums\CertificateDocumentType::KnowledgeAssessmentReport)
      @include('certificates.documents.report')
    @elseif($documentType === \App\Enums\CertificateDocumentType::CompletionCardFront)
      @include('certificates.documents.front')
    @else
      @include('certificates.documents.back')
    @endif
  </div>
</section>
@endsection
