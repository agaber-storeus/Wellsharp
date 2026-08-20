@extends('layouts.operational')

@section('operational-content')
<div x-data="wellsharpClassesAnalytics(@js($classesJson), @js($initialFilters))">
  <div class="analytics-top">
    <strong>Date Range:</strong>
    <select x-model="filters.date_range">
      <option value="All Time">All Time</option>
      <option value="Custom Date Range">Custom Date Range</option>
      <option value="Previous 1 Month">Previous 1 Month</option>
      <option value="Previous 6 Months">Previous 6 Months</option>
    </select>
    <strong>Roles:</strong>
    <select x-model="filters.role">
      <option>Trainees</option>
      <option>Instructors</option>
      <option>Proctors</option>
    </select>
    <button class="blue-btn" type="button">Search</button>
  </div>

  <span class="overview-tab">Overview</span>
  <div class="overview-line"></div>
  <div class="class-metrics">
    <span><strong x-text="totalClasses"></strong> Total Classes</span>
    <span><strong x-text="averageClassSize.toFixed(1)"></strong> Average Class Size</span>
    <span><strong x-text="rateStats.perWeek.toFixed(1)"></strong> Classes per Week</span>
    <span><strong x-text="rateStats.perMonth.toFixed(1)"></strong> Classes per Month</span>
  </div>
  <h1 class="classes-over-time-title">Classes Over Time</h1>
  <div class="classes-chart-controls">
    <label><strong>Course Levels:</strong>
      <select x-model="filters.course_level_id">
        <option value="">All selected</option>
        @foreach($courseLevels as $level)
          <option value="{{ $level->id }}">{{ $level->name }}</option>
        @endforeach
      </select>
    </label>
    <label><strong>Time Periods:</strong>
      <select x-model="filters.period">
        <option value="week">By Week</option>
        <option value="month">By Month</option>
        <option value="quarter">By Quarter</option>
      </select>
    </label>
    <label><strong>Graph Type:</strong>
      <select x-model="filters.graph_type">
        <option value="bar">Bar Chart</option>
        <option value="line">Line Chart</option>
      </select>
    </label>
  </div>
  <section class="classes-time-chart" aria-label="Classes over time chart">
    <div class="classes-y-axis"><span>8</span><span>7</span><span>6</span><span>5</span><span>4</span><span>3</span><span>2</span><span>1</span><span>0</span></div>
    <div class="classes-bars-wrap">
      <svg class="classes-line-svg" x-show="filters.graph_type === 'line'" x-cloak x-bind:viewBox="'0 0 ' + Math.max(1, buckets.length) + ' 8'" preserveAspectRatio="none">
        <polyline x-bind:points="linePoints"></polyline>
      </svg>
      <div class="classes-bars" x-bind:style="'grid-template-columns: repeat(' + Math.max(1, buckets.length) + ', 1fr);'">
        <template x-for="bucket in buckets" :key="bucket.key">
          <div class="week-bar" x-bind:class="filters.graph_type === 'line' ? 'line-point' : (bucket.count === 0 ? 'zero' : '')"
               x-bind:style="filters.graph_type === 'bar' && bucket.count > 0 ? ('height:' + barHeight(bucket.count) + 'px') : ''">
            <b x-text="bucket.count" x-bind:style="filters.graph_type === 'line' ? ('bottom: calc(' + Math.min(100, (bucket.count / 8) * 100) + '% + 3px)') : ''"></b>
          </div>
        </template>
        <div class="week-bar zero" x-show="!buckets.length" x-cloak><b>0</b></div>
      </div>
    </div>
  </section>
  <div class="classes-chart-dates">
    <template x-for="bucket in buckets" :key="'label-' + bucket.key">
      <span x-text="bucket.label + '[' + bucket.count + ']'"></span>
    </template>
  </div>
  <div class="classes-chart-legend"><span></span>Classes</div>
</div>

