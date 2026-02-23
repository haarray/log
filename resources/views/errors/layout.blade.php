<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Application Error') — {{ config('app.name', 'Haarray Core') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="{{ asset('css/haarray.app.css') }}">
  <style>
    body.h-error-screen {
      min-height: 100vh;
      margin: 0;
      display: grid;
      place-items: center;
      background:
        radial-gradient(circle at 20% 10%, rgba(47, 125, 246, 0.18) 0%, transparent 45%),
        radial-gradient(circle at 80% 90%, rgba(34, 197, 94, 0.14) 0%, transparent 42%),
        var(--bg1);
      color: var(--t1);
      padding: 20px;
    }

    .h-error-shell {
      width: min(720px, 100%);
      border: 1px solid var(--bd2);
      background: color-mix(in oklab, var(--bg2) 94%, transparent);
      border-radius: 18px;
      padding: 30px;
      box-shadow: 0 18px 56px rgba(0, 0, 0, 0.35);
    }

    .h-error-code {
      font-family: var(--fm);
      font-size: 13px;
      letter-spacing: 0.18em;
      color: var(--gold);
      margin-bottom: 8px;
    }

    .h-error-title {
      font-family: var(--fd);
      font-size: clamp(28px, 4vw, 38px);
      line-height: 1.08;
      margin: 0 0 12px;
    }

    .h-error-copy {
      color: var(--t2);
      font-size: 14px;
      margin-bottom: 18px;
    }

    .h-error-help {
      border: 1px solid var(--bd2);
      background: var(--bg3);
      border-radius: 12px;
      padding: 12px;
      color: var(--t2);
      font-size: 12px;
      margin-bottom: 18px;
    }

    .h-error-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
  </style>
</head>
<body class="h-error-screen">
  <main class="h-error-shell" role="main" aria-live="polite">
    <div class="h-error-code">@yield('code', 'ERROR')</div>
    <h1 class="h-error-title">@yield('heading', 'Something went wrong')</h1>
    <p class="h-error-copy">@yield('message', 'The request could not be completed.') </p>

    <div class="h-error-help">
      <i class="fa-solid fa-circle-info me-2"></i>
      If this keeps happening, check <code>storage/logs/laravel.log</code> and your server rewrite/public path setup.
    </div>

    <div class="h-error-actions">
      @yield('actions')
      <a href="{{ url('/') }}" class="btn btn-primary">
        <i class="fa-solid fa-house me-2"></i>
        Home
      </a>
      <button type="button" class="btn btn-outline-secondary" onclick="window.location.reload()">
        <i class="fa-solid fa-rotate me-2"></i>
        Reload
      </button>
    </div>
  </main>
</body>
</html>
