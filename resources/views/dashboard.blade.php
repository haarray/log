@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('topbar_extra')
  <div class="h-live-badge">
    <span class="h-pulse"></span>
    Market Live
  </div>
@endsection

@section('content')

  {{-- Market ticker --}}
  <div class="h-ticker">
    <span class="h-ticker-lbl">Live ·</span>
    <div class="h-ticker-item">
      <span class="h-ticker-name">Gold/tola</span>
      <span class="h-ticker-val">रू {{ number_format($market['gold']) }}</span>
      <span class="h-ticker-chg {{ $market['gold_up'] ? 'up' : 'dn' }}">{{ $market['gold_chg'] }}</span>
    </div>
    <div class="h-ticker-div"></div>
    <div class="h-ticker-item">
      <span class="h-ticker-name">NEPSE</span>
      <span class="h-ticker-val">{{ $market['nepse'] }}</span>
      <span class="h-ticker-chg {{ $market['nepse_up'] ? 'up' : 'dn' }}">{{ $market['nepse_chg'] }}</span>
    </div>
    <div class="h-ticker-div"></div>
    <div class="h-ticker-item">
      <span class="h-ticker-name">USD/NPR</span>
      <span class="h-ticker-val">{{ $market['usd_npr'] }}</span>
      <span class="h-ticker-chg {{ $market['usd_up'] ? 'up' : 'dn' }}">{{ $market['usd_chg'] }}</span>
    </div>
  </div>

  {{-- Page header --}}
  <div class="h-page-header">
    <div>
      <div class="h-page-title">
        Welcome back, Dharma.
      </div>
      <div class="h-page-sub">Here's your financial snapshot</div>
    </div>
    <button class="h-btn primary" data-modal-open="quick-log-modal">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Quick Log
    </button>
  </div>

  {{-- Stat cards --}}
  <div class="h-grid-4">
    <div class="h-stat-card ga">
      <div class="h-stat-icon" style="background:rgba(47,125,246,.12)">💰</div>
      <div class="h-stat-label">Net Worth</div>
      <div class="h-stat-val gold">रू {{ number_format($stats['net_worth']) }}</div>
      <div class="h-stat-chg up">▲ 3.2% this month</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-icon" style="background:rgba(248,113,113,.10)">📉</div>
      <div class="h-stat-label">Spent This Month</div>
      <div class="h-stat-val">रू {{ number_format($stats['monthly_spend']) }}</div>
      <div class="h-stat-chg dn">▲ 8% vs last month</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-icon" style="background:rgba(74,222,128,.10)">📈</div>
      <div class="h-stat-label">Savings Rate</div>
      <div class="h-stat-val teal">{{ $stats['savings_rate'] }}%</div>
      <div class="h-stat-chg up">▲ Goal: 30% ✓</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-icon" style="background:rgba(47,125,246,.10)">🏦</div>
      <div class="h-stat-label">Idle Cash</div>
      <div class="h-stat-val">रू {{ number_format($stats['idle_cash']) }}</div>
      <div class="h-stat-chg warn">⚡ 2 suggestions</div>
    </div>
  </div>

  {{-- AI Suggestions --}}
  <div class="h-grid-2">
    @foreach($suggestions as $s)
    <div class="h-sug-card {{ $s['priority'] === 'high' ? 'high' : '' }}">
      <div class="h-sug-icon" style="background:{{ $s['priority'] === 'high' ? 'rgba(47,125,246,.10)' : 'rgba(45,212,191,.10)' }}">
        {{ $s['icon'] }}
      </div>
      <div>
        <div class="h-sug-title">{{ $s['title'] }}</div>
        <div class="h-sug-body">{{ $s['body'] }}</div>
        <div style="margin-top:8px;">
          <span class="h-badge {{ $s['priority'] === 'high' ? 'gold' : 'muted' }}">
            {{ strtoupper($s['priority']) }}
          </span>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Main panels --}}
  <div class="h-grid-main">

    {{-- Chart --}}
    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Monthly Overview</div>
        <div class="h-card-meta">{{ strtoupper(now()->format('M Y')) }}</div>
      </div>
      <div class="h-card-body">
        <div class="h-bar-legend">
          <div class="h-bar-legend-item"><div class="h-bar-legend-dot" style="background:var(--teal)"></div>Income</div>
          <div class="h-bar-legend-item"><div class="h-bar-legend-dot" style="background:var(--gold)"></div>Expense</div>
        </div>
        <div class="h-bars" id="chart-bars"></div>
      </div>
    </div>

    {{-- Transactions --}}
    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Recent Transactions</div>
        <span class="h-card-meta" style="color:var(--gold);cursor:pointer;" onclick="HToast.info('Transactions page coming soon!')">View all →</span>
      </div>
      <div style="padding:6px 0;">
        @foreach($transactions as $tx)
        <div class="h-tx-item">
          <div class="h-tx-cat" style="background:{{ $tx['type'] === 'credit' ? 'rgba(74,222,128,.10)' : 'rgba(248,113,113,.10)' }}">
            {{ $tx['icon'] }}
          </div>
          <div style="flex:1;min-width:0;">
            <div class="h-tx-name">{{ $tx['name'] }}</div>
            <div class="h-tx-sub">{{ $tx['sub'] }}</div>
          </div>
          <div class="h-tx-amt {{ $tx['type'] === 'credit' ? 'cr' : 'dr' }}">
            {{ $tx['type'] === 'credit' ? '+' : '–' }} रू {{ number_format($tx['amount']) }}
          </div>
        </div>
        @endforeach
      </div>
    </div>

    {{-- IPO Tracker --}}
    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">IPO Tracker</div>
        <div class="h-card-meta">NEPAL · CDSC</div>
      </div>
      <div class="h-card-body">
        @foreach($ipos as $ipo)
        @php
          $status = strtolower((string) ($ipo['status'] ?? 'upcoming'));
          $openDate = $ipo['open_date'] ?? null;
          $closeDate = $ipo['close_date'] ?? null;
          $dateLabel = ($openDate || $closeDate)
            ? trim(($openDate ? $hAdDate($openDate) : '-') . ' — ' . ($closeDate ? $hAdDate($closeDate) : '-'))
            : ($ipo['dates'] ?? 'Date TBA');
        @endphp
        <div class="h-ipo-item">
          <div class="h-ipo-name">{{ $ipo['name'] ?? 'IPO' }}</div>
          <div class="h-ipo-row">
            <span class="h-ipo-dates">{{ $dateLabel }}</span>
            <span class="h-badge {{ $status === 'open' ? 'green' : 'gold' }}">
              {{ strtoupper($status) }}
            </span>
          </div>
          <div style="font-family:var(--fm);font-size:10px;color:var(--t3);">
            रू {{ $ipo['unit'] ?? 0 }}/unit · Min {{ $ipo['min'] ?? 0 }} units · रू {{ number_format((float)($ipo['unit'] ?? 0) * (float)($ipo['min'] ?? 0)) }}
          </div>
        </div>
        @endforeach

        {{-- Recommendation box --}}
        <div style="margin-top:14px;padding:11px 13px;background:rgba(47,125,246,.06);border:1px solid rgba(47,125,246,.20);border-radius:10px;">
          <div style="font-family:var(--fm);font-size:9.5px;color:var(--gold);letter-spacing:1px;margin-bottom:4px;">⚡ AI RECOMMENDATION</div>
          <div style="font-size:12px;color:var(--t2);line-height:1.6;">Apply for Citizens Bank IPO with your idle cash. Closes in 2 days.</div>
        </div>
      </div>
    </div>

  </div>

