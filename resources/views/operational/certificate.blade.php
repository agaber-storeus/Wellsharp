@extends('layouts.operational')

@section('operational-content')
@php
  $operationalRoutePrefix = auth()->user()->hasRole('proctor') ? 'proctor' : 'instructor';
@endphp
<style>[x-cloak]{display:none!important}.certificate-table-loading{opacity:.55;pointer-events:none}.certificate-sort{border:0;background:transparent;color:inherit;font:inherit;font-weight:600;cursor:pointer;padding:0}.certificate-sort:hover{color:var(--admin-blue,#0b5fff)}.certificate-sort-icon{font-size:11px;margin-left:3px}.certificate-table-error{background:#fef3f2;color:#b42318;border-radius:5px;padding:10px 12px;margin-bottom:15px}</style>

<div class="certificate-workspace-standard" x-data="certificateTable(@js(route($operationalRoutePrefix.'.certificate.data')), @js($initialCertificates), @js(['current_page' => $certificates->currentPage(), 'last_page' => $certificates->lastPage(), 'total' => $certificates->total(), 'from' => $certificates->firstItem(), 'to' => $certificates->lastItem()]), @js(route($operationalRoutePrefix.'.certificate.export')), @js($filters))">
  <section class="panel certificate-search-panel">
    <div class="panel-title">Search Options</div>
    <div class="panel-body">
      <form class="search-form certificate-search-form" x-on:submit.prevent="load(1)">
        <div>
          <div class="form-line"><label>Name:</label><div class="split-inputs"><input id="certificate-first-name" x-model.debounce.350ms="filters.first_name" x-on:input="load(1)" placeholder="First" /><input id="certificate-last-name" x-model.debounce.350ms="filters.last_name" x-on:input="load(1)" placeholder="Last" /></div></div>
          <div class="form-line"><label for="certificate-email">Email:</label><input id="certificate-email" type="email" x-model.debounce.350ms="filters.email" x-on:input="load(1)" placeholder="Email" /></div>
          <div class="form-line"><label for="certificate-id">Certificate ID:</label><input id="certificate-id" x-model.debounce.350ms="filters.certificate_id" x-on:input="load(1)" placeholder="Certificate ID" /></div>
          <div class="form-line"><label>Date Filter:</label><div class="date-inputs"><input type="date" x-model="filters.start_date" x-on:change="load(1)" aria-label="Start date" /><span>to</span><input type="date" x-model="filters.end_date" x-on:change="load(1)" aria-label="End date" /></div></div>
          <div class="form-line"><label for="certificate-class">Class ID:</label><input id="certificate-class" x-model.debounce.350ms="filters.class_id" x-on:input="load(1)" placeholder="Class ID" /></div>
        </div>
        <div>
          <div class="form-line"><label for="certificate-provider">Provider:</label><select id="certificate-provider" x-model="filters.provider_id" x-on:change="load(1)"><option value="">All Providers</option>@foreach($providers as $provider)<option value="{{ $provider->id }}">{{ $provider->name }}</option>@endforeach</select></div>
          <div class="form-line"><label for="certificate-instructor">Instructor:</label><select id="certificate-instructor" x-model="filters.instructor_id" x-on:change="load(1)"><option value="">All Instructors</option>@foreach($instructors as $instructor)<option value="{{ $instructor->id }}">{{ $instructor->display_name }}</option>@endforeach</select></div>
          <div class="form-line"><label for="certificate-level">Course Level:</label><select id="certificate-level" x-model="filters.level_id" x-on:change="load(1)"><option value="">All Courses</option>@foreach($levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select></div>
          <div class="form-line"><label for="certificate-supplement">Supplement:</label><select id="certificate-supplement" x-model="filters.supplement_id" x-on:change="load(1)"><option value="">All Supplements</option>@foreach($supplements as $supplement)<option value="{{ $supplement->id }}">{{ $supplement->name }}</option>@endforeach</select></div>
        </div>
        <div class="search-actions"><button class="blue-btn" type="submit">Search Certificates</button></div>
      </form>
      <div class="show-row">Show <select x-model.number="perPage" x-on:change="load(1)"><option value="200">200</option><option value="25">25</option></select> Most Recent Certificates</div>
    </div>
  </section>

  <div class="certificate-actions-top">
    <a class="export-btn" x-bind:href="exportUrl">Export Excel (CSV)</a>
    <button class="columns-btn" type="button" x-on:click="showEmail = !showEmail; showProvider = !showProvider; showInstructor = !showInstructor">Show / hide columns</button>
  </div>

  <div class="certificate-table-error" x-show="error" x-text="error" x-cloak></div>

  <div x-bind:class="loading ? 'certificate-table-loading' : ''">
    <table class="certificate-table certificate-standard-table">
      <thead><tr>
        <th><button class="certificate-sort" type="button" x-on:click="sortBy('student_name')">Name <span class="certificate-sort-icon" x-text="sortIcon('student_name')"></span></button></th>
        <th><button class="certificate-sort" type="button" x-on:click="sortBy('issued_at')">Date Issued <span class="certificate-sort-icon" x-text="sortIcon('issued_at')"></span></button></th>
        <th x-show="showProvider"><button class="certificate-sort" type="button" x-on:click="sortBy('provider_name')">Provider <span class="certificate-sort-icon" x-text="sortIcon('provider_name')"></span></button></th>
        <th x-show="showInstructor"><button class="certificate-sort" type="button" x-on:click="sortBy('instructor_name')">Instructor <span class="certificate-sort-icon" x-text="sortIcon('instructor_name')"></span></button></th>
        <th>Course</th>
        <th x-show="showEmail">Email</th>
        <th>Options</th>
      </tr></thead>
      <tbody>
        <template x-for="certificate in rows" :key="certificate.id">
          <tr>
            <td x-text="certificate.student_name"></td>
            <td x-text="certificate.issued_at || '—'"></td>
            <td x-show="showProvider" x-text="certificate.provider || '—'"></td>
            <td x-show="showInstructor" x-text="certificate.instructor || '—'"></td>
            <td x-text="certificate.course"></td>
            <td x-show="showEmail" x-text="certificate.student_email || '—'"></td>
            <td>
              <template x-if="certificate.preview_url">
                <select class="certificate-action-select" x-on:change="handleAction($event, certificate)" x-bind:aria-label="'Certificate actions for ' + certificate.student_name">
                  <option value="">Select an Action</option>
                  <option value="preview">Preview Certificate</option>
                  <option value="download">Download Certificate</option>
                  <option value="email">Email to Trainee</option>
                </select>
              </template>
              <template x-if="!certificate.preview_url"><span class="muted">No document</span></template>
            </td>
          </tr>
        </template>
        <tr x-show="!loading && rows.length === 0" x-cloak><td colspan="7">No certificate records match the selected search.</td></tr>
      </tbody>
    </table>
  </div>
  <div class="table-foot certificate-foot">
    <span x-text="meta.total ? 'Showing '+meta.from+' to '+meta.to+' of '+meta.total+' certificates' : 'No certificates found'"></span>
    <div class="actions"><button class="btn secondary" type="button" x-on:click="load(meta.current_page - 1)" x-bind:disabled="loading || meta.current_page <= 1">Previous</button><span class="muted" x-text="meta.current_page+' / '+meta.last_page"></span><button class="btn secondary" type="button" x-on:click="load(meta.current_page + 1)" x-bind:disabled="loading || meta.current_page >= meta.last_page">Next</button></div>
  </div>

  <div class="certificate-preview-overlay" x-show="previewUrl" x-cloak x-on:click="previewUrl = null"></div>
  <section class="certificate-preview-modal" x-show="previewUrl" x-cloak x-on:keydown.escape.window="previewUrl = null">
    <button type="button" class="modal-close certificate-preview-close" aria-label="Close certificate preview" x-on:click="previewUrl = null">×</button>
    <div class="certificate-preview-frame"><iframe x-bind:src="previewUrl" title="Certificate preview"></iframe></div>
  </section>
</div>
@endsection

@push('scripts')
<script>
  window.certificateTable = function (endpoint, initialRows, initialMeta, exportEndpoint, initialFilters) {
    var blankFilters = function () {
      return { first_name: '', last_name: '', email: '', certificate_id: '', start_date: '', end_date: '', class_id: '', provider_id: '', instructor_id: '', level_id: '', supplement_id: '' };
    };
    var provided = Object.assign({}, initialFilters || {});
    var initialPerPage = parseInt(provided.per_page, 10) === 25 ? 25 : 200;
    delete provided.per_page;

    return {
      endpoint, exportEndpoint,
      rows: initialRows || [],
      meta: initialMeta || { current_page: 1, last_page: 1, total: 0, from: null, to: null },
      filters: Object.assign(blankFilters(), provided),
      perPage: initialPerPage,
      sort: 'issued_at',
      direction: 'desc',
      loading: false,
      error: '',
      request: null,
      showEmail: true,
      showProvider: true,
      showInstructor: true,
      previewUrl: null,
      get exportUrl() {
        var params = new URLSearchParams();
        Object.keys(this.filters).forEach((key) => { if (this.filters[key]) params.set(key, this.filters[key]); });
        var query = params.toString();
        return this.exportEndpoint + (query ? '?' + query : '');
      },
      sortIcon(field) { return this.sort === field ? (this.direction === 'asc' ? '▲' : '▼') : '↕'; },
      sortBy(field) { if (this.sort === field) this.direction = this.direction === 'asc' ? 'desc' : 'asc'; else { this.sort = field; this.direction = 'asc'; } this.load(1); },
      async load(page) {
        page = Math.max(1, page || 1);
        if (this.request) this.request.abort();
        this.request = new AbortController();
        var params = new URLSearchParams({ page, sort: this.sort, direction: this.direction, per_page: this.perPage });
        Object.keys(this.filters).forEach((key) => { if (this.filters[key]) params.set(key, this.filters[key]); });
        this.loading = true;
        this.error = '';
        try {
          const response = await fetch(this.endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' }, signal: this.request.signal });
          if (!response.ok) throw new Error('Certificate search failed');
          const payload = await response.json();
          this.rows = payload.data;
          this.meta = payload.meta;
        } catch (error) {
          if (error.name !== 'AbortError') this.error = 'Certificates could not be loaded. Try again.';
        } finally {
          this.loading = false;
        }
      },
      handleAction(event, certificate) {
        var value = event.target.value;
        if (value === 'preview') this.previewUrl = certificate.preview_url;
        if (value === 'download') window.location.assign(certificate.download_url);
        if (value === 'email') {
          var email = certificate.student_email || '';
          window.location.href = 'mailto:' + email + '?subject=IADC%20WellSharp%20Certificate&body=Please%20find%20your%20IADC%20WellSharp%20certificate%20in%20the%20portal.';
        }
        event.target.value = '';
      }
    };
  };
</script>
@endpush
