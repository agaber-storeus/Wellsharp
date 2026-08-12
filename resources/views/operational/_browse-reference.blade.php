<div class="browse-reference" x-data="wellsharpBrowseClasses(@js($browseRows), @js($filters))">
  <section class="panel">
    <div class="panel-title">Search Options</div>
    <div class="panel-body">
      <form class="search-form browse-reference-search" x-on:submit.prevent="page = 1">
        <div>
          <div class="form-line"><label for="browse-search">Class Title or ID:</label><input id="browse-search" x-model.debounce.200ms="filters.search" x-on:input="page = 1" autocomplete="off" /></div>
          <div class="form-line"><label>Date Filter:</label><div class="date-inputs"><input id="browse-start-date" type="date" x-model="filters.start_date" x-on:change="page = 1" /><span>to</span><input id="browse-end-date" type="date" x-model="filters.end_date" x-on:change="page = 1" /></div></div>
          <div class="form-line"><label for="browse-exam-date">Exam Date:</label><input id="browse-exam-date" type="date" placeholder="Test Date" x-model="filters.exam_date" x-on:change="page = 1" /></div>
          <div class="form-line"><label for="browse-city">City:</label><input id="browse-city" x-model.debounce.200ms="filters.city" x-on:input="page = 1" /></div>
          <div class="form-line"><label for="browse-country">Country:</label><input id="browse-country" x-model.debounce.200ms="filters.country" x-on:input="page = 1" /></div>
          <div class="form-line"><label for="browse-state">State:</label><input id="browse-state" x-model.debounce.200ms="filters.state" x-on:input="page = 1" /></div>
        </div>
        <div>
          <div class="form-line"><label for="browse-course">Course:</label><select id="browse-course" x-model="filters.course_id" x-on:change="page = 1"><option value="">All Courses</option>@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach</select></div>
          <div class="form-line"><label for="browse-instructor">Instructor:</label><select id="browse-instructor" x-model="filters.instructor_id" x-on:change="page = 1"><option value="">All Instructors</option>@foreach($instructors as $instructor)<option value="{{ $instructor->id }}">{{ $instructor->display_name }}</option>@endforeach</select></div>
          <div class="form-line"><label for="browse-retakes">Retakes:</label><select id="browse-retakes" x-model="filters.retakes" x-on:change="page = 1"><option value="">(All)</option><option value="yes">With retakes</option><option value="no">Without retakes</option></select></div>
          <div class="form-line"><label for="browse-deployment">Deployment:</label><select id="browse-deployment" x-model="filters.deployment" x-on:change="page = 1"><option value="">Select an Option</option><option value="not_configured">Not configured</option></select></div>
        </div>
        <div class="search-actions"><button class="blue-btn" type="submit">Search Classes</button></div>
      </form>
    </div>
  </section>

  <div class="browse-reference-actions">
    <a class="export-btn" href="#" x-on:click.prevent="exportCsv">Export Excel</a>
  </div>

  <div class="browse-table-scroll">
    <table class="results-table browse-results-table" x-ref="browseTable">
      <colgroup><template x-for="column in visibleColumns" :key="'col-' + column.key"><col x-bind:data-column-key="column.key" x-bind:style="columnStyle(column)"></template></colgroup>
      <thead><tr>
        <template x-for="column in visibleColumns" :key="column.key"><th scope="col" x-bind:data-column-key="column.key" x-bind:style="columnStyle(column)"><button class="browse-sort-button browse-reference-sort" type="button" x-on:click="sortBy(column.key)"><span x-text="column.label"></span><span class="browse-sort-icon" x-text="sortIcon(column.key)"></span></button><span class="browse-column-resizer" x-bind:data-resize-column-key="column.key" role="separator" aria-label="Resize column" title="Drag to resize"></span></th></template>
      </tr></thead>
      <tbody>
        <template x-for="trainingClass in pageRows" :key="trainingClass.id"><tr>
          <template x-for="column in visibleColumns" :key="column.key"><td x-bind:data-column-key="column.key" x-bind:style="columnStyle(column)">
            <template x-if="column.key === 'id'"><a href="#" data-class-modal="details" :data-class-state="trainingClass.state" :data-class-id="trainingClass.id" x-text="trainingClass.id"></a></template>
            <template x-if="column.key === 'status'"><span class="browse-status-value"><span class="status-dot" :class="statusDotClass(trainingClass)"></span><span x-text="displayStatus(trainingClass)"></span></span></template>
            <template x-if="column.key === 'retakes'"><span :class="Number(trainingClass.retakes) > 0 ? 'retake-badge' : ''" x-text="trainingClass.retakes"></span></template>
            <template x-if="!['id', 'status', 'retakes'].includes(column.key)"><span x-text="cellValue(trainingClass, column.key)"></span></template>
          </td></template>
        </tr></template>
        <tr x-show="!pageRows.length" x-cloak><td x-bind:colspan="Math.max(1, visibleColumns.length)">No classes found.</td></tr>
      </tbody>
    </table>
  </div>
  <p class="results-caption" x-text="resultSummary"></p>
  <div class="pagination browse-reference-pagination" x-show="pageCount > 1" x-cloak><button type="button" x-on:click="page = Math.max(1, page - 1)" x-bind:disabled="page === 1">Previous</button><span class="page-box" x-text="page"></span><button type="button" x-on:click="page = Math.min(pageCount, page + 1)" x-bind:disabled="page === pageCount">Next</button></div>