@endsection

{{-- Quick Log Modal --}}
@section('modals')
<div class="h-modal-overlay" id="quick-log-modal">
  <div class="h-modal">
    <div class="h-modal-head">
      <div class="h-modal-title">Quick Log Transaction</div>
      <button class="h-modal-close">×</button>
    </div>
    <form method="POST" action="{{ route('transactions.store') }}" data-spa id="quick-log-form">
      @csrf
      <div class="h-modal-body">
        <div class="h-alert info">
          Telegram shortcut:
          <code style="background:rgba(0,0,0,.2);padding:1px 5px;border-radius:4px;font-family:var(--fm);">/log 200 food tea</code>
        </div>

        <div style="display:flex;gap:8px;margin-bottom:16px;" id="type-tabs">
          <button type="button" class="h-btn ghost" style="flex:1;font-size:12px;padding:8px;" data-type="debit" id="tab-exp">Expense</button>
          <button type="button" class="h-btn ghost" style="flex:1;font-size:12px;padding:8px;" data-type="credit" id="tab-inc">Income</button>
        </div>
        <input type="hidden" id="ql-type" name="type" value="debit">

        <div class="h-form-group">
          <label class="h-label">Amount (NPR)</label>
          <input type="number" id="ql-amount" name="amount" class="h-input" placeholder="0" min="0.01" step="0.01" required>
        </div>

        <div class="h-form-group">
          <label class="h-label">Account</label>
          <select class="h-input" name="account_id">
            <option value="">No account</option>
            @foreach($quickLogAccounts as $account)
              <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->currency }})</option>
            @endforeach
          </select>
        </div>

        <div class="h-form-group">
          <label class="h-label">Category</label>
          <select class="h-input" name="category_id">
            <option value="">No category</option>
            @foreach($quickLogCategories as $category)
              <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="h-form-group">
          <label class="h-label">Title</label>
          <input type="text" name="title" class="h-input" placeholder="Lunch / Salary / Rent">
        </div>

        <div class="h-form-group">
          <label class="h-label">Date</label>
          <input type="date" name="transaction_date" class="h-input" value="{{ now()->toDateString() }}" required>
        </div>

        <div class="h-form-group">
          <label class="h-label">Note (optional)</label>
          <input type="text" name="notes" id="ql-note" class="h-input" placeholder="What was this for?">
        </div>
      </div>
      <div class="h-modal-footer">
        <button class="h-btn ghost" data-modal-close type="button">Cancel</button>
        <button class="h-btn primary" id="ql-submit" type="submit">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Log Transaction
        </button>
      </div>
    </form>
  </div>
