<div class="browse-classes" x-data="wellsharpBrowseClasses(@js($browseRows), @js($filters))">
  <section class="panel browse-search-panel">
    <div class="panel-title">Search Options</div>
    <div class="panel-body">
      <form class="browse-search-form" x-on:submit.prevent="page = 1">
        <div class="browse-filter-grid">
          <label class="browse-filter-field" for="browse-search">
            <span>Class title or ID</span>
            <input id="browse-search" type="search" x-model.debounce.200ms="filters.search" x-on:input="page = 1" placeholder="Search title, ID, subject, provider..." autocomplete="off">
          </label>
          <label class="browse-filter-field" for="browse-course">
            <span>Subject</span>
            <select id="browse-course" x-model="filters.course_id" x-on:change="page = 1">
              <option value="">All subjects</option>
              @foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach
            </select>
          </label>
          <label class="browse-filter-field" for="browse-instructor">
            <span>Instructor</span>
            <select id="browse-instructor" x-model="filters.instructor_id" x-on:change="page = 1">
              <option value="">All instructors</option>
              @foreach($instructors as $instructor)<option value="{{ $instructor->id }}">{{ $instructor->display_name }}</option>@endforeach
            </select>
          </label>
          <label class="browse-filter-field" for="browse-start-date">
            <span>Class starts on/after</span>
            <input id="browse-start-date" type="date" x-model="filters.start_date" x-on:change="page = 1">
          </label>
          <label class="browse-filter-field" for="browse-end-date">
            <span>Class ends on/before</span>
            <input id="browse-end-date" type="date" x-model="filters.end_date" x-on:change="page = 1">
          </label>
          <label class="browse-filter-field" for="browse-exam-date">
            <span>Exam available on</span>
            <input id="browse-exam-date" type="date" x-model="filters.exam_date" x-on:change="page = 1">
          </label>
          <label class="browse-filter-field" for="browse-city">
            <span>Location contains</span>
            <input id="browse-city" type="search" x-model.debounce.200ms="filters.city" x-on:input="page = 1" placeholder="City or address">
          </label>
          <label class="browse-filter-field" for="browse-country">
            <span>Country contains</span>
            <input id="browse-country" type="search" x-model.debounce.200ms="filters.country" x-on:input="page = 1" placeholder="Country">
          </label>
          <label class="browse-filter-field" for="browse-state">
            <span>State contains</span>
            <input id="browse-state" type="search" x-model.debounce.200ms="filters.state" x-on:input="page = 1" placeholder="State or region">
          </label>
          <label class="browse-filter-field" for="browse-retakes">
            <span>Retakes</span>
            <select id="browse-retakes" x-model="filters.retakes" x-on:change="page = 1">
              <option value="">All classes</option>
              <option value="yes">With retakes</option>
              <option value="no">Without retakes</option>
            </select>
          </label>
        </div>
        <div class="browse-search-actions">
          <span class="browse-filter-summary" x-text="activeFilterCount + ' active filter' + (activeFilterCount === 1 ? '' : 's')"></span>
          <div class="browse-action-buttons">
            <button class="blue-btn" type="submit">Search classes</button>
            <button class="browse-clear-button" type="button" x-on:click="clearFilters">Clear all</button>
          </div>
        </div>
      </form>
    </div>
  </section>

  <section class="panel browse-results-panel">
    <div class="panel-title">Class Results</div>
    <div class="browse-results-toolbar">
      <div class="browse-results-summary">
        <strong x-text="filteredRows.length + ' matching ' + (filteredRows.length === 1 ? 'class' : 'classes')"></strong>
        <span class="muted">Use the arrows in the headings to sort. Scroll horizontally to view every standard column.</span>
      </div>
      <div class="browse-results-controls">
        <label class="browse-sort-control">Sort by
          <select x-model="sortKey" x-on:change="page = 1">
            <option value="starts_at">Class date</option>
            <option value="class_number">Title</option>
            <option value="status">Status</option>
            <option value="subject">Subject</option>
            <option value="provider">Provider</option>
            <option value="users">Users</option>
            <option value="retakes">Retakes</option>
          </select>
        </label>
        <button class="browse-sort-direction" type="button" x-on:click="toggleSortDirection" x-text="sortDirection === 'asc' ? 'Ascending' : 'Descending'"></button>
        <label class="browse-per-page">Show
          <select x-model.number="perPage" x-on:change="page = 1">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          classes
        </label>
      </div>
    </div>
    <div class="browse-result-grid" x-ignore aria-hidden="true">
      <template x-for="trainingClass in pageRows" :key="trainingClass.id">
        <article class="browse-result-card" :class="'status-' + trainingClass.status">
          <div class="browse-result-card-topline"></div>
          <header class="browse-result-card-header">
            <div class="browse-result-card-heading">
              <span class="browse-result-label">Class ID</span>
              <a href="#" data-class-modal="details" :data-class-state="trainingClass.state" :data-class-id="trainingClass.id" x-text="trainingClass.id"></a>
              <h3 x-text="trainingClass.class_number"></h3>
            </div>
            <span class="browse-status" :class="trainingClass.status" x-text="trainingClass.status_label"></span>
          </header>
          <div class="browse-result-date-grid">
            <div><span class="browse-result-label">Class dates</span><strong x-text="trainingClass.course_dates"></strong></div>
            <div><span class="browse-result-label">Exam available</span><strong x-text="trainingClass.exam_date"></strong></div>
          </div>
          <div class="browse-result-info-grid">
            <div><span class="browse-result-label">Subject</span><strong x-text="trainingClass.subject"></strong></div>
            <div><span class="browse-result-label">Provider</span><strong x-text="trainingClass.provider"></strong></div>
            <div class="browse-result-wide"><span class="browse-result-label">Location</span><strong x-text="trainingClass.location"></strong></div>
            <div><span class="browse-result-label">Instructor</span><strong x-text="trainingClass.instructor"></strong></div>
            <div><span class="browse-result-label">Proctor</span><strong x-text="trainingClass.proctor"></strong></div>
          </div>
          <div class="browse-result-metrics">
            <div><strong x-text="trainingClass.users"></strong><span>Users</span></div>
            <div><strong x-text="trainingClass.retakes"></strong><span>Retakes</span></div>
            <div><strong x-text="trainingClass.duration_label"></strong><span>Duration</span></div>
          </div>
          <div class="browse-result-extra" x-show="isExpanded(trainingClass.id)" x-cloak>
            <div><span class="browse-result-label">Deployment</span><strong x-text="trainingClass.deployment"></strong></div>
            <div><span class="browse-result-label">Database record</span><strong>Available to eligible staff</strong></div>
          </div>
          <footer class="browse-result-card-footer">
            <button class="browse-card-details" type="button" x-on:click="toggleExpanded(trainingClass.id)"><span x-text="isExpanded(trainingClass.id) ? 'Hide details' : 'More details'"></span><span x-text="isExpanded(trainingClass.id) ? '▲' : '▼'"></span></button>
            <a class="browse-card-open" href="#" data-class-modal="details" :data-class-state="trainingClass.state" :data-class-id="trainingClass.id">Open class details</a>
          </footer>
        </article>
      </template>
      <div class="browse-result-empty" x-show="!pageRows.length" x-cloak>
        <strong x-text="rows.length ? 'No classes match the current filters.' : 'No classes are available.'"></strong>
        <span>Try clearing a filter or changing the search text.</span>
      </div>
    </div>
    <div class="browse-standard-table-wrap">
      <div class="browse-scroll-hint"><span>Class results</span><span>Scroll horizontally to view all columns</span></div>
      <div class="browse-table-scroll">
        <table class="results-table browse-results-table">
          <thead><tr>
            @foreach(['id' => 'Class ID', 'class_number' => 'Title', 'status' => 'Class Status', 'provider' => 'Provider', 'instructor' => 'Instructor', 'location' => 'Location', 'subject' => 'Course', 'exam_date' => 'Exam Date', 'starts_at' => 'Course Dates', 'users' => 'Users', 'retakes' => 'Retakes', 'duration_days' => 'Duration', 'deployment' => 'Deployment', 'proctor' => 'Proctor'] as $key => $label)
              <th scope="col"><button class="browse-sort-button" type="button" x-on:click="sortBy('{{ $key }}')"><span>{{ $label }}</span><span class="browse-sort-icon" x-text="sortIcon('{{ $key }}')"></span></button></th>
            @endforeach
          </tr></thead>
          <tbody>
            <template x-for="trainingClass in pageRows" :key="'table-' + trainingClass.id"><tr>
              <td><a href="#" data-class-modal="details" :data-class-state="trainingClass.state" :data-class-id="trainingClass.id" x-text="trainingClass.id"></a></td>
              <td class="browse-title-cell" x-text="trainingClass.class_number"></td>
              <td class="browse-status-cell"><span class="browse-status-dot" :class="trainingClass.status"></span><span x-text="trainingClass.status_label"></span></td>
              <td x-text="trainingClass.provider"></td><td x-text="trainingClass.instructor"></td><td x-text="trainingClass.location"></td><td x-text="trainingClass.subject"></td><td x-text="trainingClass.exam_date"></td><td x-text="trainingClass.course_dates"></td><td class="browse-number-cell" x-text="trainingClass.users"></td><td class="browse-number-cell" x-text="trainingClass.retakes"></td><td x-text="trainingClass.duration_label"></td><td x-text="trainingClass.deployment"></td><td x-text="trainingClass.proctor"></td>
            </tr></template>
            <tr x-show="!pageRows.length" x-cloak><td class="browse-empty-cell" colspan="14"><strong x-text="rows.length ? 'No classes match the current filters.' : 'No classes are available.'"></strong><span>Try clearing a filter or changing the search text.</span></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="browse-results-footer">
      <span x-text="resultSummary"></span>
      <div class="browse-pagination" x-show="pageCount > 1" x-cloak>
        <button type="button" x-on:click="page = Math.max(1, page - 1)" x-bind:disabled="page === 1">Previous</button>
        <span x-text="'Page ' + page + ' of ' + pageCount"></span>
        <button type="button" x-on:click="page = Math.min(pageCount, page + 1)" x-bind:disabled="page === pageCount">Next</button>
      </div>
    </div>
  </section>
