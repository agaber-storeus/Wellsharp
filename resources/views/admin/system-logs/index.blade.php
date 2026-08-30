@extends('layouts.admin')
@section('admin-content')
<style>
[x-cloak]{display:none!important}
.admin-table-loading{opacity:.55;pointer-events:none}
.admin-table-error{background:#fef3f2;color:#b42318;border-radius:5px;padding:10px 12px;margin-bottom:15px}
.system-log-search{flex-wrap:wrap;row-gap:10px}
.system-log-search input,.system-log-search select{min-width:150px}
.system-log-search .search-actions{display:flex;gap:9px;margin-left:auto}
.system-log-event small,.system-log-actor small{display:block;color:var(--admin-muted);margin-top:2px}
.system-log-mono{font-family:'JetBrains Mono',monospace;font-size:12px}
.badge.info{background:var(--admin-accent-cool-soft);color:var(--admin-accent-cool)}
.badge.warning{background:var(--admin-warning-soft);color:var(--admin-warning)}
</style>
<div class="admin-page-head"><div><h1>System Logs</h1><p>Search, filter, and review business, operational, and authentication activity across WellSharp.</p></div></div>
<div class="card" x-data="systemLogTable(@js(route('admin.system-logs.data')), @js($initialLogs), @js($initialMeta))">
    <form class="search system-log-search" x-on:submit.prevent="load(1)">
        <input type="date" x-model="dateFrom" x-on:change="load(1)" aria-label="From date">
        <input type="date" x-model="dateTo" x-on:change="load(1)" aria-label="To date">
        <select x-model="category" x-on:change="load(1)" aria-label="Category">
            <option value="">All Categories</option>
            @foreach($categories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        <select x-model="action" x-on:change="load(1)" aria-label="Event">
            <option value="">All Events</option>
            @foreach($actions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        <select x-model="actorId" x-on:change="load(1)" aria-label="Actor">
            <option value="">All Actors</option>
            @foreach($actors as $actor)<option value="{{ $actor->id }}">{{ $actor->display_name }} ({{ $actor->wellsharp_id }})</option>@endforeach
        </select>
        <select x-model="actorRole" x-on:change="load(1)" aria-label="Actor role">
            <option value="">All Roles</option>
            @foreach($roles as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        <select x-model="subjectType" x-on:change="load(1)" aria-label="Resource type">
            <option value="">All Resources</option>
            @foreach($subjectTypes as $fqcn => $label)<option value="{{ $fqcn }}">{{ $label }}</option>@endforeach
        </select>
        <select x-model="result" x-on:change="load(1)" aria-label="Result">
            <option value="">Any Result</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
            <option value="system">System</option>
        </select>
        <input x-model="correlationId" x-on:input.debounce.350ms="load(1)" placeholder="Correlation ID" autocomplete="off" aria-label="Correlation ID">
        <input x-model="search" x-on:input.debounce.350ms="load(1)" placeholder="Search action, actor, or ID" autocomplete="off" aria-label="Search">
        <span class="search-actions"><button class="btn secondary" type="submit">Apply</button><button class="btn secondary" type="button" x-show="hasFilters()" x-on:click="clearFilters()" x-cloak>Clear</button></span>
    </form>
    <div class="admin-table-error" x-show="error" x-text="error" x-cloak></div>
    <div class="table-wrap" x-bind:class="loading ? 'admin-table-loading' : ''">
        <table class="table">
            <thead><tr><th>Date / time</th><th>Category</th><th>Event</th><th>Actor</th><th>Subject</th><th>Result</th><th>Details</th></tr></thead>
            <tbody>
                <template x-for="entry in rows" :key="entry.id">
                    <tr>
                        <td x-text="entry.occurred_at"></td>
                        <td x-text="entry.category_label"></td>
                        <td class="system-log-event"><span x-text="entry.label"></span><small x-show="entry.reason" x-text="entry.reason"></small><small x-show="entry.correlation_id" class="system-log-mono" x-text="'Correlation: ' + entry.correlation_id"></small></td>
                        <td class="system-log-actor"><span x-text="entry.actor"></span><small x-show="entry.actor_role" x-text="entry.actor_role"></small></td>
                        <td x-text="entry.subject || '—'"></td>
                        <td><span class="badge" x-bind:class="entry.severity" x-text="entry.result ? (entry.result.charAt(0).toUpperCase() + entry.result.slice(1)) : '—'"></span></td>
                        <td><a x-bind:href="entry.detail_url">View</a></td>
                    </tr>
                </template>
                <tr x-show="!loading && rows.length === 0" x-cloak><td colspan="7"><div class="admin-empty-row"><span class="admin-empty-row-icon">🔍</span>No activity found for these filters.</div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="actions" style="justify-content:space-between;margin-top:18px"><span class="muted" x-text="meta.total ? 'Showing '+meta.from+' to '+meta.to+' of '+meta.total+' events' : 'No events found'"></span><div class="actions"><button class="btn secondary" type="button" x-on:click="load(meta.current_page - 1)" x-bind:disabled="loading || meta.current_page <= 1">Previous</button><span class="muted" x-text="meta.current_page+' / '+meta.last_page"></span><button class="btn secondary" type="button" x-on:click="load(meta.current_page + 1)" x-bind:disabled="loading || meta.current_page >= meta.last_page">Next</button></div></div>
</div>
<script>
    window.systemLogTable = function (endpoint, initialRows, initialMeta) {
        return {
            endpoint, rows: initialRows || [], meta: initialMeta || { current_page: 1, last_page: 1, total: 0, from: null, to: null },
            dateFrom: @js($filters['date_from'] ?? ''), dateTo: @js($filters['date_to'] ?? ''), category: @js($filters['category'] ?? ''),
            action: @js($filters['action'] ?? ''), actorId: @js((string) ($filters['actor_id'] ?? '')), actorRole: @js($filters['actor_role'] ?? ''),
            subjectType: @js($filters['subject_type'] ?? ''), result: @js($filters['result'] ?? ''), correlationId: @js($filters['correlation_id'] ?? ''),
            search: @js($filters['search'] ?? ''), loading: false, error: '', request: null,
            hasFilters() { return this.dateFrom || this.dateTo || this.category || this.action || this.actorId || this.actorRole || this.subjectType || this.result || this.correlationId || this.search; },
            clearFilters() { this.dateFrom = ''; this.dateTo = ''; this.category = ''; this.action = ''; this.actorId = ''; this.actorRole = ''; this.subjectType = ''; this.result = ''; this.correlationId = ''; this.search = ''; this.load(1); },
            async load(page) {
                page = Math.max(1, page || 1);
                if (this.request) this.request.abort();
                this.request = new AbortController();
                const params = new URLSearchParams({ page });
                if (this.dateFrom) params.set('date_from', this.dateFrom);
                if (this.dateTo) params.set('date_to', this.dateTo);
                if (this.category) params.set('category', this.category);
                if (this.action) params.set('action', this.action);
                if (this.actorId) params.set('actor_id', this.actorId);
                if (this.actorRole) params.set('actor_role', this.actorRole);
                if (this.subjectType) params.set('subject_type', this.subjectType);
                if (this.result) params.set('result', this.result);
                if (this.correlationId) params.set('correlation_id', this.correlationId);
                if (this.search) params.set('search', this.search);
                this.loading = true; this.error = '';
                try {
                    const response = await fetch(this.endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' }, signal: this.request.signal });
                    if (!response.ok) throw new Error('System log search failed');
                    const payload = await response.json();
                    this.rows = payload.data; this.meta = payload.meta;
                } catch (error) {
                    if (error.name !== 'AbortError') this.error = 'System logs could not be loaded. Try again.';
                } finally {
                    this.loading = false;
                }
            }
        };
    };
</script>
@endsection
