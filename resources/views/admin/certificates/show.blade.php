@extends('layouts.admin')

@section('admin-content')
<div class="page-head hero"><div><span class="admin-kicker">Certificate Details</span><h1>{{ $certificate->certificate_number }}</h1><p>Issued {{ $certificate->issued_at?->format('F j, Y H:i') }}</p></div><a class="btn secondary" href="{{ route('admin.certificates.index') }}">Back to certificates</a></div>
<div class="admin-bento">
<div class="admin-bento-card admin-bento-card--wide"><div class="admin-card-head"><span class="admin-card-icon">🎖️</span><h3>Certificate status</h3><span class="badge {{ $certificate->status->value }}" style="margin-left:auto">{{ $certificate->status->label() }}</span></div><p class="admin-card-note">This certificate is linked to one submitted exam attempt and contains three documents.</p><div class="admin-meta-grid"><div class="admin-meta-item"><span class="muted">Certificate number</span><strong>{{ $certificate->certificate_number }}</strong></div><div class="admin-meta-item"><span class="muted">Score</span><strong>{{ number_format((float) $certificate->score, 2) }}%</strong> <span class="muted">/ passing {{ $certificate->passing_score }}%</span></div><div class="admin-meta-item"><span class="muted">Exam attempt</span><strong>{{ $certificate->attempt?->public_id ?: '—' }}</strong></div><div class="admin-meta-item"><span class="muted">Issued date</span><strong>{{ $certificate->issued_at?->format('Y-m-d H:i') }}</strong></div><div class="admin-meta-item"><span class="muted">Valid through</span><strong>{{ $certificate->expires_at?->format('Y-m-d') ?: 'Not configured' }}</strong></div><div class="admin-meta-item"><span class="muted">Documents</span><strong>{{ $certificate->documents->count() }}</strong></div></div></div>

<div class="admin-bento-card"><div class="admin-card-head"><span class="admin-card-icon cool">🎓</span><h3>Student</h3><a style="margin-left:auto" href="{{ $certificate->student ? route('admin.users.show', $certificate->student) : '#' }}">Open user</a></div><div class="admin-meta-grid"><div class="admin-meta-item"><span class="muted">Name</span><strong>{{ $certificate->student_name }}</strong></div><div class="admin-meta-item"><span class="muted">WellSharp ID</span><strong>{{ $certificate->student_wellsharp_id }}</strong></div><div class="admin-meta-item"><span class="muted">Email</span><strong>{{ $certificate->student_email ?: '—' }}</strong></div></div></div>

<div class="admin-bento-card"><div class="admin-card-head"><span class="admin-card-icon cool">📚</span><h3>Training and assessment</h3></div><div class="admin-meta-grid"><div class="admin-meta-item"><span class="muted">Subject</span><strong>{{ $certificate->subject_name }}</strong></div><div class="admin-meta-item"><span class="muted">Exam</span><strong>{{ $certificate->exam_name }}{{ $certificate->exam_code ? ' ('.$certificate->exam_code.')' : '' }}</strong></div><div class="admin-meta-item"><span class="muted">Class</span><strong>{{ $certificate->class_number ?: '—' }}</strong></div><div class="admin-meta-item"><span class="muted">Group</span><strong>{{ $certificate->group_name ?: '—' }}</strong></div><div class="admin-meta-item"><span class="muted">Training provider</span><strong>{{ $certificate->provider_name ?: '—' }}</strong></div><div class="admin-meta-item"><span class="muted">Instructor</span><strong>{{ $certificate->instructor_name ?: '—' }}</strong></div></div></div>

<div class="admin-bento-card admin-bento-card--wide certificate-documents-admin-card">
  <div class="admin-card-head"><span class="admin-card-icon">📄</span><h3>Certificate documents</h3><a class="btn secondary" style="margin-left:auto" href="{{ route('certificates.show', $certificate) }}">Open bundle</a></div>
  <p class="admin-card-note">Open a document to view it, or download an individual PDF.</p>
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
</div>
@endsection
