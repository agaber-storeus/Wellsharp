<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>WellSharp Login</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/prototype-laravel-overrides.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/alerts.css') }}?v={{ filemtime(public_path('css/alerts.css')) }}" />
  </head>
  <body>
    <main class="login-page">
      <section class="login-card" aria-label="WellSharp login">
        <header class="card-header">
          <img class="iadc-logo" src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" />
        </header>

        <div class="hero-area">
          <form class="login-form" action="{{ route('login.store') }}" method="POST">
            @csrf
            @if($errors->any())
              <div class="login-error global-alert" role="alert">{{ $errors->first() }}</div>
            @endif
            <input class="field" type="text" name="wellsharp_id" value="{{ old('wellsharp_id') }}" placeholder="WellSharp® ID" aria-label="WellSharp ID" autocomplete="username" required autofocus />
            <input class="field" type="password" name="password" placeholder="Password" aria-label="Password" autocomplete="current-password" required />
            <button class="login-button" type="submit">Login</button>
            <a class="small-link forgot-link" href="#">Forgot your password?</a>
          </form>

          <img class="rig-image" src="{{ asset('images/login_imageZo.jpg') }}" alt="WellSharp drilling site" />
        </div>

        <footer class="card-footer">
          <div class="support-links">
            <a class="small-link" href="https://intellidata.tech/wellsharp/support1/" target="_blank" rel="noopener">Database/Program Assistance?</a>
            <a class="small-link" href="https://intellidata.tech/wellsharp/support2/" target="_blank" rel="noopener">Proctor Assistance?</a>
          </div>
          <a class="small-link requirements-link" href="#">Requirements</a>
          <img class="skill-logo" src="{{ asset('images/ws-login_SG-Logo.png') }}" alt="SkillGRADER" />
        </footer>
      </section>

      <p class="copyright">SkillSTICK® v3.0 &copy; 2024 Indaptive Technologies Inc.</p>
    </main>
    <script src="{{ asset('js/auto-dismiss-alerts.js') }}?v={{ filemtime(public_path('js/auto-dismiss-alerts.js')) }}"></script>
  </body>
</html>
