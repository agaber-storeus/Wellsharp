@extends('layouts.operational')

@section('operational-content')
@php
  $calendarEvents = $ongoingClasses->merge($upcomingClasses)->map(function ($trainingClass): array {
      $instructor = 'Any eligible Instructor';

      return [
          'id' => $trainingClass->public_id,
          'title' => $instructor,
          'classTitle' => $trainingClass->displayTitle(),
          'courseId' => (string) $trainingClass->course_id,
          'location' => $trainingClass->provider?->address ?: $trainingClass->provider?->name ?: 'Location not assigned',
          'start' => $trainingClass->starts_at?->toDateString(),
          'end' => ($trainingClass->ends_at ?: $trainingClass->starts_at)?->toDateString(),
          'status' => $trainingClass->status->value,
          'state' => $trainingClass->status->value === 'active' ? 'open' : 'notstarted',
      ];
  })->filter(fn (array $event): bool => filled($event['start']))->values();

  $calendarCourses = $ongoingClasses->merge($upcomingClasses)->pluck('course')->filter()->unique('id')->sortBy('name');
  $classDateLabel = function ($trainingClass): string {
      if (! $trainingClass->starts_at && ! $trainingClass->ends_at) {
          return 'Not scheduled';
      }

      if (! $trainingClass->starts_at) {
          return $trainingClass->ends_at->format('F j, Y');
      }

      if (! $trainingClass->ends_at) {
          return $trainingClass->starts_at->format('F j, Y');
      }

      return $trainingClass->starts_at->format('F j').' - '.$trainingClass->ends_at->format('j, Y');
  };

  $classRows = $ongoingClasses->merge($upcomingClasses)->map(function ($trainingClass) use ($classDateLabel): array {
      $modalState = $trainingClass->status->value === 'active' ? 'open' : 'notstarted';

      return [
          'id' => $trainingClass->public_id,
          'state' => $modalState,
          'title' => $trainingClass->displayTitle(),
          'dateLabel' => $classDateLabel($trainingClass),
          'location' => $trainingClass->provider?->address ?: $trainingClass->provider?->name ?: 'Not assigned',
      ];
  })->values();
@endphp

<section class="panel map-panel classes-map-panel">
  <div class="panel-title">Class Map</div>
  <div class="panel-body">
    <div id="classesLeafletMap" class="classes-leaflet-map"></div>
    <div class="classes-map-fallback">
      <div class="map-controls"><button type="button">+</button><button type="button">-</button></div>
      <span class="pin classes-pin-egypt"></span>
      <span class="pin classes-pin-kuwait"></span>
      <span class="pin classes-pin-nigeria"></span>
      <span>Class locations are unavailable on the map.</span>
    </div>
    <div class="map-filter classes-map-filter">
      <span>Report Type:</span>
      <select id="classesReportType">
        <option value="all">Class History</option>
        <option value="scheduled">Scheduled Classes</option>
      </select>
      <span>Time Frame:</span>
      <select id="classesTimeFrame">
        <option value="all">All Time</option>
        <option value="1">Previous 1 Month</option>
      </select>
    </div>
  </div>
</section>

