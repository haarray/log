<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  @php
    $uiBranding = \App\Support\AppSettings::uiBranding();
    $brandDisplayName = trim((string) ($uiBranding['display_name'] ?? config('app.name', 'HariLog')));
    $brandMark = trim((string) ($uiBranding['brand_mark'] ?? config('haarray.app_initial', 'H')));
    $brandFavicon = \App\Support\AppSettings::resolveUiAsset((string) ($uiBranding['favicon_url'] ?? ''));
    if ($brandDisplayName === '') {
      $brandDisplayName = (string) config('app.name', 'HariLog');
    }
    if ($brandMark === '') {
      $brandMark = (string) config('haarray.app_initial', 'H');
    }
  @endphp
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Verify Login — {{ $brandDisplayName }}</title>
  <link rel="icon" type="image/x-icon" href="{{ $brandFavicon !== '' ? $brandFavicon : asset('favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=JetBrains+Mono:wght@400;500&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="{{ asset('css/haarray.app.css') }}">
</head>
<body>
<div class="h-auth-wrap">
  <div class="h-auth-left">
    <div class="h-auth-art">
      <div class="h-auth-logo">{{ strtoupper(substr($brandMark, 0, 1)) }}</div>
      <div class="h-auth-headline">
        Secure login<br><span>verification</span>
      </div>
      <p class="h-auth-desc">
        Enter the 6-digit code we sent to your email.
      </p>
    </div>
  </div>

  <div class="h-auth-right">
    <div class="h-auth-form">
      <div class="h-auth-title">Two-factor verification</div>
      <div class="h-auth-sub">Code expires in 10 minutes</div>

      @if($errors->any())
        <div class="h-alert error">
          <i class="fa-solid fa-triangle-exclamation"></i>
          {{ $errors->first() }}
        </div>
      @endif

      @if(session('success'))
        <div class="h-alert success">
          <i class="fa-solid fa-check"></i>
          {{ session('success') }}
        </div>
      @endif

      <form method="POST" action="{{ route('2fa.verify') }}" data-spa>
        @csrf
        <div class="h-form-group">
          <label class="h-label" for="code">Verification Code</label>
          <input
            id="code"
            type="text"
            name="code"
            maxlength="6"
            inputmode="numeric"
            class="h-input"
            placeholder="123456"
            required
            autocomplete="one-time-code"
          >
        </div>

        <button type="submit" class="h-btn primary full lg" data-busy-text="Verifying...">
          <i class="fa-solid fa-shield-halved"></i>
          Verify and Continue
        </button>
      </form>

      <form method="POST" action="{{ route('2fa.resend') }}" style="margin-top:10px;" data-spa>
        @csrf
        <button type="submit" class="h-btn ghost full" data-busy-text="Sending...">
          <i class="fa-solid fa-rotate"></i>
          Resend Code
        </button>
      </form>

      <p style="text-align:center;margin-top:18px;font-size:13px;color:var(--t2);">
        <a href="{{ route('login') }}" style="color:var(--gold);" data-spa>Back to login</a>
      </p>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/haarray.app.js') }}"></script>
</body>
</html>
