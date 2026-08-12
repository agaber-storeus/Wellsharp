<section class="panel">
  <div class="panel-title">Search Options</div>
  <div class="panel-body">
    <form class="search-form" method="GET" action="{{ route(auth()->user()->hasRole('proctor') ? 'proctor.browse.results' : 'instructor.browse.results') }}" x-data>
      <div>
        <div class="form-line"><label for="browse-search">Class Title or ID:</label><input id="browse-search" name="search" value="{{ request('search') }}" autocomplete="off" /></div>
        <div class="form-line"><label>Date Filter:</label><div class="date-inputs"><input name="start_date" type="date" value="{{ request('start_date') }}" /><span>to</span><input name="end_date" type="date" value="{{ request('end_date') }}" /></div></div>
        <div class="form-line"><label for="browse-exam-date">Exam Date:</label><input id="browse-exam-date" name="exam_date" type="date" value="{{ request('exam_date') }}" /></div>
        <div class="form-line"><label for="browse-city">City:</label><input id="browse-city" name="city" value="{{ request('city') }}" /></div>
        <div class="form-line"><label for="browse-country">Country:</label><input id="browse-country" name="country" value="{{ request('country') }}" /></div>
        <div class="form-line"><label for="browse-state">State:</label><input id="browse-state" name="state" value="{{ request('state') }}" /></div>
      </div>
      <div>
        <div class="form-line"><label for="browse-course">Subject:</label><select id="browse-course" name="course_id"><option value="">All Subjects</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected((string) request('course_id') === (string) $course->id)>{{ $course->name }}</option>@endforeach</select></div>
        <div class="form-line"><label for="browse-instructor">Instructor:</label><select id="browse-instructor" name="instructor_id"><option value="">All Instructors</option>@foreach($instructors as $instructor)<option value="{{ $instructor->id }}" @selected((string) request('instructor_id') === (string) $instructor->id)>{{ $instructor->display_name }}</option>@endforeach</select></div>
        <div class="form-line"><label for="browse-retakes">Retakes:</label><select id="browse-retakes" name="retakes"><option value="">All</option><option value="yes" @selected(request('retakes') === 'yes')>With retakes</option><option value="no" @selected(request('retakes') === 'no')>Without retakes</option></select></div>
        <p class="muted">Location filters search the provider address stored for each class.</p>
      </div>
      <div class="search-actions"><button class="blue-btn" type="submit">Search Classes</button><a class="tiny-blue" href="{{ route(auth()->user()->hasRole('proctor') ? 'proctor.browse' : 'instructor.browse') }}">Clear</a></div>
    </form>
  </div>
</section>
