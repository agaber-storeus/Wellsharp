@extends('layouts.operational')

@section('operational-content')
@php
  $operationalRoutePrefix = auth()->user()->hasRole('proctor') ? 'proctor' : 'instructor';
  $filter = fn (string $key, mixed $fallback = '') => old($key, $filters[$key] ?? $fallback);
  $perPage = (int) $filter('per_page', 200);
  $exportUrl = route($operationalRoutePrefix.'.certificate.export', array_filter($filters, fn ($value) => filled($value)));
@endphp

<div class="certificate-workspace-standard" x-data="{ showEmail: true, showProvider: true, showInstructor: true }">
  <section class="panel certificate-search-panel">
    <div class="panel-title">Search Options</div>
    <div class="panel-body">
      <form id="certificate-search" class="search-form certificate-search-form" method="GET" action="{{ route($operationalRoutePrefix.'.certificate') }}">
        <div>
          <div class="form-line"><label>Name:</label><div class="split-inputs"><input id="certificate-first-name" name="first_name" placeholder="First" value="{{ $filter('first_name') }}" /><input id="certificate-last-name" name="last_name" placeholder="Last" value="{{ $filter('last_name') }}" /></div></div>
          <div class="form-line"><label for="certificate-email">Email:</label><input id="certificate-email" name="email" type="email" placeholder="Email" value="{{ $filter('email') }}" /></div>
          <div class="form-line"><label for="certificate-id">Certificate ID:</label><input id="certificate-id" name="certificate_id" placeholder="Certificate ID" value="{{ $filter('certificate_id') }}" /></div>
          <div class="form-line"><label>Date Filter:</label><div class="date-inputs"><input name="start_date" type="date" value="{{ $filter('start_date') }}" aria-label="Start date" /><span>to</span><input name="end_date" type="date" value="{{ $filter('end_date') }}" aria-label="End date" /></div></div>
          <div class="form-line"><label for="certificate-class">Class ID:</label><input id="certificate-class" name="class_id" placeholder="Class ID" value="{{ $filter('class_id') }}" /></div>
        </div>
        <div>
          <div class="form-line"><label for="certificate-provider">Provider:</label><select id="certificate-provider" name="provider_id"><option value="">All Providers</option>@foreach($providers as $provider)<option value="{{ $provider->id }}" @selected((string) $filter('provider_id') === (string) $provider->id)>{{ $provider->name }}</option>@endforeach</select></div>
          <div class="form-line"><label for="certificate-instructor">Instructor:</label><select id="certificate-instructor" name="instructor_id"><option value="">All Instructors</option>@foreach($instructors as $instructor)<option value="{{ $instructor->id }}" @selected((string) $filter('instructor_id') === (string) $instructor->id)>{{ $instructor->display_name }}</option>@endforeach</select></div>
          <div class="form-line"><label for="certificate-level">Course Level:</label><select id="certificate-level" name="level_id"><option value="">All Courses</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected((string) $filter('level_id') === (string) $level->id)>{{ $level->name }}</option>@endforeach</select></div>
          <div class="form-line"><label for="certificate-supplement">Supplement:</label><select id="certificate-supplement" name="supplement_id"><option value="">All Supplements</option>@foreach($supplements as $supplement)<option value="{{ $supplement->id }}" @selected((string) $filter('supplement_id') === (string) $supplement->id)>{{ $supplement->name }}</option>@endforeach</select></div>
        </div>
        <div class="search-actions"><button class="blue-btn" type="submit">Search Certificates</button><a class="tiny-blue" href="{{ route($operationalRoutePrefix.'.certificate') }}">Clear</a></div>
      </form>
      <div class="show-row">Show <select form="certificate-search" name="per_page"><option value="200" @selected($perPage !== 25)>200</option><option value="25" @selected($perPage === 25)>25</option></select> Most Recent Certificates</div>
    </div>
  </section>

  <div class="certificate-actions-top">
    <a class="export-btn" href="{{ $exportUrl }}">Export Excel (CSV)</a>
    <button class="columns-btn" type="button" x-on:click="showEmail = !showEmail; showProvider = !showProvider; showInstructor = !showInstructor">Show / hide columns</button>
  </div>

  <table class="certificate-table certificate-standard-table">
    <thead><tr><th>Name</th><th>Date Issued</th><th x-show="showProvider">Provider</th><th x-show="showInstructor">Instructor</th><th>Course</th><th x-show="showEmail">Email</th><th>Options</th></tr></thead>
    <tbody>
      @forelse($certificates as $certificate)
        @php
          $certificateDocument = $certificate->documents->first();
          $courseLabel = collect([$certificate->subject_name ?: $certificate->exam?->subject?->name, $certificate->exam?->subject?->level?->name, $certificate->exam?->subject?->stacks?->pluck('name')->join(', ')])->filter()->join(', ');
        @endphp
        <tr>
          <td><a href="{{ route('certificates.show', $certificate) }}" title="{{ $certificate->certificate_number }}">{{ $certificate->student_name }}</a></td>
          <td>{{ $certificate->issued_at?->format('Y-m-d g:i A') ?: '—' }}</td>
          <td x-show="showProvider">{{ $certificate->provider_name ?: $certificate->provider?->name ?: '—' }}</td>
          <td x-show="showInstructor">{{ $certificate->instructor_name ?: $certificate->instructor?->display_name ?: '—' }}</td>
          <td>{{ $courseLabel ?: $certificate->exam_name }}</td>
          <td x-show="showEmail">{{ $certificate->student_email ?: '—' }}</td>
          <td>
            @if($certificateDocument)
              <select class="certificate-action-select" data-preview-url="{{ route('certificates.documents.show', [$certificate, $certificateDocument]) }}" data-download-url="{{ route('certificates.documents.download', [$certificate, $certificateDocument]) }}" data-email="{{ $certificate->student_email }}" aria-label="Certificate actions for {{ $certificate->student_name }}">
                <option value="">Select an Action</option>
                <option value="preview">Preview Certificate</option>
                <option value="download">Download Certificate</option>
                <option value="email">Email to Trainee</option>
              </select>
            @else
              <span class="muted">No document</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7">No certificate records match the selected search.</td></tr>
      @endforelse
    </tbody>
  </table>
  <div class="table-foot certificate-foot"><span>Showing {{ $certificates->count() }} of {{ $certificates->total() }} certificates</span><span>{{ $certificates->links() }}</span></div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  function closePreview() {
    document.getElementById('certificatePreviewOverlay')?.remove();
    document.getElementById('certificatePreviewModal')?.remove();
  }

  function openPreview(url) {
    closePreview();
    const overlay = document.createElement('div');
    overlay.id = 'certificatePreviewOverlay';
    overlay.className = 'certificate-preview-overlay';
    const modal = document.createElement('section');
    modal.id = 'certificatePreviewModal';
    modal.className = 'certificate-preview-modal';
    modal.innerHTML = '<button type="button" class="modal-close certificate-preview-close" aria-label="Close certificate preview">×</button><div class="certificate-preview-frame"><iframe src="' + url + '" title="Certificate preview"></iframe></div>';
    document.body.append(overlay, modal);
  }

  document.addEventListener('change', (event) => {
    const select = event.target.closest('.certificate-action-select');
    if (!select) return;
    if (select.value === 'preview') openPreview(select.dataset.previewUrl);
    if (select.value === 'download') window.location.assign(select.dataset.downloadUrl);
    if (select.value === 'email') {
      const email = select.dataset.email || '';
      window.location.href = 'mailto:' + email + '?subject=IADC%20WellSharp%20Certificate&body=Please%20find%20your%20IADC%20WellSharp%20certificate%20in%20the%20portal.';
    }
    select.value = '';
  });

  document.addEventListener('click', (event) => {
    if (event.target.id === 'certificatePreviewOverlay' || event.target.closest('.certificate-preview-close')) {
      event.preventDefault();
      closePreview();
    }
  });
})();
</script>
@endpush