@push('scripts')
<script>
  window.wellsharpClassesAnalytics = function (rows, initialFilters) {
    var provided = initialFilters || {};
    var pad = function (n) { return n < 10 ? '0' + n : '' + n; };

    return {
      rows: rows || [],
      filters: {
        date_range: provided.date_range || 'All Time',
        role: provided.role || 'Trainees',
        course_level_id: provided.course_level_id || '',
        period: provided.period || 'week',
        graph_type: provided.graph_type || 'bar'
      },
      dateBounds: function () {
        var range = this.filters.date_range;
        if (range === 'All Time' || range === 'Custom Date Range') return [null, null];
        var match = range.match(/^Previous (\d+) Month/);
        if (match) {
          var from = new Date();
          from.setMonth(from.getMonth() - Number(match[1]));
          from.setHours(0, 0, 0, 0);
          var to = new Date();
          to.setHours(23, 59, 59, 999);
          return [from, to];
        }
        return [null, null];
      },
      roleMatches: function (row) {
        if (this.filters.role === 'Instructors') return (row.staff_roles || []).indexOf('instructor') !== -1;
        if (this.filters.role === 'Proctors') return (row.staff_roles || []).indexOf('proctor') !== -1;
        return true;
      },
      get filteredClasses() {
        var bounds = this.dateBounds();
        var from = bounds[0], to = bounds[1];
        var self = this;

        return this.rows.filter(function (row) {
          if (from || to) {
            if (!row.starts_at) return false;
            var d = new Date(row.starts_at + 'T00:00:00');
            if (from && d < from) return false;
            if (to && d > to) return false;
          }
          if (!self.roleMatches(row)) return false;
          if (self.filters.course_level_id && String(row.course_level_id) !== String(self.filters.course_level_id)) return false;
          return true;
        });
      },
      get totalClasses() {
        return this.filteredClasses.length;
      },
      get averageClassSize() {
        var rows = this.filteredClasses;
        if (!rows.length) return 0;
        var sum = rows.reduce(function (acc, row) { return acc + Number(row.enrollments_count || 0); }, 0);

        return Math.round((sum / rows.length) * 10) / 10;
      },
      get rateStats() {
        var rows = this.filteredClasses;
        var starts = rows.map(function (row) { return row.starts_at; }).filter(Boolean).sort();
        if (!starts.length) return { perWeek: 0, perMonth: 0 };

        var earliest = new Date(starts[0] + 'T00:00:00');
        var latest = new Date(starts[starts.length - 1] + 'T00:00:00');
        var days = Math.max(0, Math.floor((latest - earliest) / 86400000));
        var weeks = Math.max(1, Math.floor(days / 7));
        var monthSpan = (latest.getFullYear() - earliest.getFullYear()) * 12 + (latest.getMonth() - earliest.getMonth()) - (latest.getDate() < earliest.getDate() ? 1 : 0);
        var months = Math.max(1, monthSpan);

        return {
          perWeek: Math.round((rows.length / weeks) * 10) / 10,
          perMonth: Math.round((rows.length / months) * 10) / 10
        };
      },
      bucketKey: function (dateStr) {
        var d = new Date(dateStr + 'T00:00:00');
        if (this.filters.period === 'quarter') {
          return d.getFullYear() + '-Q' + (Math.floor(d.getMonth() / 3) + 1);
        }
        if (this.filters.period === 'week') {
          var day = d.getDay();
          var diff = day === 0 ? -6 : 1 - day;
          var monday = new Date(d);
          monday.setDate(d.getDate() + diff);

          return monday.getFullYear() + '-' + pad(monday.getMonth() + 1) + '-' + pad(monday.getDate());
        }

        return d.getFullYear() + '-' + pad(d.getMonth() + 1);
      },
      bucketLabel: function (key) {
        if (this.filters.period === 'quarter') return key.replace('-Q', ' Q');
        var parts = key.split('-');
        if (this.filters.period === 'week') {
          var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));

          return (d.getMonth() + 1) + '/' + d.getDate() + '/' + d.getFullYear();
        }

        return Number(parts[1]) + '/' + parts[0];
      },
      get buckets() {
        var self = this;
        var groups = {};
        this.filteredClasses.filter(function (row) { return row.starts_at; }).forEach(function (row) {
          var key = self.bucketKey(row.starts_at);
          groups[key] = (groups[key] || 0) + 1;
        });

        return Object.keys(groups).sort().map(function (key) {
          return { key: key, label: self.bucketLabel(key), count: groups[key] };
        });
      },
      barHeight: function (count) {
        return Math.min(224, Math.max(32, count * 32));
      },
      get linePoints() {
        var buckets = this.buckets;

        return buckets.map(function (bucket, index) {
          return (index + 0.5) + ',' + (8 - Math.min(8, bucket.count));
        }).join(' ');
      }
    };
  };
</script>
@endpush
@endsection
