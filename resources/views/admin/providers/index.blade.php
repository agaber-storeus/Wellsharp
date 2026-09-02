@extends('layouts.admin')

@section('admin-content')
<style>
    [x-cloak] { display: none !important; }
    .admin-table-loading { opacity: .55; pointer-events: none; }
    .admin-sort { border: 0; background: transparent; color: inherit; font: inherit; font-weight: 600; cursor: pointer; padding: 0; }
    .admin-sort:hover { color: var(--admin-blue); }
    .admin-sort-icon { font-size: 11px; margin-left: 3px; }
    .admin-table-error { background: #fef3f2; color: #b42318; border-radius: 5px; padding: 10px 12px; margin-bottom: 15px; }
    .provider-status-cell { display: flex; align-items: center; min-width: 145px; }
    .provider-status-toggle { cursor: pointer !important; pointer-events: auto !important; touch-action: manipulation; }
    .provider-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .provider-row-error { display: block; color: #b42318; font-size: 12px; margin-top: 5px; }
</style>

<div class="page-head">
    <div><h1>Training providers</h1><p>Search, filter, sort, and update provider status without leaving this page.</p></div>
    <a class="btn" href="{{ route('admin.providers.create') }}">Add provider</a>
</div>

<div class="card" x-data="providerTable(@js(route('admin.providers.data')), @js($initialProviders), @js($initialMeta), @js($sort), @js($direction))">
    <form class="search" x-on:submit.prevent="load(1, true)">
        <input x-model="search" x-on:input.debounce.350ms="load(1)" placeholder="Search provider number, name, or email" autocomplete="off">
        <select x-model="status" x-on:change="load(1)">
            <option value="">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option>
        </select>
        <button class="btn secondary" type="submit">Apply</button>
        <button class="btn secondary" type="button" x-show="hasFilters()" x-on:click="clearFilters()" x-cloak>Clear</button>
    </form>

    <div class="admin-table-error" x-show="error" x-text="error" x-cloak></div>
    <div class="table-wrap" x-bind:class="loading ? 'admin-table-loading' : ''">
        <table class="table">
            <thead><tr><th><button class="admin-sort" type="button" x-on:click="sortBy('provider_number')">Provider number <span class="admin-sort-icon" x-text="sortIcon('provider_number')"></span></button></th><th><button class="admin-sort" type="button" x-on:click="sortBy('name')">Name <span class="admin-sort-icon" x-text="sortIcon('name')"></span></button></th><th><button class="admin-sort" type="button" x-on:click="sortBy('email')">Contact <span class="admin-sort-icon" x-text="sortIcon('email')"></span></button></th><th><button class="admin-sort" type="button" x-on:click="sortBy('status')">Status <span class="admin-sort-icon" x-text="sortIcon('status')"></span></button></th><th></th></tr></thead>
            <tbody>
                <template x-for="provider in rows" :key="provider.id">
                    <tr>
                        <td x-text="provider.provider_number"></td><td x-text="provider.name"></td><td x-text="provider.email"></td>
                        <td>
                            <div class="provider-status-cell">
                                <template x-if="provider.status === 'archived'"><span class="badge archived">Archived</span></template>
                                <template x-if="provider.status !== 'archived'"><button class="btn secondary small provider-status-toggle" type="button" x-on:click.prevent="toggleStatus(provider)" x-bind:disabled="provider.saving || provider.archiving" x-bind:aria-busy="provider.saving ? 'true' : 'false'" x-text="provider.saving ? 'Updating...' : (provider.status === 'active' ? 'Deactivate' : 'Activate')"></button></template>
                            </div>
                        </td>
                        <td><div class="provider-actions"><a x-bind:href="provider.view_url">View</a><template x-if="provider.status !== 'archived'"><button class="btn secondary small provider-status-toggle" type="button" x-on:click.prevent="archiveProvider(provider)" x-bind:disabled="provider.archiving" x-bind:aria-busy="provider.archiving ? 'true' : 'false'" x-text="provider.archiving ? 'Updating...' : 'Archive'"></button></template><template x-if="provider.status === 'archived'"><button class="btn secondary small provider-status-toggle" type="button" x-on:click.prevent="unarchiveProvider(provider)" x-bind:disabled="provider.archiving" x-bind:aria-busy="provider.archiving ? 'true' : 'false'" x-text="provider.archiving ? 'Updating...' : 'Unarchive'"></button></template><span class="provider-row-error" x-show="provider.rowError" x-text="provider.rowError" x-cloak></span></div></td>
                    </tr>
                </template>
                <tr x-show="!loading && rows.length === 0" x-cloak><td colspan="5"><div class="admin-empty-row"><span class="admin-empty-row-icon">ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â</span>No providers found.</div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="actions" style="justify-content:space-between;margin-top:18px"><span class="muted" x-text="meta.total ? 'Showing '+meta.from+' to '+meta.to+' of '+meta.total+' providers' : 'No providers found'"></span><div class="actions"><button class="btn secondary" type="button" x-on:click="load(meta.current_page - 1, true)" x-bind:disabled="loading || meta.current_page <= 1">Previous</button><span class="muted" x-text="meta.current_page+' / '+meta.last_page"></span><button class="btn secondary" type="button" x-on:click="load(meta.current_page + 1, true)" x-bind:disabled="loading || meta.current_page >= meta.last_page">Next</button></div></div>
</div>

<script>
    window.providerTable = function (endpoint, initialRows, initialMeta, initialSort, initialDirection) {
        return {
            endpoint, rows: (initialRows || []).map(provider => ({ ...provider, archiving: false, saving: false, rowError: '' })), meta: initialMeta || { current_page: 1, last_page: 1, total: 0, from: null, to: null }, search: @js($search), status: @js($status), sort: initialSort || 'created_at', direction: initialDirection || 'desc', loading: false, error: '', request: null,
            init() { window.addEventListener('popstate', () => { const params = new URLSearchParams(window.location.search); this.search = params.get('search') || ''; this.status = params.get('status') || ''; this.sort = params.get('sort') || 'created_at'; this.direction = params.get('direction') === 'asc' ? 'asc' : 'desc'; this.load(Number(params.get('page') || 1), false); }); this.syncUrl(false); },
            syncUrl(push = false) { const url = new URL(window.location.href); const params = url.searchParams; this.search ? params.set('search', this.search) : params.delete('search'); this.status ? params.set('status', this.status) : params.delete('status'); this.sort !== 'created_at' ? params.set('sort', this.sort) : params.delete('sort'); this.direction !== 'desc' ? params.set('direction', this.direction) : params.delete('direction'); this.meta.current_page > 1 ? params.set('page', this.meta.current_page) : params.delete('page'); window.history[push ? 'pushState' : 'replaceState']({}, '', url); },
            hasFilters() { return this.search || this.status; },
            clearFilters() { this.search = ''; this.status = ''; this.load(1, true); },
            sortIcon(field) { return this.sort === field ? (this.direction === 'asc' ? 'ÃƒÂ¢Ã¢â‚¬â€œÃ‚Â²' : 'ÃƒÂ¢Ã¢â‚¬â€œÃ‚Â¼') : 'ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬Â¢'; },
            sortBy(field) { if (this.sort === field) this.direction = this.direction === 'asc' ? 'desc' : 'asc'; else { this.sort = field; this.direction = 'asc'; } this.load(1, true); },
            async load(page, push = false) { page = Math.max(1, page || 1); if (this.request) this.request.abort(); this.request = new AbortController(); const params = new URLSearchParams({ page, sort: this.sort, direction: this.direction }); if (this.search) params.set('search', this.search); if (this.status) params.set('status', this.status); this.loading = true; this.error = ''; try { const response = await fetch(this.endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' }, signal: this.request.signal }); if (!response.ok) throw new Error('Provider search failed'); const payload = await response.json(); this.rows = (payload.data || []).map(provider => ({ ...provider, archiving: false, saving: false, rowError: '' })); this.meta = payload.meta; this.syncUrl(push); } catch (error) { if (error.name !== 'AbortError') this.error = 'Providers could not be loaded. Try again.'; } finally { this.loading = false; } },
            async toggleStatus(provider) { if (provider.status === 'archived' || provider.saving || provider.archiving) return; const nextStatus = provider.status === 'active' ? 'inactive' : 'active'; provider.saving = true; this.error = ''; try { const response = await fetch(provider.status_url, { method: 'PATCH', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify({ status: nextStatus }) }); const payload = await response.json().catch(() => ({})); if (!response.ok) throw new Error(payload.message || 'Provider status could not be updated.'); provider.saved_status = payload.status; provider.status = payload.status; provider.status_label = payload.status_label; await this.load(this.meta.current_page, false); } catch (exception) { this.error = exception.message || 'Provider status could not be updated.'; } finally { provider.saving = false; } },
            async archiveProvider(provider) { if (provider.status === 'archived' || provider.saving || provider.archiving) return; await this.changeArchiveState(provider, provider.archive_url, 'Provider could not be archived.', 'archived'); },
            async unarchiveProvider(provider) { if (provider.status !== 'archived' || provider.saving || provider.archiving) return; await this.changeArchiveState(provider, provider.unarchive_url, 'Provider could not be unarchived.', 'active'); },
            async changeArchiveState(provider, url, errorMessage, state) { provider.archiving = true; provider.rowError = ''; this.error = ''; try { const response = await fetch(url, { method: 'PATCH', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'X-Requested-With': 'XMLHttpRequest' }, body: '{}' }); const payload = await response.json().catch(() => ({})); if (!response.ok) throw new Error(payload.message || errorMessage); provider.saved_status = payload.status || state; provider.status = payload.status || state; provider.status_label = payload.status_label || (state === 'active' ? 'Active' : 'Archived'); await this.load(this.meta.current_page, false); } catch (exception) { provider.rowError = exception.message || errorMessage; this.error = provider.rowError; } finally { provider.archiving = false; } }
        };
    };
</script>
@endsection
