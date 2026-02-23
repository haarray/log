{{-- FILE: resources/views/auth/register.blade.php --}}
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
  <title>Create Account — {{ $brandDisplayName }}</title>
  <link rel="icon" type="image/x-icon" href="{{ $brandFavicon !== '' ? $brandFavicon : asset('favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=JetBrains+Mono:wght@400;500&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link rel="stylesheet" href="{{ asset('css/haarray.app.css') }}">
</head>
<body>

<div class="h-auth-wrap">

  <div class="h-auth-left">
    <div class="h-auth-art">
      <div class="h-auth-logo">{{ strtoupper(substr($brandMark, 0, 1)) }}</div>
      <div class="h-auth-headline">
        Your money,<br><span>finally</span><br>understood.
      </div>
      <p class="h-auth-desc">
        Join HariLog and start taking control of your finances.
        IPO alerts, spending patterns, gold rates — all free.
      </p>
      <div class="h-auth-tribute">
        <strong>Built 100% free, for you.</strong><br>
        No paid APIs. No subscriptions. Just pure Laravel,
        jQuery, and smart financial logic.
      </div>
    </div>
  </div>

  <div class="h-auth-right">
    <div class="h-auth-form">

      <div style="display:flex;justify-content:flex-end;margin-bottom:22px;">
        <button class="h-theme-toggle h-icon-btn" title="Toggle theme" type="button">
          <span class="moon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg></span>
          <span class="sun" style="display:none"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/></svg></span>
        </button>
      </div>

      <div class="h-auth-title">Create account</div>
      <div class="h-auth-sub">Start your {{ $brandDisplayName }} journey — takes 30 seconds</div>

      @if($errors->any())
      <div class="h-alert error">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        {{ $errors->first() }}
      </div>
      @endif

      <form method="POST" action="{{ route('register.post') }}" id="reg-form" data-spa>
        @csrf

        {{-- Name --}}
        <div class="h-form-group">
          <label class="h-label">Full Name</label>
          <div class="h-input-wrap">
            <span class="h-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </span>
            <input type="text" name="name" class="h-input has-icon @error('name') error @enderror"
              value="{{ old('name') }}" placeholder="Pratik Bhujel" required autocomplete="name">
          </div>
        </div>

        {{-- Email --}}
        <div class="h-form-group">
          <label class="h-label">Email</label>
          <div class="h-input-wrap">
            <span class="h-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </span>
            <input type="email" name="email" class="h-input has-icon @error('email') error @enderror"
              value="{{ old('email') }}" placeholder="you@example.com" required autocomplete="email">
          </div>
        </div>

        {{-- Password --}}
        <div class="h-form-group">
          <label class="h-label">Password</label>
          <div class="h-input-wrap">
            <span class="h-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            </span>
            <input type="password" id="reg-pw" name="password" class="h-input has-icon @error('password') error @enderror"
              placeholder="Min 6 characters" required autocomplete="new-password">
            <span class="h-input-icon-r h-pw-toggle" role="button" tabindex="0">
              <svg class="eye-off" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-on" width="14" height="14" style="display:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </span>
          </div>
          <div class="h-pw-strength"><div class="h-pw-bar" id="pw-bar" style="width:0"></div></div>
          <span id="pw-lbl" class="h-hint"></span>
        </div>

        {{-- Confirm --}}
        <div class="h-form-group">
          <label class="h-label">Confirm Password</label>
          <div class="h-input-wrap">
            <span class="h-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            </span>
            <input type="password" name="password_confirmation" class="h-input has-icon"
              placeholder="Repeat password" required autocomplete="new-password">
          </div>
        </div>

        <div class="h-form-group" style="margin-top:-2px;">
          <label style="display:flex;gap:8px;align-items:flex-start;color:var(--t2);font-size:13px;line-height:1.5;">
            <input type="checkbox" name="terms" value="1" style="margin-top:2px;accent-color:var(--gold);" {{ old('terms') ? 'checked' : '' }} required>
            <span>
              I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener" style="color:var(--gold);font-weight:600;">Terms & Conditions</a>.
            </span>
          </label>
          @error('terms')
            <div class="h-hint" style="color:var(--red);">{{ $message }}</div>
          @enderror
        </div>

        <button type="submit" class="h-btn primary full lg" id="reg-btn" data-busy-text="Creating account…">
          Create Account
        </button>

      </form>

      <div class="h-divider" style="margin:18px 0;">or</div>

      <a href="{{ route('facebook.redirect') }}" class="btn btn-outline-secondary w-100 mb-3">
        <i class="fa-brands fa-facebook-f me-2"></i>
        Continue with Facebook
      </a>

      <p style="text-align:center;margin-top:20px;font-size:13.5px;color:var(--t2);">
        Already have an account?
        <a href="{{ route('login') }}" style="color:var(--gold);font-weight:600;" data-spa> Sign in</a>
      </p>

      <p style="text-align:center;margin-top:28px;font-family:var(--fm);font-size:9px;color:var(--t3);letter-spacing:1.5px;">
        HAARRAY · HARILOG · FREE FOREVER
      </p>

    </div>
  </div>

</div>

{{-- JS --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('js/haarray.app.js') }}"></script>

<script>
  // Password strength (same as before) — still works with SPA
  $('#reg-pw').on('input', function () {
    const v = $(this).val();
    let s = 0;
    if (v.length >= 6) s++;
    if (v.length >= 10) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    const lbls = ['','Weak','Fair','Good','Strong'];
    const cols = ['','var(--red)','var(--gold)','var(--teal)','var(--green)'];
    $('#pw-bar').css({ width: (s * 25) + '%', background: cols[s] });
    $('#pw-lbl').text(lbls[s] || '').css('color', cols[s]);
  });

  // Non-SPA fallback busy-text behavior
  $('#reg-form').on('submit', function () {
    if (!$(this).is('[data-spa]')) {
      $('#reg-btn').prop('disabled', true).text('Creating account…');
      setTimeout(() => $('#reg-btn').prop('disabled', false).text('Create Account'), 8000);
    }
  });
</script>
</body>
</html>
