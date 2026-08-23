<section class="panel data-panel {{ !empty($compact) ? 'compact-panel' : '' }}" x-data="{ search: '', page: 1, pageSize: 10, matches(value) { const needle = this.search.trim().toLowerCase(); return !needle || value.includes(needle); }, filteredRows() { return Array.from(this.$root.querySelectorAll('tbody tr[data-class-row]')).filter(row => this.matches(row.dataset.search)); }, visible(row) { const index = this.filteredRows().indexOf(row); return index >= (this.page - 1) * this.pageSize && index < this.page * this.pageSize; }, pageCount() { return Math.max(1, Math.ceil(this.filteredRows().length / this.pageSize)); }, resetPage() { this.page = 1; } }">
  <div class="panel-title">{{ $title }}</div>
  <div class="data-panel-body">
    <div class="table-tools"><label>Show <select x-model.number="pageSize" x-on:change="resetPage()"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select> entries</label><label>Search: <input type="search" x-model="search" x-on:input="resetPage()" aria-label="Search {{ strtolower($title) }}" autocomplete="off" /></label></div>
    <table class="home-data-table {{ !empty($striped) ? 'striped' : '' }}">
      <thead><tr><th>Class Title</th><th>Class Location</th><th>Start Date</th><th>End Date</th><th>{{ $roleLabel === 'Instructor' ? 'Instructor' : 'Instructor' }}</th><th>{{ !empty($past) ? 'Concluded' : 'Language' }}</th></tr></thead>
      <tbody>
        @forelse($classes as $trainingClass)
          @php
            $instructor = 'Any eligible Instructor';
            $languages = $trainingClass->course->languages->pluck('name')->join(', ') ?: 'Not assigned';
            $searchText = strtolower(implode(' ', [$trainingClass->displayTitle(), $trainingClass->course->code, $trainingClass->course->name, $trainingClass->provider?->name, $trainingClass->starts_at?->format('Y-m-d'), $trainingClass->ends_at?->format('Y-m-d'), $instructor, $languages]));
            $modalState = match ($trainingClass->status->value) {
                'active' => 'open',
                'planned' => 'notstarted',
                default => 'ended',
            };
          @endphp
          <tr data-class-row data-search="{{ $searchText }}" x-show="visible($el)">
            <td><a href="#" data-class-modal="details" data-class-state="{{ $modalState }}" data-class-id="{{ $trainingClass->public_id }}">{{ $trainingClass->displayTitle() }}</a></td>
            <td>{{ $trainingClass->provider?->name ?: 'Not assigned' }}</td>
            <td>{{ $trainingClass->starts_at?->format('Y-m-d') ?: 'Not scheduled' }}</td>
            <td>{{ $trainingClass->ends_at?->format('Y-m-d') ?: 'Not scheduled' }}</td>
            <td>{{ $instructor }}</td>
            <td>{{ !empty($past) ? ($trainingClass->ends_at?->diffForHumans() ?: 'Not concluded') : $languages }}</td>
          </tr>
        @empty
          <tr><td colspan="6"><div class="empty-class-message"><strong>{{ $emptyMessage }}</strong>@if(!empty($emptyHint))<span>{{ $emptyHint }}</span>@endif</div></td></tr>
        @endforelse
        <tr x-show="search.trim() && filteredRows().length === 0" x-cloak><td colspan="6"><div class="empty-class-message"><strong>No matching Classes found.</strong><span>Clear the search field to see all available Classes.</span></div></td></tr>
      </tbody>
    </table>
    <div class="table-foot"><span x-text="'Showing ' + (filteredRows().length ? ((page - 1) * pageSize + 1) : 0) + ' to ' + Math.min(page * pageSize, filteredRows().length) + ' of ' + filteredRows().length + ' entries'"></span><span><button type="button" x-on:click="page = Math.max(1, page - 1)" x-bind:disabled="page <= 1">Previous</button> <b x-text="page"></b> <button type="button" x-on:click="page = Math.min(pageCount(), page + 1)" x-bind:disabled="page >= pageCount()">Next</button></span></div>
  </div>
</section>
