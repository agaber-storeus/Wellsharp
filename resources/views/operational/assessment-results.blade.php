@extends('layouts.operational')

@section('operational-content')
@php($operationalPrefix = auth()->user()->hasRole('proctor') ? 'proctor' : 'instructor')
<div x-data="wellsharpAssessmentResults(@js($attemptsJson), @js($initialFilters), @js(route($operationalPrefix.'.analytics.results.export')))">
  <section class="panel assessment-search-panel">
    <div class="panel-title">Search Options</div>
    <div class="panel-body">
      <form class="search-form assessment-search-form" x-on:submit.prevent="page = 1">
        <div class="form-line"><label><strong>Course(s):</strong></label><select x-model="filters.course_id" x-on:change="page = 1"><option value=""></option>@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach</select></div>
        <div class="form-line"><label><strong>Roles:</strong></label><select x-model="filters.role" x-on:change="page = 1"><option>Trainees</option><option>Instructors</option><option>Proctors</option></select></div>
        <div class="form-line"><label><strong>Date Range:</strong></label><div class="assessment-date-range"><select x-model="filters.date_range" x-on:change="page = 1"><option value="Custom Date Range">Custom Date Range</option><option value="All Time">All Time</option><option value="Previous 1 Month">Previous 1 Month</option><option value="Previous 6 Months">Previous 6 Months</option><option value="Previous 1 Year">Previous 1 Year</option></select><input type="date" x-model="filters.start_date" x-on:change="page = 1" /><input type="date" x-model="filters.end_date" x-on:change="page = 1" /></div></div>
        <div class="search-actions"><button class="blue-btn" type="submit">Search</button></div>
      </form>
    </div>
  </section>

  <h2 class="assessment-title">Assessment Comparison</h2>
  <div class="assessment-export"><a class="export-btn" x-bind:href="exportUrl">Export Excel</a></div>
  <div class="assessment-show">Show <select><option>4</option></select> entries</div>

  <div class="browse-table-scroll">
  <table class="assessment-table sortable-table">
    <thead>
      <tr>
        <template x-for="column in columns" :key="column.key">
          <th><button class="browse-sort-button" type="button" x-on:click="sortBy(column.key)"><span x-text="column.label"></span><span class="browse-sort-icon" x-text="sortIcon(column.key)"></span></button></th>
        </template>
      </tr>
    </thead>
    <tbody>
      <template x-for="result in pageRows" :key="result.exam_id">
        <tr>
          <td x-text="result.name"></td>
          <td x-text="result.trainees"></td>
          <td x-text="result.passed"></td>
          <td x-text="result.failed"></td>
          <td x-text="result.rate"></td>
          <td x-text="result.average"></td>
          <td x-text="result.retaking"></td>
          <td x-text="result.retake_passed"></td>
          <td x-text="result.retake_failed"></td>
          <td x-text="result.retake_rate"></td>
          <td x-text="result.retake_average"></td>
        </tr>
      </template>
      <tr x-show="!pageRows.length" x-cloak><td colspan="11">No scored assessment data is available for the selected filters.</td></tr>
    </tbody>
    <tfoot>
      <tr>
        <td></td>
        <td x-text="totals.trainees"></td>
        <td x-text="totals.passed"></td>
        <td x-text="totals.failed"></td>
        <td x-text="totals.rate"></td>
        <td x-text="totals.average"></td>
        <td x-text="totals.retaking"></td>
        <td x-text="totals.retake_passed"></td>
        <td x-text="totals.retake_failed"></td>
        <td x-text="totals.retake_rate"></td>
        <td x-text="totals.retake_average"></td>
      </tr>
    </tfoot>
  </table>
  </div>
  <div class="table-foot assessment-foot">
    <span x-text="resultSummary"></span>
    <span>
      <template x-if="page === 1"><span>Previous</span></template>
      <template x-if="page !== 1"><a href="#" x-on:click.prevent="page = Math.max(1, page - 1)">Previous</a></template>
      <b x-text="page"></b>
      <template x-if="page >= pageCount"><span>Next</span></template>
      <template x-if="page < pageCount"><a href="#" x-on:click.prevent="page = Math.min(pageCount, page + 1)">Next</a></template>
    </span>
  </div>

  <section class="assessment-chart-wrap">
    <h3>Course Results</h3>
    <div class="assessment-chart">
      <div class="y-axis">
        <template x-for="tick in chartAxis.axis" :key="'tick-' + tick">
          <span x-text="tick"></span>
        </template>
      </div>
      <div class="chart-area" x-bind:style="'grid-template-columns: repeat(' + Math.max(1, pageRows.length) + ', 1fr);'">
        <template x-for="result in pageRows" :key="'chart-' + result.exam_id">
          <div class="bar-group">
            <div class="bar pass" x-bind:style="'height: ' + barHeight(result.passed) + 'px'"></div>
            <div class="bar fail" x-show="result.failed > 0" x-cloak x-bind:style="'height: ' + barHeight(result.failed) + 'px'"></div>
            <div class="bar retake-pass" x-show="result.retake_passed > 0" x-cloak x-bind:style="'height: ' + barHeight(result.retake_passed) + 'px'"></div>
            <span x-text="result.name"></span>
          </div>
        </template>
      </div>
    </div>
    <div class="chart-legend">
      <span><i class="legend-fail"></i># Failed</span>
      <span><i class="legend-not-retake"></i>Trainees Not Retaking Exam</span>
      <span><i class="legend-failed-retake"></i>Failed Retake</span>
      <span><i class="legend-passed-retake"></i>Passed Retake</span>
      <span><i class="legend-pass"></i>Trainees Passed</span>
    </div>
  </section>