</div>

@push('scripts')
<script>
  window.wellsharpBrowseClasses = function (rows, initialFilters) {
    var provided = initialFilters || {};
    var blankFilters = function () {
      return { search: '', start_date: '', end_date: '', exam_date: '', city: '', country: '', state: '', course_id: '', instructor_id: '', retakes: '' };
    };

    return {
      rows: rows || [],
      filters: Object.assign(blankFilters(), provided),
      page: 1,
      perPage: 10,
      sortKey: 'starts_at',
      sortDirection: 'asc',
      expandedRows: {},
      get filteredRows() {
        var filters = this.filters;
        var normalize = function (value) { return String(value || '').trim().toLowerCase(); };
        var search = normalize(filters.search);
        var city = normalize(filters.city);
        var country = normalize(filters.country);
        var state = normalize(filters.state);

        return this.rows.filter(function (row) {
          if (search && !normalize(row.search).includes(search)) return false;
          if (filters.course_id && String(row.course_id) !== String(filters.course_id)) return false;
          if (filters.instructor_id && !(row.instructor_ids || []).map(String).includes(String(filters.instructor_id))) return false;
          if (filters.retakes === 'yes' && Number(row.retakes) === 0) return false;
          if (filters.retakes === 'no' && Number(row.retakes) > 0) return false;
          if (filters.start_date && (!row.starts_at || row.starts_at < filters.start_date)) return false;
          if (filters.end_date && (!row.ends_at || row.ends_at > filters.end_date)) return false;
          if (city && !normalize(row.location).includes(city)) return false;
          if (country && !normalize(row.location).includes(country)) return false;
          if (state && !normalize(row.location).includes(state)) return false;
          if (filters.exam_date && !(row.exam_ranges || []).some(function (range) {
            return range.start && range.start <= filters.exam_date && (range.end || range.start) >= filters.exam_date;
          })) return false;

          return true;
        }).sort(this.compareRows.bind(this));
      },
      get pageRows() {
        var start = (this.page - 1) * this.perPage;
        return this.filteredRows.slice(start, start + this.perPage);
      },
      get pageCount() {
        return Math.max(1, Math.ceil(this.filteredRows.length / this.perPage));
      },
      get activeFilterCount() {
        return Object.values(this.filters).filter(function (value) { return String(value || '').trim() !== ''; }).length;
      },
      get resultSummary() {
        if (!this.filteredRows.length) return 'No matching classes';
        var from = (this.page - 1) * this.perPage + 1;
        var to = Math.min(this.page * this.perPage, this.filteredRows.length);
        return 'Showing ' + from + ' to ' + to + ' of ' + this.filteredRows.length + ' classes';
      },
      compareRows: function (left, right) {
        var leftValue = left[this.sortKey];
        var rightValue = right[this.sortKey];
        if (leftValue === null || leftValue === undefined) leftValue = '';
        if (rightValue === null || rightValue === undefined) rightValue = '';
        var comparison = String(leftValue).localeCompare(String(rightValue), undefined, { numeric: true, sensitivity: 'base' });

        return this.sortDirection === 'asc' ? comparison : -comparison;
      },
      sortBy: function (key) {
        if (this.sortKey === key) this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        else {
          this.sortKey = key;
          this.sortDirection = 'asc';
        }
        this.page = 1;
      },
      sortIcon: function (key) {
        return this.sortKey === key ? (this.sortDirection === 'asc' ? '▲' : '▼') : '↕';
      },
      toggleSortDirection: function () {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        this.page = 1;
      },
      isExpanded: function (id) {
        return Boolean(this.expandedRows[id]);
      },
      toggleExpanded: function (id) {
        this.expandedRows[id] = !this.expandedRows[id];
      },
      clearFilters: function () {
        this.filters = blankFilters();
        this.page = 1;
      }
    };
  };
</script>
<script>window.wellsharpClassModalData = @json($classModalData);</script>
@endpush
