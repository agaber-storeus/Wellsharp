@extends('layouts.operational')

@section('operational-content')
@php
  $calendarEvents = $classes->map(function ($trainingClass): array {
      $instructor = 'Any eligible Instructor';

      return [
          'id' => $trainingClass->public_id,
          'title' => $instructor,
          'classTitle' => $trainingClass->course->name,
          'courseId' => (string) $trainingClass->course_id,
          'location' => $trainingClass->provider?->address ?: $trainingClass->provider?->name ?: 'Location not assigned',
          'start' => $trainingClass->starts_at?->toDateString(),
          'end' => ($trainingClass->ends_at ?: $trainingClass->starts_at)?->toDateString(),
          'status' => $trainingClass->status->value,
          'state' => match ($trainingClass->status->value) {
              'active' => 'open',
              'planned' => 'notstarted',
              default => 'ended',
          },
      ];
  })->filter(fn (array $event): bool => filled($event['start']))->values();

  $calendarCourses = $classes->pluck('course')->filter()->unique('id')->sortBy('name');
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

<div class="upcoming-grid" x-data="wellsharpClassesPage(@js($calendarEvents))">
  <section class="mini-panel">
    <div class="panel-title">Ongoing/ Upcoming Classes</div>
    <div class="panel-body">
      <table class="status-table">
        <thead><tr><th>Test Status</th><th>Course Dates</th><th>Location</th></tr></thead>
        <tbody>
          @forelse($ongoingClasses->merge($upcomingClasses) as $trainingClass)
            @php($modalState = $trainingClass->status->value === 'active' ? 'open' : 'notstarted')
            <tr>
              <td><span class="{{ $modalState === 'open' ? 'state-green' : 'state-blue' }}"></span><a href="#" data-class-modal="details" data-class-state="{{ $modalState }}" data-class-id="{{ $trainingClass->public_id }}">{{ $trainingClass->course->name }}</a></td>
              <td>{{ $classDateLabel($trainingClass) }}</td>
              <td>{{ $trainingClass->provider?->address ?: $trainingClass->provider?->name ?: 'Not assigned' }}</td>
            </tr>
          @empty
            <tr><td colspan="3">No ongoing or upcoming classes.</td></tr>
          @endforelse
        </tbody>
      </table>
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
        <div class="calendar-reading-tip"><strong>How to read:</strong> Each class appears on every date in its continuous course range.</div>
        <div class="calendar-grid calendar-reference-grid">
          <template x-for="weekday in weekdays" :key="weekday"><div class="calendar-cell calendar-weekday" x-text="weekday"></div></template>
          <template x-for="day in days" :key="day.key">
            <div class="calendar-cell" :class="{ 'calendar-outside-day': day.outside, 'active-day': day.today }">
              <span class="calendar-day-number" x-text="day.number"></span>
              <template x-for="event in day.events" :key="event.id + '-' + day.key">
                <a class="calendar-event" :class="'calendar-event-' + event.status" href="#" data-class-modal="details" :data-class-state="event.state" :data-class-id="event.id" :title="event.classTitle" x-text="event.title"></a>
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

  window.wellsharpClassesPage = function (events) {
    var source = events || [];

    return {
      events: source,
      courseId: '',
      get selectedCourseName() {
        var event = this.events.find(function (item) { return String(item.courseId) === String(this.courseId); }, this);
        return event ? event.classTitle : 'All Courses';
      },
      filteredEvents: function () {
        var selected = String(this.courseId || '');
        return this.events.filter(function (event) { return !selected || String(event.courseId) === selected; });
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
      get days() {
        var first = new Date(this.monthDate.getFullYear(), this.monthDate.getMonth(), 1);
        var start = new Date(this.monthDate.getFullYear(), this.monthDate.getMonth(), 1 - first.getDay());
        var selectedEvents = page().filteredEvents();
        var today = this.dateKey(new Date());
        var days = [];

        for (var index = 0; index < 42; index += 1) {
          var date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + index);
          var key = this.dateKey(date);
          days.push({
            key: key,
            number: date.getDate(),
            outside: date.getMonth() !== this.monthDate.getMonth(),
            today: key === today,
            events: selectedEvents.filter(function (event) { return event.start <= key && (event.end || event.start) >= key; })
          });
        }

        return days;
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
