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

  <div class="h-grid-main" style="grid-template-columns: 1fr 1.7fr;">
    <div style="display:grid;gap:16px;">
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
          <div class="h-card-title">Categories (CRUD)</div>
        </div>
        <div class="h-card-body">
          <form method="POST" action="{{ route('transactions.categories.store') }}" class="row g-2 mb-3" data-spa>
            @csrf
            <div class="col-md-5">
              <input class="h-input" name="name" placeholder="Category name" required>
            </div>
            <div class="col-md-4">
              <input class="h-input" name="slug" placeholder="Slug (optional)">
            </div>
            <div class="col-md-3 d-grid">
              <button class="h-btn ghost" type="submit">
                <i class="fa-solid fa-plus"></i>
                Add
              </button>
            </div>
          </form>

          <div style="display:grid;gap:8px;max-height:320px;overflow:auto;">
            @forelse($categories as $category)
              @php
                $owned = (int) ($category->user_id ?? 0) === (int) auth()->id();
              @endphp
              <form
                id="category-update-{{ $category->id }}"
                method="POST"
                action="{{ route('transactions.categories.update', $category) }}"
                class="h-card"
                style="background:rgba(15,23,42,.35);"
                data-spa
              >
                @csrf
                @method('PUT')
                <div class="h-card-body">
                  <div class="row g-2 align-items-end">
                    <div class="col-4">
                      <label class="h-label">Name</label>
                      <input class="h-input" name="name" value="{{ $category->name }}" {{ $owned ? '' : 'readonly' }}>
                    </div>
                    <div class="col-3">
                      <label class="h-label">Slug</label>
                      <input class="h-input" name="slug" value="{{ $category->slug }}" {{ $owned ? '' : 'readonly' }}>
                    </div>
                    <div class="col-3">
                      <label class="h-label">Icon</label>
                      <input class="h-input" name="icon" value="{{ $category->icon }}" {{ $owned ? '' : 'readonly' }}>
                    </div>
                    <div class="col-2 d-flex gap-2 justify-content-end">
                      @if($owned)
                        <button class="h-btn ghost" type="submit" title="Save category">
                          <i class="fa-solid fa-floppy-disk"></i>
                        </button>
                        <button
                          class="h-btn danger"
                          type="submit"
                          form="category-delete-{{ $category->id }}"
                          data-confirm="true"
                          data-confirm-title="Delete category?"
                          data-confirm-text="Existing transactions keep working but category link will be removed."
                          title="Delete category"
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      @else
                        <span class="h-badge muted">SYSTEM</span>
                      @endif
                    </div>
                  </div>
                </div>
              </form>
              @if($owned)
                <form id="category-delete-{{ $category->id }}" method="POST" action="{{ route('transactions.categories.delete', $category) }}" data-spa style="display:none;">
                  @csrf
                  @method('DELETE')
                </form>
              @endif
            @empty
              <div class="h-alert info">No categories available yet.</div>
            @endforelse
          </div>
        </div>
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
                  <div class="d-inline-flex gap-2">
                    <button class="h-btn ghost" type="button" data-bs-toggle="modal" data-bs-target="#tx-edit-{{ $tx->id }}" title="Edit">
                      <i class="fa-solid fa-pen"></i>
                    </button>
                    <form method="POST" action="{{ route('transactions.delete', $tx) }}" data-spa>
                      @csrf
                      @method('DELETE')
                      <button
                        type="submit"
                        class="h-btn danger"
                        data-confirm="true"
                        data-confirm-title="Delete transaction?"
                        data-confirm-text="This will reverse related account balance too."
                        title="Delete"
                      >
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  </div>
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

  @foreach($transactions as $tx)
    <div class="modal fade" id="tx-edit-{{ $tx->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="background:var(--card);border:1px solid var(--line);color:var(--t2);">
          <div class="modal-header" style="border-color:var(--line);">
            <h5 class="modal-title">Edit Transaction #{{ $tx->id }}</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="{{ route('transactions.update', $tx) }}" data-spa>
            @csrf
            @method('PUT')
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="h-label">Type</label>
                  <select class="h-input" name="type" required>
                    <option value="debit" @selected($tx->type === 'debit')>Debit</option>
                    <option value="credit" @selected($tx->type === 'credit')>Credit</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="h-label">Amount</label>
                  <input class="h-input" type="number" name="amount" step="0.01" min="0.01" value="{{ $tx->amount }}" required>
                </div>
                <div class="col-md-4">
                  <label class="h-label">Date</label>
                  <input class="h-input" type="date" name="transaction_date" value="{{ optional($tx->transaction_date)->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-6">
                  <label class="h-label">Account</label>
                  <select class="h-input" name="account_id">
                    <option value="">No account</option>
                    @foreach($accounts as $account)
                      <option value="{{ $account->id }}" @selected((int) $tx->account_id === (int) $account->id)>
                        {{ $account->name }} ({{ $account->currency }})
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="h-label">Category</label>
                  <select class="h-input" name="category_id">
                    <option value="">No category</option>
                    @foreach($categories as $category)
                      <option value="{{ $category->id }}" @selected((int) $tx->category_id === (int) $category->id)>
                        {{ $category->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-12">
                  <label class="h-label">Title</label>
                  <input class="h-input" name="title" value="{{ $tx->title }}" placeholder="Lunch / Salary / Rent">
                </div>
                <div class="col-12">
                  <label class="h-label">Notes</label>
                  <textarea class="h-input" name="notes" rows="3" placeholder="Optional">{{ $tx->notes }}</textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer" style="border-color:var(--line);">
              <button type="button" class="h-btn ghost" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="h-btn primary">
                <i class="fa-solid fa-floppy-disk"></i>
                Update Transaction
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endforeach
@endsection