</div>

@push('scripts')
<script>
  window.wellsharpBrowseClasses = function (rows, initialFilters) {
    var provided = initialFilters || {};
    var blankFilters = function () { return { search: '', start_date: '', end_date: '', exam_date: '', city: '', country: '', state: '', course_id: '', instructor_id: '', retakes: '', deployment: '' }; };
    var defaultColumns = function () { return [
      { key: 'id', label: 'Class ID', width: 78, visible: true }, { key: 'class_number', label: 'Title', width: 170, visible: true }, { key: 'status', label: 'Class Status', width: 145, visible: true },
      { key: 'provider', label: 'Provider', width: 112, visible: true }, { key: 'instructor', label: 'Instructor', width: 86, visible: true }, { key: 'location', label: 'Location', width: 126, visible: true },
      { key: 'subject', label: 'Course', width: 170, visible: true }, { key: 'exam_date', label: 'Exam Date', width: 116, visible: true }, { key: 'starts_at', label: 'Course Dates', width: 116, visible: true },
      { key: 'users', label: 'Users', width: 56, visible: true }, { key: 'retakes', label: 'Retakes', width: 56, visible: true }, { key: 'duration_days', label: 'Duration', width: 96, visible: true },
      { key: 'deployment', label: 'Deployment', width: 92, visible: true }, { key: 'proctor', label: 'Proctor', width: 228, visible: true }
    ]; };
    var clampColumnWidth = function (value, fallback) { var width = Number(value); if (!Number.isFinite(width)) width = fallback; return Math.max(60, Math.min(460, Math.round(width))); };

    return {
      rows: rows || [], filters: Object.assign(blankFilters(), provided), page: 1, perPage: 50, sortKey: 'starts_at', sortDirection: 'asc',
      columns: defaultColumns(), resizingColumn: null, resizeStartX: 0, resizeStartWidth: 0,
      init: function () {
        try {
          var saved = JSON.parse(localStorage.getItem('wellsharp-browse-columns-v4') || 'null');
          if (Array.isArray(saved)) {
            var defaults = defaultColumns();
            var preferences = {};
            saved.forEach(function (savedColumn) {
              var key = typeof savedColumn === 'string' ? savedColumn : savedColumn.key;
              if (key) preferences[key] = typeof savedColumn === 'object' ? savedColumn : {};
            });
            this.columns = defaults.map(function (column) {
              var preference = preferences[column.key];
              return preference ? Object.assign(column, { visible: true, width: clampColumnWidth(preference.width, column.width) }) : column;
            });
          }
        } catch (error) { /* Preferences are optional and the table remains usable. */ }
      },
      get filteredRows() {
        var filters = this.filters;
        var normalize = function (value) { return String(value || '').trim().toLowerCase(); };
        var search = normalize(filters.search), city = normalize(filters.city), country = normalize(filters.country), state = normalize(filters.state);

        return this.rows.filter(function (row) {
          if (search && !normalize(row.search).includes(search)) return false;
          if (filters.course_id && String(row.course_id) !== String(filters.course_id)) return false;
          if (filters.instructor_id && !(row.instructor_ids || []).map(String).includes(String(filters.instructor_id))) return false;
          if (filters.retakes === 'yes' && Number(row.retakes) === 0) return false;
          if (filters.retakes === 'no' && Number(row.retakes) > 0) return false;
          if (filters.deployment && row.deployment !== 'Not configured') return false;
          if (filters.start_date && (!row.starts_at || row.starts_at < filters.start_date)) return false;
          if (filters.end_date && (!row.ends_at || row.ends_at > filters.end_date)) return false;
          if (city && !normalize(row.location).includes(city)) return false;
          if (country && !normalize(row.location).includes(country)) return false;
          if (state && !normalize(row.location).includes(state)) return false;
          if (filters.exam_date && !(row.exam_ranges || []).some(function (range) { return range.start && range.start <= filters.exam_date && (range.end || range.start) >= filters.exam_date; })) return false;
          return true;
        }).sort(this.compareRows.bind(this));
      },
      get visibleColumns() { return this.columns; },
      get pageRows() { var start = (this.page - 1) * this.perPage; return this.filteredRows.slice(start, start + this.perPage); },
      get pageCount() { return Math.max(1, Math.ceil(this.filteredRows.length / this.perPage)); },
      get resultSummary() { if (!this.filteredRows.length) return 'Showing 0 to 0 of 0 entries'; var from = (this.page - 1) * this.perPage + 1; var to = Math.min(this.page * this.perPage, this.filteredRows.length); return 'Showing ' + from + ' to ' + to + ' of ' + this.filteredRows.length + ' entries'; },
      compareRows: function (left, right) { var leftValue = left[this.sortKey] ?? '', rightValue = right[this.sortKey] ?? ''; var comparison = String(leftValue).localeCompare(String(rightValue), undefined, { numeric: true, sensitivity: 'base' }); return this.sortDirection === 'asc' ? comparison : -comparison; },
      sortBy: function (key) { if (this.sortKey === key) this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'; else { this.sortKey = key; this.sortDirection = 'asc'; } this.page = 1; },
      sortIcon: function (key) { return this.sortKey === key ? (this.sortDirection === 'asc' ? '▲' : '▼') : '↕'; },
      statusDotClass: function (trainingClass) { return trainingClass.status === 'active' ? 'status-active' : (trainingClass.status === 'planned' ? 'status-scheduled' : 'status-ended'); },
      displayStatus: function (trainingClass) { return trainingClass.status === 'active' ? 'Exam Active' : (trainingClass.status === 'planned' ? 'Class Scheduled' : 'Class Ended'); },
      cellValue: function (trainingClass, key) { if (key === 'duration_days') return trainingClass.duration_label; if (key === 'starts_at') return trainingClass.course_dates; return trainingClass[key] ?? ''; },
      columnStyle: function (column) { return 'width:' + column.width + 'px;min-width:' + column.width + 'px;'; },
      startColumnResize: function (event, key) {
        var column = this.columns.find(function (item) { return item.key === key; });
        if (!column) return;
        this.resizingColumn = key;
        this.resizeStartX = event.clientX;
        this.resizeStartWidth = column.width;
        event.preventDefault();
        document.body.classList.add('browse-resizing');
      },
      stopColumnResize: function () { if (!this.resizingColumn) return; this.persistColumns(); this.resizingColumn = null; document.body.classList.remove('browse-resizing'); },
      persistColumns: function () { try { localStorage.setItem('wellsharp-browse-columns-v4', JSON.stringify(this.columns.map(function (column) { return { key: column.key, width: column.width }; }))); } catch (error) { /* Preferences are optional. */ } },
      exportCsv: function () {
        var headers = this.visibleColumns.map(function (column) { return column.label; });
        var escape = function (value) { return '"' + String(value ?? '').replace(/"/g, '""') + '"'; };
        var csv = [headers].concat(this.filteredRows.map(function (row) { return this.visibleColumns.map(function (column) { return this.cellValue(row, column.key); }, this); }, this)).map(function (line) { return line.map(escape).join(','); }).join('\r\n');
        var link = document.createElement('a'); link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' })); link.download = 'wellsharp-browse-results.csv'; link.click(); URL.revokeObjectURL(link.href);
      }
    };
  };
</script>
<script>
  (function () {
    if (window.__wellsharpBrowseColumnResizeBound) return;
    window.__wellsharpBrowseColumnResizeBound = true;
    var resizeState = null;
    var clampWidth = function (value) { return Math.max(60, Math.min(460, Math.round(value))); };
    var alpineData = function (root) { return window.Alpine && typeof window.Alpine.$data === 'function' ? window.Alpine.$data(root) : null; };
    var applyResize = function (clientX) {
      if (!resizeState) return;
      var width = clampWidth(resizeState.startWidth + clientX - resizeState.startX);
      var styleWidth = width + 'px';
      resizeState.root.querySelectorAll('[data-column-key="' + resizeState.key + '"]').forEach(function (element) { element.style.width = styleWidth; element.style.minWidth = styleWidth; });
      resizeState.table.style.width = Math.max(1420, resizeState.otherColumnsWidth + width) + 'px';
      resizeState.table.style.minWidth = resizeState.table.style.width;
      if (resizeState.data) {
        var column = resizeState.data.columns.find(function (item) { return item.key === resizeState.key; });
        if (column) column.width = width;
      }
    };
    var resizeColumn = function (event) {
      if (!resizeState) return;
      resizeState.pendingX = event.clientX;
      if (resizeState.frame) return;
      resizeState.frame = window.requestAnimationFrame(function () { resizeState.frame = 0; applyResize(resizeState.pendingX); });
    };
    var finishResize = function () {
      if (!resizeState) return;
      if (resizeState.frame) window.cancelAnimationFrame(resizeState.frame);
      if (resizeState.pendingX !== null) applyResize(resizeState.pendingX);
      if (resizeState.data && typeof resizeState.data.persistColumns === 'function') resizeState.data.persistColumns();
      resizeState = null;
      document.body.classList.remove('browse-resizing');
    };
    document.addEventListener('mousedown', function (event) {
      var handle = event.target && event.target.closest ? event.target.closest('.browse-column-resizer') : null;
      if (!handle) return;
      var root = handle.closest('.browse-reference');
      var header = handle.closest('th');
      var table = root && root.querySelector('.browse-results-table');
      var key = handle.getAttribute('data-resize-column-key');
      if (!root || !header || !table || !key) return;
      event.preventDefault();
      event.stopPropagation();
      var startWidth = header.getBoundingClientRect().width;
      resizeState = { root: root, table: table, data: alpineData(root), key: key, startX: event.clientX, startWidth: startWidth, otherColumnsWidth: Math.max(0, table.getBoundingClientRect().width - startWidth), pendingX: null, frame: 0 };
      document.body.classList.add('browse-resizing');
    }, true);
    document.addEventListener('mousemove', resizeColumn, true);
    document.addEventListener('mouseup', finishResize, true);
  }());
</script>
<script>window.wellsharpClassModalData = @json($classModalData);</script>
@endpush
