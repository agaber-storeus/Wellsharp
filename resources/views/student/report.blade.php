<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>WellSharp - Report</title>
    <link rel="stylesheet" href="{{ asset('css/student.css') }}?v={{ filemtime(public_path('css/student.css')) }}" />
  </head>
  <body class="report-page">
    <div class="report-backdrop">
      <img class="report-bg-logo" src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" width="310" height="80" />
      <div class="report-timer">Time Remaining: 00:00<br />User: {{ auth()->user()->display_name }}</div>
    </div>
    <section class="report-modal" aria-labelledby="report-title">
      <form method="POST" action="{{ route('logout') }}" class="report-close-form">
        @csrf
        <button class="report-close" type="submit" aria-label="Exit exam">x</button>
      </form>
      <header>
        <img src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" width="151" height="50" />
        <h1 id="report-title">Knowledge Assessment Report</h1>
      </header>
      <div class="report-line"><strong>Assessment Date :</strong><span>{{ $attempt->submitted_at?->format('Y-m-d') ?: now()->format('Y-m-d') }}</span></div>
      <h2>You have finished the exam, your result submitted to the instructor.</h2>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="green-btn" type="submit">Exit Exam</button>
      </form>
    </section>
  </body>
</html>
