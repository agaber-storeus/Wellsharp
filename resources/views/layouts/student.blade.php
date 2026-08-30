<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'WellSharp - Confirm Info' }}</title>
    <link rel="stylesheet" href="{{ asset('css/student.css') }}?v={{ filemtime(public_path('css/student.css')) }}" />
    <link rel="stylesheet" href="{{ asset('css/prototype-laravel-overrides.css') }}?v={{ filemtime(public_path('css/prototype-laravel-overrides.css')) }}" />
    <link rel="stylesheet" href="{{ asset('css/alerts.css') }}?v={{ filemtime(public_path('css/alerts.css')) }}" />
    @if(request()->routeIs('certificates.*', 'student.certificates'))<link rel="stylesheet" href="{{ asset('css/proctor.css') }}?v={{ filemtime(public_path('css/proctor.css')) }}" /><link rel="stylesheet" href="{{ asset('css/certificate-documents.css') }}?v={{ filemtime(public_path('css/certificate-documents.css')) }}" />@endif
    <style>[x-cloak]{display:none!important}</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
  </head>
  <body>
    <main class="app-shell flow-shell">
      <header class="topbar">
        <a href="{{ route('student.dashboard') }}"><img class="brand-logo" src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" /></a>
        <div class="user-tools">
          <div class="user-line"><strong>{{ auth()->user()->display_name }}</strong><form method="POST" action="{{ route('logout') }}" class="inline-form">@csrf<button class="signout" type="submit">Sign Out</button></form></div>
          <a href="https://intellidata.tech/wellsharp/support1/" class="support-link" target="_blank" rel="noopener">Database/Program Assistance?</a>
          <a href="https://intellidata.tech/wellsharp/support2/" class="support-link" target="_blank" rel="noopener">Proctor Assistance?</a>
        </div>
      </header>

      <div class="main-area">
        @php($flowSchedule = $studentFlowSchedule ?? null)
        <aside class="step-sidebar">
          <a class="step {{ ($flowStep ?? 'confirm') === 'confirm' ? 'active' : '' }}" href="{{ $flowSchedule ? route('student.confirm', $flowSchedule) : route('student.dashboard') }}"><b>1</b> Confirm Info</a>
          @if($flowSchedule)
            <a class="step {{ ($flowStep ?? '') === 'survey' ? 'active' : '' }}" href="{{ route('student.survey.start', $flowSchedule) }}"><b>2</b> Survey</a>
            <a class="step {{ ($flowStep ?? '') === 'exam' ? 'active' : '' }}" href="{{ route('student.proctor', $flowSchedule) }}"><b>3</b> Take Exam</a>
          @else
            <span class="step muted"><b>2</b> Survey</span>
            <span class="step muted"><b>3</b> Take Exam</span>
          @endif
          <button class="collapse-btn" type="button">&lsaquo; Collapse</button>
        </aside>
        @yield('student-content')
      </div>

      <footer class="app-footer">
        <div class="footer-copy"><a href="#">Technical Support</a><span>SkillGRID® (online) | Version 3.0 | &copy; 2024 Indaptive Technologies Inc.</span></div>
        <img class="footer-logo" src="{{ asset('images/ws-login_SG-Logo.png') }}" alt="SkillGRID" />
      </footer>
    </main>
    @stack('scripts')
  </body>
</html>
