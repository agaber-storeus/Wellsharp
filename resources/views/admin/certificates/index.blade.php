@extends('layouts.admin')

@section('admin-content')
<style>[x-cloak]{display:none!important}.certificate-table-loading{opacity:.55;pointer-events:none}.certificate-sort{border:0;background:transparent;color:inherit;font:inherit;font-weight:600;cursor:pointer;padding:0}.certificate-sort:hover{color:var(--admin-blue)}.certificate-sort-icon{font-size:11px;margin-left:3px}.certificate-error{background:#fef3f2;color:#b42318;border-radius:5px;padding:10px 12px;margin-bottom:15px}</style>
<div class="page-head"><div><span class="admin-kicker">Certificate Management</span><h1>Certificates</h1><p>Search, filter, and sort every certificate without leaving this page.</p></div></div>
<div class="card" x-data="certificateTable(@js(route('admin.certificates.data')), @js($initialCertificates), @js($initialMeta), @js($search), @js($status))">
    <form class="search" x-on:submit.prevent="load(1)">
        <input x-model="search" x-on:input.debounce.350ms="load(1)" placeholder="Search certificate, student, class, Subject, or provider" autocomplete="off">
        <select x-model="status" x-on:change="load(1)"><option value="">All statuses</option>@foreach(\App\Enums\CertificateStatus::cases() as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</select>
        <button class="btn secondary" type="submit">Apply</button>
        <button class="btn secondary" type="button" x-show="hasFilters()" x-on:click="clearFilters()" x-cloak>Clear</button>
    </form>
    <div class="certificate-error" x-show="error" x-text="error" x-cloak></div>
    <div class="table-wrap" x-bind:class="loading ? 'certificate-table-loading' : ''">
        <table class="table"><thead><tr>
            <th><button class="certificate-sort" type="button" x-on:click="sortBy('certificate_number')">Certificate <span class="certificate-sort-icon" x-text="sortIcon('certificate_number')"></span></button></th>
            <th><button class="certificate-sort" type="button" x-on:click="sortBy('student_name')">Student <span class="certificate-sort-icon" x-text="sortIcon('student_name')"></span></button></th>
            <th><button class="certificate-sort" type="button" x-on:click="sortBy('subject_name')">Subject / Exam <span class="certificate-sort-icon" x-text="sortIcon('subject_name')"></span></button></th>
            <th><button class="certificate-sort" type="button" x-on:click="sortBy('class_number')">Class <span class="certificate-sort-icon" x-text="sortIcon('class_number')"></span></button></th>
            <th><button class="certificate-sort" type="button" x-on:click="sortBy('provider_name')">Provider <span class="certificate-sort-icon" x-text="sortIcon('provider_name')"></span></button></th>
            <th><button class="certificate-sort" type="button" x-on:click="sortBy('score')">Score <span class="certificate-sort-icon" x-text="sortIcon('score')"></span></button></th>
            <th><button class="certificate-sort" type="button" x-on:click="sortBy('issued_at')">Issued <span class="certificate-sort-icon" x-text="sortIcon('issued_at')"></span></button></th>
            <th><button class="certificate-sort" type="button" x-on:click="sortBy('status')">Status <span class="certificate-sort-icon" x-text="sortIcon('status')"></span></button></th><th></th>
        </tr></thead><tbody>
            <template x-for="certificate in rows" :key="certificate.id"><tr>
                <td><a x-bind:href="certificate.certificate_url" x-text="certificate.certificate_number"></a></td>
                <td><span x-text="certificate.student_name"></span><br><small class="muted" x-text="certificate.student_wellsharp_id"></small></td>
                <td><span x-text="certificate.subject_name"></span><br><small class="muted" x-text="certificate.exam_name"></small></td>
                <td x-text="certificate.class_number || '-' "></td>
                <td x-text="certificate.provider_name || '-' "></td>
                <td x-text="Number(certificate.score).toFixed(2) + '%' "></td>
                <td><span x-text="certificate.issued_at || '-' "></span><br><small class="muted" x-text="certificate.document_count + ' documents' "></small></td>
                <td><span class="badge" x-bind:class="certificate.status" x-text="certificate.status_label"></span></td>
                <td><a x-bind:href="certificate.certificate_url">Details</a></td>
            </tr></template>
            <tr x-show="!loading && rows.length === 0" x-cloak><td colspan="9" class="muted">No certificates found.</td></tr>
        </tbody></table>
    </div>
    <div class="actions" style="justify-content:space-between;margin-top:18px"><span class="muted" x-text="meta.total ? 'Showing '+meta.from+' to '+meta.to+' of '+meta.total+' certificates' : 'No certificates found'"></span><div class="actions"><button class="btn secondary" type="button" x-on:click="load(meta.current_page - 1)" x-bind:disabled="loading || meta.current_page <= 1">Previous</button><span class="muted" x-text="meta.current_page+' / '+meta.last_page"></span><button class="btn secondary" type="button" x-on:click="load(meta.current_page + 1)" x-bind:disabled="loading || meta.current_page >= meta.last_page">Next</button></div></div>
</div>
<script>
    window.certificateTable = function (endpoint, initialRows, initialMeta, initialSearch, initialStatus) {
        return {
            endpoint,
            rows: initialRows || [],
            meta: initialMeta || { current_page: 1, last_page: 1, total: 0, from: null, to: null },
            search: initialSearch || '',
            status: initialStatus || '',
            sort: 'issued_at',
            direction: 'desc',
            loading: false,
            error: '',
            request: null,
            hasFilters() { return this.search || this.status; },
            clearFilters() { this.search = ''; this.status = ''; this.load(1); },
            sortIcon(field) { return this.sort === field ? (this.direction === 'asc' ? '▲' : '▼') : '↕'; },
            sortBy(field) { if (this.sort === field) this.direction = this.direction === 'asc' ? 'desc' : 'asc'; else { this.sort = field; this.direction = 'asc'; } this.load(1); },
            async load(page) {
                page = Math.max(1, page || 1);
                if (this.request) this.request.abort();
                this.request = new AbortController();
                const params = new URLSearchParams({ page, sort: this.sort, direction: this.direction });
                if (this.search) params.set('search', this.search);
                if (this.status) params.set('status', this.status);
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
                    if (this.request?.signal.aborted !== true) this.loading = false;
                }
            }
        };
    };
</script>
@endsection