<div class="upcoming-grid" x-data="wellsharpClassesPage(@js($calendarEvents), @js($classRows))">
  <section class="mini-panel">
    <div class="panel-title">Ongoing/ Upcoming Classes</div>
    <div class="panel-body">
      <table class="status-table">
        <thead><tr><th>Test Status</th><th>Course Dates</th><th>Location</th></tr></thead>
        <tbody>
          <template x-for="row in pageRows" :key="row.id">
            <tr>
              <td><span :class="row.state === 'open' ? 'state-green' : 'state-blue'"></span><a href="#" data-class-modal="details" :data-class-state="row.state" :data-class-id="row.id" x-text="row.title"></a></td>
              <td x-text="row.dateLabel"></td>
              <td x-text="row.location"></td>
            </tr>
          </template>
          <tr x-show="!pageRows.length" x-cloak><td colspan="3">No ongoing or upcoming classes.</td></tr>
        </tbody>
      </table>
      <p class="results-caption" x-text="resultSummary"></p>
      <div class="pagination" x-show="pageCount > 1" x-cloak>
        <button type="button" x-on:click="page = Math.max(1, page - 1)" x-bind:disabled="page === 1">Previous</button>
        <span class="page-box" x-text="page"></span>
        <button type="button" x-on:click="page = Math.min(pageCount, page + 1)" x-bind:disabled="page === pageCount">Next</button>
      </div>
    </div>
  </section>

  <div>
    <section class="mini-panel">
      <div class="panel-title">Calendar Filters</div>
      <div class="panel-body calendar-filter-body" x-data="{ expanded: false }" :class="expanded ? 'is-expanded' : ''">
        <div class="calendar-filter-compact" x-show="!expanded" x-cloak>
          <span x-text="courseId ? selectedCourseName : 'All Courses'"></span>
          <button id="calendarFilterToggle" type="button" x-on:click="expanded = true">Filter</button>
        </div>
        <div class="calendar-filter-expanded" x-show="expanded" x-cloak>
          <select aria-label="Filter calendar by subject" x-model="courseId">
            <option value="">All Courses</option>
            @foreach($calendarCourses as $course)
              <option value="{{ $course->id }}">{{ $course->name }}</option>
            @endforeach
          </select>
          <button id="calendarFilterClose" type="button" x-on:click="expanded = false">Show All Courses</button>
        </div>
      </div>
    </section>

    <section class="mini-panel">
      <div class="panel-title">Calendar Schedule</div>
      <div class="calendar" x-data="wellsharpCalendarGrid()">
        <div class="calendar-head">
          <span x-text="monthLabel"></span>
          <span><button type="button" x-on:click="today">today</button> <button type="button" x-on:click="previousMonth">&lt;</button> <button type="button" x-on:click="nextMonth">&gt;</button></span>
        </div>
        <div class="calendar-grid calendar-reference-grid">
          <div class="calendar-week-row">
            <template x-for="weekday in weekdays" :key="weekday"><div class="calendar-cell calendar-weekday" x-text="weekday"></div></template>
          </div>
          <template x-for="week in weeks" :key="week.key">
            <div class="calendar-week-row">
              <template x-for="(day, index) in week.days" :key="day.key">
                <div class="calendar-cell" :class="{ 'calendar-outside-day': day.outside, 'active-day': day.today }" :style="'grid-column:' + (index + 1) + ';grid-row:1 / ' + week.rowSpan">
                  <span class="calendar-day-number" x-text="day.number"></span>
                </div>
              </template>
              <template x-for="bar in week.bars" :key="bar.id + '-' + week.key">
                <a class="calendar-event calendar-event-span" :class="'calendar-event-' + bar.status" :style="'grid-column:' + bar.startCol + ' / ' + bar.endCol + ';grid-row:' + bar.rowIndex" href="#" data-class-modal="details" :data-class-state="bar.state" :data-class-id="bar.id" :title="bar.classTitle" x-text="bar.title"></a>
              </template>
            </div>
          </template>
        </div>
      </div>
    </section>
  </div>
</div>

