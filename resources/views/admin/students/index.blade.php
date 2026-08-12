@extends('layouts.admin')

@section('admin-content')
<style>[x-cloak]{display:none!important}.admin-table-loading{opacity:.55;pointer-events:none}.admin-sort{border:0;background:transparent;color:inherit;font:inherit;font-weight:600;cursor:pointer;padding:0}.admin-sort:hover{color:var(--admin-blue)}.admin-sort-icon{font-size:11px;margin-left:3px}.admin-table-error{background:#fef3f2;color:#b42318;border-radius:5px;padding:10px 12px;margin-bottom:15px}.admin-table-tools{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px}.admin-table-tools .search{flex:1;margin:0}.admin-table-tools select{border:1px solid #b9c7d2;border-radius:5px;padding:9px;font:inherit;background:#fff}.admin-table-foot{display:flex;justify-content:space-between;gap:15px;align-items:center;margin-top:16px;color:var(--muted)}.admin-page-actions{display:flex;gap:7px;align-items:center}.admin-page-actions button{border:1px solid var(--line);border-radius:4px;background:#fff;padding:7px 10px;color:var(--ink);cursor:pointer}.admin-page-actions button:disabled{cursor:not-allowed;opacity:.45}</style>
<div class="page-head"><div><span class="admin-kicker">Student management</span><h1>Students</h1><p>Search, filter, and sort students without leaving this page.</p></div><a class="btn" href="{{ route('admin.students.create') }}">Create student</a></div>

<div class="card" x-data="studentTable(@js(route('admin.students.data')), @js($initialStudents), @js($initialMeta))">
  <div class="admin-table-tools">
    <form class="search" x-on:submit.prevent="load(1)">
      <input x-model="search" x-on:input.debounce.350ms="load(1)" placeholder="Search WellSharp ID, name, or email" autocomplete="off" aria-label="Search students">
      <select x-model="gender" x-on:change="load(1)" aria-label="Filter by gender"><option value="">All Genders</option><option value="Male">Male</option><option value="Female">Female</option></select>
      <select x-model="groupId" x-on:change="load(1)" aria-label="Filter by group"><option value="">All Groups</option>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select>
      <select x-model="perPage" x-on:change="load(1)" aria-label="Students per page"><option value="15">15</option><option value="30">30</option><option value="50">50</option><option value="100">100</option></select>
      <button class="btn secondary" type="submit">Apply</button>
      <button class="btn secondary" type="button" x-show="hasFilters()" x-on:click="clearFilters()" x-cloak>Clear</button>
    </form>
  </div>
  <div class="admin-table-error" x-show="error" x-text="error" x-cloak></div>
  <div class="table-wrap" x-bind:class="loading ? 'admin-table-loading' : ''">
    <table class="table">
      <thead><tr><th><button class="admin-sort" type="button" x-on:click="sortBy('wellsharp_id')">WellSharp ID <span class="admin-sort-icon" x-text="sortIcon('wellsharp_id')"></span></button></th><th><button class="admin-sort" type="button" x-on:click="sortBy('name')">Name <span class="admin-sort-icon" x-text="sortIcon('name')"></span></button></th><th><button class="admin-sort" type="button" x-on:click="sortBy('age')">Age <span class="admin-sort-icon" x-text="sortIcon('age')"></span></button></th><th><button class="admin-sort" type="button" x-on:click="sortBy('gender')">Gender <span class="admin-sort-icon" x-text="sortIcon('gender')"></span></button></th><th><button class="admin-sort" type="button" x-on:click="sortBy('groups_count')">Groups <span class="admin-sort-icon" x-text="sortIcon('groups_count')"></span></button></th><th><button class="admin-sort" type="button" x-on:click="sortBy('status')">Status <span class="admin-sort-icon" x-text="sortIcon('status')"></span></button></th><th>Actions</th></tr></thead>
      <tbody><template x-for="student in rows" :key="student.id"><tr><td x-text="student.wellsharp_id"></td><td><strong x-text="student.display_name"></strong><br><small class="muted" x-text="student.email"></small></td><td x-text="student.age || '—'"></td><td x-text="student.gender || '—'"></td><td x-text="student.groups_count"></td><td><span class="badge" x-bind:class="student.status" x-text="student.status_label"></span></td><td><a x-bind:href="student.view_url">View</a></td></tr></template><tr x-show="!loading && rows.length === 0" x-cloak><td colspan="7" class="muted">No students found.</td></tr></tbody>
    </table>
  </div>
  <div class="admin-table-foot"><span x-text="meta.total ? 'Showing ' + meta.from + ' to ' + meta.to + ' of ' + meta.total + ' students' : 'No students found'"></span><div class="admin-page-actions"><button type="button" x-on:click="load(meta.current_page - 1)" x-bind:disabled="loading || meta.current_page <= 1">Previous</button><span x-text="meta.current_page + ' / ' + meta.last_page"></span><button type="button" x-on:click="load(meta.current_page + 1)" x-bind:disabled="loading || meta.current_page >= meta.last_page">Next</button></div></div>
</div>
@endsection

<script>
window.studentTable = function (endpoint, initialRows, initialMeta) {
  return {
    endpoint, rows: initialRows || [], meta: initialMeta || { current_page: 1, last_page: 1, total: 0, from: null, to: null }, search: @js($search), gender: @js($gender), groupId: @js($groupId), perPage: '15', sort: 'created_at', direction: 'desc', loading: false, error: '', request: null,
    hasFilters() { return this.search || this.gender || this.groupId; },
    clearFilters() { this.search = ''; this.gender = ''; this.groupId = ''; this.load(1); },
    sortIcon(field) { return this.sort === field ? (this.direction === 'asc' ? '▲' : '▼') : '↕'; },
    sortBy(field) { if (this.sort === field) this.direction = this.direction === 'asc' ? 'desc' : 'asc'; else { this.sort = field; this.direction = 'asc'; } this.load(1); },
    async load(page) { page = Math.max(1, page || 1); if (this.request) this.request.abort(); this.request = new AbortController(); const params = new URLSearchParams({ page, per_page: this.perPage, sort: this.sort, direction: this.direction }); if (this.search) params.set('search', this.search); if (this.gender) params.set('gender', this.gender); if (this.groupId) params.set('group_id', this.groupId); this.loading = true; this.error = ''; try { const response = await fetch(this.endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' }, signal: this.request.signal }); if (!response.ok) throw new Error('Student search failed'); const payload = await response.json(); this.rows = payload.data; this.meta = payload.meta; } catch (error) { if (error.name !== 'AbortError') this.error = 'Students could not be loaded. Try again.'; } finally { this.loading = false; } }
  };
};
</script>