</div>

@push('scripts')
<script>
  window.wellsharpAssessmentResults = function (rows, initialFilters, exportBaseUrl) {
    var provided = initialFilters || {};

    return {
      rows: rows || [],
      exportBaseUrl: exportBaseUrl,
      filters: {
        course_id: provided.course_id || '',
        role: provided.role || 'Trainees',
        date_range: provided.date_range || 'Custom Date Range',
        start_date: provided.start_date || '',
        end_date: provided.end_date || ''
      },
      page: 1,
      perPage: 4,
      sortKey: 'name',
      sortDirection: 'asc',
      columns: [
        { key: 'name', label: 'Assessment' }, { key: 'trainees', label: 'Trainees Assessed' },
        { key: 'passed', label: '# Passed' }, { key: 'failed', label: '# Failed' },
        { key: 'rate', label: 'Passing Rate' }, { key: 'average', label: 'Average Score' },
        { key: 'retaking', label: 'Trainees Retaking Exam' }, { key: 'retake_passed', label: 'Retake # Passed' },
        { key: 'retake_failed', label: 'Retake # Failed' }, { key: 'retake_rate', label: 'Retake Passing Rate' },
        { key: 'retake_average', label: 'Retake Average Score' }
      ],
      dateBounds: function () {
        var range = this.filters.date_range || 'All Time';
        if (range === 'All Time') return [null, null];
        var month = range.match(/^Previous (\d+) Month/);
        if (month) {
          var from = new Date(); from.setMonth(from.getMonth() - Number(month[1])); from.setHours(0, 0, 0, 0);
          var to = new Date(); to.setHours(23, 59, 59, 999);
          return [from, to];
        }
        var year = range.match(/^Previous (\d+) Year/);
        if (year) {
          var from2 = new Date(); from2.setFullYear(from2.getFullYear() - Number(year[1])); from2.setHours(0, 0, 0, 0);
          var to2 = new Date(); to2.setHours(23, 59, 59, 999);
          return [from2, to2];
        }
        var start = this.filters.start_date ? new Date(this.filters.start_date + 'T00:00:00') : null;
        var end = this.filters.end_date ? new Date(this.filters.end_date + 'T23:59:59') : null;

        return [start, end];
      },
      get filteredAttempts() {
        if (this.filters.role === 'Instructors' || this.filters.role === 'Proctors') return [];
        var bounds = this.dateBounds();
        var from = bounds[0], to = bounds[1];
        var self = this;

        return this.rows.filter(function (attempt) {
          if (self.filters.course_id && String(attempt.course_id) !== String(self.filters.course_id)) return false;
          if (from || to) {
            if (!attempt.occurred_at) return false;
            var occurred = new Date(attempt.occurred_at.replace(' ', 'T'));
            if (from && occurred < from) return false;
            if (to && occurred > to) return false;
          }
          return true;
        });
      },
      uniqueCount: function (attempts, key) {
        var seen = {};
        attempts.forEach(function (attempt) { seen[attempt[key]] = true; });

        return Object.keys(seen).length;
      },
      rate: function (numerator, denominator) {
        return denominator > 0 ? ((numerator / denominator) * 100).toFixed(2) + '%' : '0%';
      },
      average: function (attempts) {
        var scores = attempts.map(function (attempt) { return attempt.score; }).filter(function (score) { return score !== null && score !== undefined; });
        if (!scores.length) return '0%';
        var avg = scores.reduce(function (a, b) { return a + b; }, 0) / scores.length;

        return avg.toFixed(2) + '%';
      },
      summarize: function (examAttempts, name, subject, examId) {
        var retakes = examAttempts.filter(function (attempt) { return attempt.attempt_number > 1; });
        var passed = examAttempts.filter(function (attempt) { return attempt.passed === true; }).length;
        var failed = examAttempts.filter(function (attempt) { return attempt.passed === false; }).length;
        var scored = examAttempts.filter(function (attempt) { return attempt.passed === true || attempt.passed === false; }).length;
        var retakePassed = retakes.filter(function (attempt) { return attempt.passed === true; }).length;
        var retakeFailed = retakes.filter(function (attempt) { return attempt.passed === false; }).length;
        var retakeScored = retakes.filter(function (attempt) { return attempt.passed === true || attempt.passed === false; }).length;

        return {
          exam_id: examId, name: name, subject: subject,
          trainees: this.uniqueCount(examAttempts, 'student_user_id'), passed: passed, failed: failed,
          rate: this.rate(passed, scored), average: this.average(examAttempts),
          retaking: this.uniqueCount(retakes, 'student_user_id'), retake_passed: retakePassed, retake_failed: retakeFailed,
          retake_rate: this.rate(retakePassed, retakeScored), retake_average: this.average(retakes)
        };
      },
      get rows_grouped() {
        var self = this;
        var groups = {};
        this.filteredAttempts.forEach(function (attempt) {
          var key = attempt.exam_id;
          if (!groups[key]) groups[key] = { examId: key, name: attempt.exam_name, subject: attempt.subject_name, attempts: [] };
          groups[key].attempts.push(attempt);
        });

        return Object.keys(groups).map(function (key) {
          var group = groups[key];
          return self.summarize(group.attempts, group.name, group.subject, group.examId);
        }).sort(function (a, b) { return String(a.name).localeCompare(String(b.name)); });
      },
      get sortedRows() {
        var rows = this.rows_grouped.slice();
        var key = this.sortKey, dir = this.sortDirection;
        var percentKeys = ['rate', 'average', 'retake_rate', 'retake_average'];
        var numericKeys = ['trainees', 'passed', 'failed', 'retaking', 'retake_passed', 'retake_failed'];
        rows.sort(function (a, b) {
          var av = a[key], bv = b[key];
          if (percentKeys.indexOf(key) !== -1) { av = parseFloat(av) || 0; bv = parseFloat(bv) || 0; }
          else if (numericKeys.indexOf(key) !== -1) { av = Number(av); bv = Number(bv); }
          else { av = String(av || '').toLowerCase(); bv = String(bv || '').toLowerCase(); }
          var cmp = av < bv ? -1 : (av > bv ? 1 : 0);

          return dir === 'asc' ? cmp : -cmp;
        });

        return rows;
      },
      get totals() {
        var rows = this.rows_grouped;
        var sum = function (key) { return rows.reduce(function (acc, row) { return acc + Number(row[key] || 0); }, 0); };
        var avgPercent = function (key) {
          if (!rows.length) return '0%';
          var values = rows.map(function (row) { return parseFloat(row[key]) || 0; });
          var avg = values.reduce(function (a, b) { return a + b; }, 0) / values.length;

          return avg.toFixed(2) + '%';
        };

        return {
          trainees: sum('trainees'), passed: sum('passed'), failed: sum('failed'),
          rate: avgPercent('rate'), average: avgPercent('average'),
          retaking: sum('retaking'), retake_passed: sum('retake_passed'), retake_failed: sum('retake_failed'),
          retake_rate: avgPercent('retake_rate'), retake_average: avgPercent('retake_average')
        };
      },
      get pageRows() {
        var start = (this.page - 1) * this.perPage;

        return this.sortedRows.slice(start, start + this.perPage);
      },
      get pageCount() {
        return Math.max(1, Math.ceil(this.sortedRows.length / this.perPage));
      },
      get resultSummary() {
        var total = this.sortedRows.length;
        if (!total) return 'Showing 0 to 0 of 0 entries';
        var from = (this.page - 1) * this.perPage + 1;
        var to = Math.min(this.page * this.perPage, total);

        return 'Showing ' + from + ' to ' + to + ' of ' + total + ' entries';
      },
      sortBy: function (key) {
        if (this.sortKey === key) this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        else { this.sortKey = key; this.sortDirection = 'asc'; }
        this.page = 1;
      },
      sortIcon: function (key) {
        return this.sortKey === key ? (this.sortDirection === 'asc' ? '▲' : '▼') : '↕';
      },
      get chartAxis() {
        var max = 0;
        this.pageRows.forEach(function (row) { max = Math.max(max, row.passed, row.failed, row.retake_passed); });
        max = Math.max(1, max);
        var magnitude = Math.pow(10, String(max).length - 1);
        var axisMax = Math.ceil(max / magnitude) * magnitude;
        var step = axisMax / 9;
        var axis = [];
        for (var i = 0; i <= 9; i++) axis.push(Math.round(axisMax - (i * step)));

        return { axis: axis, axisMax: axisMax };
      },
      barHeight: function (value) {
        if (value <= 0) return 0;

        return Math.max(1, Math.round((value / this.chartAxis.axisMax) * 400));
      },
      get exportUrl() {
        var params = [];
        var add = function (key, value) { if (value) params.push(key + '=' + encodeURIComponent(value)); };
        add('course_id', this.filters.course_id);
        add('role', this.filters.role);
        add('date_range', this.filters.date_range);
        add('start_date', this.filters.start_date);
        add('end_date', this.filters.end_date);

        return this.exportBaseUrl + (params.length ? '?' + params.join('&') : '');
      }
    };
  };
</script>
@endpush
@endsection
