@extends('layouts.app')

@section('title', 'Transactions')
@section('page_title', 'Transactions')

@section('topbar_extra')
  <div class="h-live-badge">
    <span class="h-pulse"></span>
    Net NPR {{ number_format($summary['net'], 2) }}
  </div>
@endsection

@section('content')
  <div class="h-page-header">
    <div>
      <div class="h-page-title">Expense & Income Logs</div>
      <div class="h-page-sub">Save every debit/credit and keep balances synced.</div>
    </div>
  </div>

  <div class="h-grid-3" style="margin-bottom:16px;">
    <div class="h-stat-card">
      <div class="h-stat-label">Total Credit</div>
      <div class="h-stat-val teal">NPR {{ number_format($summary['credit'], 2) }}</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-label">Total Debit</div>
      <div class="h-stat-val">NPR {{ number_format($summary['debit'], 2) }}</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-label">Net</div>
      <div class="h-stat-val {{ $summary['net'] >= 0 ? 'teal' : '' }}">NPR {{ number_format($summary['net'], 2) }}</div>
    </div>
  </div>

  <div class="h-grid-main" style="grid-template-columns: 1fr 1.6fr;">
    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Quick Add</div>
      </div>
      <div class="h-card-body">
        <form method="POST" action="{{ route('transactions.store') }}" class="row g-3" data-spa>
          @csrf
          <div class="col-6">
            <label class="h-label">Type</label>
            <select class="h-input" name="type" required>
              <option value="debit">Debit (Expense)</option>
              <option value="credit">Credit (Income)</option>
            </select>
          </div>
          <div class="col-6">
            <label class="h-label">Amount</label>
            <input class="h-input" type="number" name="amount" step="0.01" min="0.01" required>
          </div>
          <div class="col-12">
            <label class="h-label">Account</label>
            <select class="h-input" name="account_id">
              <option value="">No account</option>
              @foreach($accounts as $account)
                <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->currency }})</option>
              @endforeach
            </select>
          </div>
          <div class="col-12">
            <label class="h-label">Category</label>
            <select class="h-input" name="category_id">
              <option value="">No category</option>
              @foreach($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12">
            <label class="h-label">Title</label>
            <input class="h-input" name="title" placeholder="Lunch / Salary / Rent">
          </div>
          <div class="col-12">
            <label class="h-label">Date</label>
            <input class="h-input" type="date" name="transaction_date" value="{{ now()->toDateString() }}" required>
          </div>
          <div class="col-12">
            <label class="h-label">Notes</label>
            <textarea class="h-input" name="notes" rows="3" placeholder="Optional"></textarea>
          </div>
          <div class="col-12">
            <button class="h-btn primary" type="submit">
              <i class="fa-solid fa-plus"></i>
              Save Transaction
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Recent Transactions</div>
      </div>
      <div class="h-card-body" style="overflow:auto;">
        <table class="table align-middle" style="color:var(--t2);">
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Title</th>
              <th>Category</th>
              <th>Account</th>
              <th class="text-end">Amount</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($transactions as $tx)
              <tr>
                <td>{{ optional($tx->transaction_date)->format('Y-m-d') }}</td>
                <td>
                  <span class="h-badge {{ $tx->type === 'credit' ? 'green' : 'gold' }}">{{ strtoupper($tx->type) }}</span>
                </td>
                <td>{{ $tx->title ?: '-' }}<br><small style="color:var(--t3);">{{ $tx->notes }}</small></td>
                <td>{{ optional($tx->category)->name ?: '-' }}</td>
                <td>{{ optional($tx->account)->name ?: '-' }}</td>
                <td class="text-end {{ $tx->type === 'credit' ? 'text-success' : 'text-warning' }}">
                  {{ $tx->type === 'credit' ? '+' : '-' }} NPR {{ number_format($tx->amount, 2) }}
                </td>
                <td class="text-end">
                  <form method="POST" action="{{ route('transactions.delete', $tx) }}" data-spa>
                    @csrf
                    @method('DELETE')
                    <button
                      type="submit"
                      class="h-btn danger"
                      data-confirm="true"
                      data-confirm-title="Delete transaction?"
                      data-confirm-text="This will reverse related account balance too."
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center" style="color:var(--t3);">No transactions yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>

        {{ $transactions->links() }}
      </div>
    </div>
  </div>
@endsection
