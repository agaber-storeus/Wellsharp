@extends('layouts.admin')

@section('admin-content')
<div class="page-head"><div><span class="admin-kicker">Certificate Details</span><h1>{{ $certificate->certificate_number }}</h1><p>Issued {{ $certificate->issued_at?->format('F j, Y H:i') }}</p></div><a class="btn secondary" href="{{ route('admin.certificates.index') }}">Back to certificates</a></div>
<div class="card"><div class="admin-section-head"><div><h2>Certificate status</h2><p class="muted">This certificate is linked to one submitted exam attempt and contains three documents.</p></div><span class="badge {{ $certificate->status->value }}">{{ $certificate->status->label() }}</span></div><div class="form-grid"><div><span class="muted">Certificate number</span><p>{{ $certificate->certificate_number }}</p></div><div><span class="muted">Score</span><p><strong>{{ number_format((float) $certificate->score, 2) }}%</strong> <span class="muted">/ passing {{ $certificate->passing_score }}%</span></p></div><div><span class="muted">Exam attempt</span><p>{{ $certificate->attempt?->public_id ?: '—' }}</p></div><div><span class="muted">Issued date</span><p>{{ $certificate->issued_at?->format('Y-m-d H:i') }}</p></div><div><span class="muted">Valid through</span><p>{{ $certificate->expires_at?->format('Y-m-d') ?: 'Not configured' }}</p></div><div><span class="muted">Documents</span><p>{{ $certificate->documents->count() }}</p></div></div></div>
<div class="card"><div class="admin-section-head"><h2>Student</h2><a href="{{ $certificate->student ? route('admin.users.show', $certificate->student) : '#' }}">Open user</a></div><div class="form-grid"><div><span class="muted">Name</span><p>{{ $certificate->student_name }}</p></div><div><span class="muted">WellSharp ID</span><p>{{ $certificate->student_wellsharp_id }}</p></div><div><span class="muted">Email</span><p>{{ $certificate->student_email ?: '—' }}</p></div></div></div>
<div class="card"><div class="admin-section-head"><h2>Training and assessment</h2></div><div class="form-grid"><div><span class="muted">Subject</span><p>{{ $certificate->subject_name }}</p></div><div><span class="muted">Exam</span><p>{{ $certificate->exam_name }}{{ $certificate->exam_code ? ' ('.$certificate->exam_code.')' : '' }}</p></div><div><span class="muted">Class</span><p>{{ $certificate->class_number ?: '—' }}</p></div><div><span class="muted">Group</span><p>{{ $certificate->group_name ?: '—' }}</p></div><div><span class="muted">Training provider</span><p>{{ $certificate->provider_name ?: '—' }}</p></div><div><span class="muted">Instructor</span><p>{{ $certificate->instructor_name ?: '—' }}</p></div></div></div>
<div class="card certificate-documents-admin-card">
  <div class="admin-section-head"><div><h2>Certificate documents</h2><p class="muted">Open a document to view it, or download an individual PDF.</p></div><a class="btn secondary" href="{{ route('certificates.show', $certificate) }}">Open bundle</a></div>
  <div class="certificate-documents-admin-list">
    @foreach($certificate->documents as $document)
      <div class="certificate-admin-document-row">
        <a class="certificate-admin-document-link" href="{{ route('certificates.documents.show', [$certificate, $document]) }}">
          <span class="certificate-admin-document-icon" aria-hidden="true">PDF</span>
          <span><strong>{{ $document->title }}</strong><small>Issued {{ $document->issued_at?->format('Y-m-d H:i') }}</small></span>
          <span class="certificate-admin-document-view">View <span aria-hidden="true">↗</span></span>
        </a>
        <a class="btn secondary small" href="{{ route('certificates.documents.download', [$certificate, $document]) }}">Download PDF</a>
      </div>
    @endforeach
  </div>
</div>
@endsection