</div>
@endsection

{{-- FAB --}}
@section('fab')
<div class="h-fab">
  <span class="h-fab-label">Quick Log</span>
  <button class="h-fab-btn" data-modal-open="quick-log-modal">+</button>
</div>
@endsection

@section('scripts')
<script>
$(function () {

  // ── Bar chart ────────────────────────────────────────
  const chartData = {
    labels:  @json($chart['labels']),
    income:  @json($chart['income']),
    expense: @json($chart['expense']),
  };

  const maxVal = Math.max(...chartData.income, ...chartData.expense);
  const $bars  = $('#chart-bars');
  $bars.empty();

  chartData.labels.forEach((lbl, i) => {
    const incH = Math.round((chartData.income[i]  / maxVal) * 100);
    const expH = Math.round((chartData.expense[i] / maxVal) * 100);
    $bars.append(`
      <div class="h-bar-col">
        <div class="h-bar inc" style="height:${incH}%" title="Income: रू ${chartData.income[i].toLocaleString('en-IN')}"></div>
        <div class="h-bar exp" style="height:${expH}%" title="Expense: रू ${chartData.expense[i].toLocaleString('en-IN')}"></div>
        <div class="h-bar-lbl">${lbl}</div>
      </div>
    `);
  });

  // ── Quick log type tabs ───────────────────────────────
  const setType = function (type, btn) {
    $('#type-tabs button').removeClass('danger teal').addClass('ghost');
    if (type === 'debit')  $(btn).removeClass('ghost').addClass('danger');
    if (type === 'credit') $(btn).removeClass('ghost').addClass('teal');
    $('#ql-type').val(type);
  };
  const expBtn = document.getElementById('tab-exp');
  if (expBtn) {
    setType('debit', expBtn);
  }
  $('#tab-exp').off('click.quickType').on('click.quickType', function () { setType('debit', this); });
  $('#tab-inc').off('click.quickType').on('click.quickType', function () { setType('credit', this); });

  $('#quick-log-form').off('submit.hQuickLog').on('submit.hQuickLog', function () {
    const amt = parseFloat($('#ql-amount').val());
    if (!amt || amt <= 0) {
      HToast.warning('Enter an amount first.');
      return false;
    }
    return true;
  });

});
</script>
@endsection
