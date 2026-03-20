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

  <div class="h-page-header">
    <div>
      <div class="h-page-title">Finance Command Center</div>
      <div class="h-page-sub">Welcome back {{ explode(' ', auth()->user()->name)[0] }}, your latest balances and opportunities are below.</div>
    </div>
    <div class="h-page-actions">
      <button class="h-btn primary" data-modal-open="quick-log-modal">
        <i class="ri-add-circle-line"></i>
        Quick Log
      </button>
      @can('view transactions')
        <a data-spa href="{{ route('transactions.index') }}" class="h-btn ghost">
          <i class="ri-file-list-3-line"></i>
          Open Transactions
        </a>
      @endcan
    </div>
  </div>

  <div class="h-grid-4">
    <div class="h-stat-card ga">
      <div class="h-stat-icon" style="background:rgba(47,125,246,.12)">💰</div>
      <div class="h-stat-label">Net Worth</div>
      <div class="h-stat-val gold">रू {{ number_format($stats['net_worth']) }}</div>
      <div class="h-stat-chg {{ $stats['net_worth'] >= 0 ? 'up' : 'dn' }}">Live portfolio + account value</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-icon" style="background:rgba(248,113,113,.10)">📉</div>
      <div class="h-stat-label">Spent This Month</div>
      <div class="h-stat-val">रू {{ number_format($stats['monthly_spend']) }}</div>
      <div class="h-stat-chg dn">Keep this aligned to your budget cap</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-icon" style="background:rgba(74,222,128,.10)">📈</div>
      <div class="h-stat-label">Savings Rate</div>
      <div class="h-stat-val teal">{{ $stats['savings_rate'] }}%</div>
      <div class="h-stat-chg up">Monthly income efficiency</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-icon" style="background:rgba(47,125,246,.10)">🏦</div>
      <div class="h-stat-label">Idle Cash</div>
      <div class="h-stat-val">रू {{ number_format($stats['idle_cash']) }}</div>
      <div class="h-stat-chg warn">Available for short-term allocation</div>
    </div>
  </div>

  <div class="h-grid-main" style="grid-template-columns:minmax(0,1.2fr) minmax(0,1fr) minmax(300px,.9fr);">
    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Monthly Income vs Expense</div>
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

    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Recent Transactions</div>
        @can('view transactions')
          <a data-spa href="{{ route('transactions.index') }}" class="h-card-meta" style="color:var(--gold);">View all →</a>
        @endcan
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

    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Market + IPO Watch</div>
        <div class="h-card-meta">CDSC + LIVE</div>
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
              <span class="h-badge {{ $status === 'open' ? 'green' : 'gold' }}">{{ strtoupper($status) }}</span>
            </div>
            <div style="font-family:var(--fm);font-size:10px;color:var(--t3);">
              रू {{ $ipo['unit'] ?? 0 }}/unit · Min {{ $ipo['min'] ?? 0 }} units
            </div>
          </div>
        @endforeach

        <div style="margin-top:14px;display:grid;gap:8px;">
          @foreach($suggestions as $s)
            <div class="h-note" style="background:rgba(47,125,246,.06);border-color:rgba(47,125,246,.20);">
              <div style="font-family:var(--fm);font-size:10px;color:var(--gold);letter-spacing:1px;margin-bottom:4px;">{{ strtoupper($s['priority']) }} IDEA</div>
              <div style="font-size:12px;color:var(--t2);line-height:1.6;">{{ $s['title'] }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
@endsection

@section('modals')
  <div class="h-modal-overlay" id="quick-log-modal">
    <div class="h-modal h-quick-log-modal">
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

          <div class="row g-3">
            <div class="col-md-4">
              <label class="h-label" style="display:block;">Amount (NPR)</label>
              <input type="number" id="ql-amount" name="amount" class="form-control" placeholder="0" min="0.01" step="0.01" required>
            </div>
            <div class="col-md-4">
              <label class="h-label" style="display:block;">Date</label>
              <input type="date" name="transaction_date" class="form-control" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-4">
              <label class="h-label" style="display:block;">Account</label>
              <select class="form-select" name="account_id" data-h-select>
                <option value="">No account</option>
                @foreach($quickLogAccounts as $account)
                  <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->currency }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="h-label" style="display:block;">Category</label>
              <select class="form-select" name="category_id" data-h-select>
                <option value="">No category</option>
                @foreach($quickLogCategories as $category)
                  <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="h-label" style="display:block;">Title</label>
              <input type="text" name="title" class="form-control" placeholder="Lunch / Salary / Rent">
            </div>
            <div class="col-12">
              <label class="h-label" style="display:block;">Note (optional)</label>
              <input type="text" name="notes" id="ql-note" class="form-control" placeholder="What was this for?">
            </div>
          </div>
        </div>
        <div class="h-modal-footer">
          <button class="h-btn ghost" data-modal-close type="button">Cancel</button>
          <button class="h-btn primary" id="ql-submit" type="submit">
            <i class="ri-check-double-line"></i>
            Log Transaction
          </button>
        </div>
      </form>
    </div>
  </div>
@endsection

@section('fab')
  <div class="h-fab">
    <span class="h-fab-label">Quick Log</span>
    <button class="h-fab-btn" data-modal-open="quick-log-modal">+</button>
  </div>
@endsection

@section('scripts')
  <script>
    $(function () {
      const chartData = {
        labels: @json($chart['labels']),
        income: @json($chart['income']),
        expense: @json($chart['expense']),
      };

      const maxVal = Math.max(...chartData.income, ...chartData.expense, 1);
      const $bars = $('#chart-bars');
      $bars.empty();

      chartData.labels.forEach((lbl, i) => {
        const incH = Math.round((chartData.income[i] / maxVal) * 100);
        const expH = Math.round((chartData.expense[i] / maxVal) * 100);
        $bars.append(`
          <div class="h-bar-col">
            <div class="h-bar inc" style="height:${incH}%" title="Income: रू ${chartData.income[i].toLocaleString('en-IN')}"></div>
            <div class="h-bar exp" style="height:${expH}%" title="Expense: रू ${chartData.expense[i].toLocaleString('en-IN')}"></div>
            <div class="h-bar-lbl">${lbl}</div>
          </div>
        `);
      });

      const setType = function (type, btn) {
        $('#type-tabs button').removeClass('danger teal').addClass('ghost');
        if (type === 'debit') $(btn).removeClass('ghost').addClass('danger');
        if (type === 'credit') $(btn).removeClass('ghost').addClass('teal');
        $('#ql-type').val(type);
      };

      const expBtn = document.getElementById('tab-exp');
      if (expBtn) setType('debit', expBtn);

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