@push('scripts')
<script>
  window.wellsharpClassPoints = @json($mapPoints);
  window.wellsharpClassModalData = @json($classModalData);

  window.wellsharpClassesPage = function (events, rows) {
    var source = events || [];

    return {
      events: source,
      rows: rows || [],
      courseId: '',
      page: 1,
      perPage: 4,
      get selectedCourseName() {
        var event = this.events.find(function (item) { return String(item.courseId) === String(this.courseId); }, this);
        return event ? event.classTitle : 'All Courses';
      },
      filteredEvents: function () {
        var selected = String(this.courseId || '');
        return this.events.filter(function (event) { return !selected || String(event.courseId) === selected; });
      },
      get pageCount() {
        return Math.max(1, Math.ceil(this.rows.length / this.perPage));
      },
      get pageRows() {
        var start = (this.page - 1) * this.perPage;
        return this.rows.slice(start, start + this.perPage);
      },
      get resultSummary() {
        if (!this.rows.length) return 'Showing 0 to 0 of 0 entries';
        var from = (this.page - 1) * this.perPage + 1;
        var to = Math.min(this.page * this.perPage, this.rows.length);
        return 'Showing ' + from + ' to ' + to + ' of ' + this.rows.length + ' entries';
      }
    };
  };

  window.wellsharpCalendarGrid = function () {
    var page = function () { return Alpine.$data(document.querySelector('.upcoming-grid')); };
    var now = new Date();
    var initial = new Date(now.getFullYear(), now.getMonth(), 1);

    return {
      monthDate: initial,
      weekdays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
      get monthLabel() {
        return this.monthDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
      },
      get weeks() {
        var first = new Date(this.monthDate.getFullYear(), this.monthDate.getMonth(), 1);
        var start = new Date(this.monthDate.getFullYear(), this.monthDate.getMonth(), 1 - first.getDay());
        var selectedEvents = page().filteredEvents();
        var today = this.dateKey(new Date());
        var dateKey = this.dateKey;
        var weeks = [];

        for (var w = 0; w < 6; w += 1) {
          var days = [];

          for (var d = 0; d < 7; d += 1) {
            var date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + (w * 7) + d);
            var key = dateKey(date);
            days.push({
              key: key,
              number: date.getDate(),
              outside: date.getMonth() !== this.monthDate.getMonth(),
              today: key === today
            });
          }

          var weekStartKey = days[0].key;
          var weekEndKey = days[6].key;

          // Each multi-day class renders as a single bar spanning its grid columns
          // (clipped to this week), packed onto the fewest rows that avoid overlap.
          var bars = selectedEvents
            .filter(function (event) { return event.start <= weekEndKey && (event.end || event.start) >= weekStartKey; })
            .map(function (event) {
              var end = event.end || event.start;
              var clippedStart = event.start < weekStartKey ? weekStartKey : event.start;
              var clippedEnd = end > weekEndKey ? weekEndKey : end;
              var startIndex = days.findIndex(function (day) { return day.key === clippedStart; });
              var endIndex = days.findIndex(function (day) { return day.key === clippedEnd; });

              return Object.assign({}, event, {
                startCol: startIndex + 1,
                endCol: endIndex + 2
              });
            });

          var rows = [];
          bars.forEach(function (bar) {
            var rowIndex = rows.findIndex(function (row) {
              return row.every(function (placed) { return bar.endCol <= placed.startCol || bar.startCol >= placed.endCol; });
            });
            if (rowIndex === -1) {
              rowIndex = rows.length;
              rows.push([]);
            }
            rows[rowIndex].push(bar);
            bar.rowIndex = rowIndex + 2;
          });

          weeks.push({ key: weekStartKey, days: days, bars: bars, rowSpan: rows.length + 2 });
        }

        return weeks;
      },
      dateKey: function (date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
      },
      previousMonth: function () { this.monthDate = new Date(this.monthDate.getFullYear(), this.monthDate.getMonth() - 1, 1); },
      nextMonth: function () { this.monthDate = new Date(this.monthDate.getFullYear(), this.monthDate.getMonth() + 1, 1); },
      today: function () { var date = new Date(); this.monthDate = new Date(date.getFullYear(), date.getMonth(), 1); }
    };
  };

  window.wellsharpCalendar = window.wellsharpCalendarGrid;
</script>
<script src="{{ asset('js/proctor-classes-laravel.js') }}?v={{ filemtime(public_path('js/proctor-classes-laravel.js')) }}"></script>
@endpush
@endsection
