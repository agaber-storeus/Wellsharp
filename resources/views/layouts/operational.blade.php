<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'WellSharp Operational Workspace' }}</title>
    <link rel="stylesheet" href="{{ asset('css/proctor.css') }}?v={{ filemtime(public_path('css/proctor.css')) }}" />
    <link rel="stylesheet" href="{{ asset('css/alerts.css') }}?v={{ filemtime(public_path('css/alerts.css')) }}" />
    <link rel="stylesheet" href="{{ asset('css/prototype-laravel-overrides.css') }}?v={{ filemtime(public_path('css/prototype-laravel-overrides.css')) }}" />
    <link rel="stylesheet" href="{{ asset('css/certificate-documents.css') }}?v={{ filemtime(public_path('css/certificate-documents.css')) }}" />
    <style>[x-cloak]{display:none!important}</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    @vite('resources/js/maps.js')
  </head>
  <body>
    <main class="proctor-shell">
      <header class="topbar">
        <a href="{{ route(auth()->user()->hasRole('proctor') ? 'proctor.dashboard' : 'instructor.dashboard') }}"><img class="brand-logo" src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" /></a>
        <div class="user-tools">
          <div class="user-line"><strong>{{ auth()->user()->display_name }}</strong>
            <form method="POST" action="{{ route('logout') }}" class="inline-form">@csrf<button class="signout" type="submit">Sign Out</button></form>
          </div>
          <a href="https://intellidata.tech/wellsharp/support1/" class="support-link" target="_blank" rel="noopener">Database/Program Assistance?</a>
          <a href="https://intellidata.tech/wellsharp/support2/" class="support-link" target="_blank" rel="noopener">Proctor Assistance?</a>
        </div>
      </header>

      <div class="main-area">
        <aside class="proctor-sidebar">
          @php($operationalRoutePrefix = auth()->user()->hasRole('proctor') ? 'proctor' : 'instructor')
          <a class="nav-item {{ request()->routeIs($operationalRoutePrefix.'.dashboard') ? 'active' : '' }}" href="{{ route($operationalRoutePrefix.'.dashboard') }}"><span class="nav-icon">&#8962;</span>Home</a>
          <a class="nav-item {{ request()->routeIs($operationalRoutePrefix.'.profile') ? 'active' : '' }}" href="{{ route($operationalRoutePrefix.'.profile') }}"><span class="nav-icon">&#128100;</span>My Profile</a>
          <a class="nav-item {{ request()->routeIs($operationalRoutePrefix.'.analytics*') ? 'active' : '' }}" href="{{ route($operationalRoutePrefix.'.analytics') }}"><span class="nav-icon">&#9685;</span>Analytics</a>
          @if(request()->routeIs($operationalRoutePrefix.'.analytics*'))
            <a class="nav-item small {{ request()->routeIs($operationalRoutePrefix.'.analytics.search', $operationalRoutePrefix.'.analytics.results') ? 'active' : '' }}" href="{{ route($operationalRoutePrefix.'.analytics.search') }}"><span class="nav-icon">&#9636;</span>Assessments</a>
            <a class="nav-item small {{ request()->routeIs($operationalRoutePrefix.'.analytics') ? 'active' : '' }}" href="{{ route($operationalRoutePrefix.'.analytics') }}"><span class="nav-icon">&#127891;</span>Classes</a>
          @endif
          <a class="nav-item {{ request()->routeIs($operationalRoutePrefix.'.classes') ? 'active' : '' }}" href="{{ route($operationalRoutePrefix.'.classes') }}"><span class="nav-icon">&#127891;</span>Classes</a>
          <a class="nav-item {{ request()->routeIs($operationalRoutePrefix.'.browse*') ? 'active' : '' }}" href="{{ route($operationalRoutePrefix.'.browse') }}"><span class="nav-icon">&#127963;</span>Browse Classes</a>
          <a class="nav-item {{ request()->routeIs($operationalRoutePrefix.'.certificate') ? 'active' : '' }}" href="{{ route($operationalRoutePrefix.'.certificate') }}"><span class="nav-icon">&#128269;</span>Certificate Lookup</a>
          <button class="collapse-btn" type="button">Collapse</button>
        </aside>

        <section class="workspace {{ $workspaceClass ?? 'proctor-home-workspace' }}">
          @if(session('status'))<div class="message-bar global-alert success" role="status">{{ session('status') }}</div>@endif
          @if($errors->any())<div class="message-bar global-alert error" role="alert">{{ $errors->first() }}</div>@endif
          @yield('operational-content')
        </section>
      </div>

      <footer class="footer">
        <div class="footer-copy"><a href="#">Technical Support</a><span>SkillGRID® (online) | Version 3.0 | &copy; 2024 Indaptive Technologies Inc.</span></div>
        <img class="footer-logo" src="{{ asset('images/ws-login_SG-Logo.png') }}" alt="SkillGRID" />
      </footer>
    </main>
    <script>
      window.wellsharpPrototypeAssets = {
        logo: @json(asset('images/iadcLoginLgo.png')),
        certificatePdf: @json(asset('images/certificates/00001835.pdf'))
      };
    </script>
    @stack('scripts')
    <script src="{{ asset('js/proctor-home-laravel.js') }}?v={{ filemtime(public_path('js/proctor-home-laravel.js')) }}"></script>
    <script src="{{ asset('js/proctor-class-modal-laravel.js') }}?v={{ filemtime(public_path('js/proctor-class-modal-laravel.js')) }}"></script>
    <script src="{{ asset('js/proctor-menu.js') }}?v={{ filemtime(public_path('js/proctor-menu.js')) }}"></script>
    <script src="{{ asset('js/auto-dismiss-alerts.js') }}?v={{ filemtime(public_path('js/auto-dismiss-alerts.js')) }}"></script>
  </body>
</html>
