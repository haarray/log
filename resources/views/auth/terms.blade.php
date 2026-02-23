<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Terms & Conditions</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="{{ asset('css/haarray.app.css') }}">
</head>
<body>
  <div class="container py-5" style="max-width:900px;">
    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Terms & Conditions</div>
        <div class="h-card-meta">Version {{ config('haarray.terms_version', '2026-02-23') }}</div>
      </div>
      <div class="h-card-body" style="display:grid;gap:12px;line-height:1.7;color:var(--t2);">
        <p>By creating an account, you agree that this app stores your financial logs, investment records, and notification preferences for your own use.</p>
        <p>You are responsible for the accuracy of entries such as expenses, bank balances, IPO data, and gold purchases.</p>
        <p>Telegram integration works only when your account is linked with your chat id. Do not share bot tokens or webhook URLs publicly.</p>
        <p>Market data, ML suggestions, and alerts are informational. They are not financial advice and may be delayed or incomplete.</p>
        <p>Root-level access is reserved for the configured owner account. Misuse, unauthorized access attempts, or abuse can result in access removal.</p>
      </div>
      <div class="h-card-foot" style="padding:14px 18px;display:flex;justify-content:flex-end;">
        <a class="h-btn ghost" href="{{ route('register') }}">Back</a>
      </div>
    </div>
  </div>
</body>
</html>
