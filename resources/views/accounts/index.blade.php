@extends('layouts.app')

@section('title', 'Accounts')
@section('page_title', 'Accounts')

@section('topbar_extra')
  <div class="h-live-badge">
    <span class="h-pulse"></span>
    Total NPR {{ number_format($totalBalance, 2) }}
  </div>
@endsection

@section('content')
  <div class="h-page-header">
    <div>
      <div class="h-page-title">Bank & Wallet Accounts</div>
      <div class="h-page-sub">Track balances from all your sources.</div>
    </div>
  </div>

  <div class="h-grid-main" style="grid-template-columns: 1fr 1.4fr;">
    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Create Account</div>
      </div>
      <div class="h-card-body">
        <form method="POST" action="{{ route('accounts.store') }}" class="row g-3" data-spa>
          @csrf
          <div class="col-12">
            <label class="h-label">Name</label>
            <input class="h-input" name="name" required placeholder="Nabil Savings">
          </div>
          <div class="col-12">
            <label class="h-label">Institution</label>
            <input class="h-input" name="institution" placeholder="Nabil Bank">
          </div>
          <div class="col-6">
            <label class="h-label">Type</label>
            <select class="h-input" name="type" required>
              <option value="bank">Bank</option>
              <option value="cash">Cash</option>
              <option value="wallet">Wallet</option>
              <option value="esewa">eSewa</option>
              <option value="khalti">Khalti</option>
            </select>
          </div>
          <div class="col-6">
            <label class="h-label">Currency</label>
            <input class="h-input" name="currency" value="NPR" maxlength="8">
          </div>
          <div class="col-8">
            <label class="h-label">Opening Balance</label>
            <input class="h-input" name="balance" type="number" step="0.01" value="0" required>
          </div>
          <div class="col-4">
            <label class="h-label">Sort</label>
            <input class="h-input" name="sort_order" type="number" value="0">
          </div>
          <div class="col-12">
            <label style="display:flex;align-items:center;gap:8px;color:var(--t2);font-size:12px;">
              <input type="checkbox" name="is_active" value="1" checked style="accent-color:var(--gold);">
              Active
            </label>
          </div>
          <div class="col-12">
            <button class="h-btn primary" type="submit">
              <i class="fa-solid fa-plus"></i>
              Create Account
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Your Accounts</div>
        <div class="h-card-meta">{{ $accounts->count() }} records</div>
      </div>
      <div class="h-card-body" style="display:grid;gap:10px;">
        @forelse($accounts as $account)
          <form id="account-update-{{ $account->id }}" method="POST" action="{{ route('accounts.update', $account) }}" class="h-card" style="background:rgba(15,23,42,.35);" data-spa>
            @csrf
            @method('PUT')
            <div class="h-card-body">
              <div class="row g-2 align-items-end">
                <div class="col-lg-3">
                  <label class="h-label">Name</label>
                  <input class="h-input" name="name" value="{{ $account->name }}" required>
                </div>
                <div class="col-lg-2">
                  <label class="h-label">Institution</label>
                  <input class="h-input" name="institution" value="{{ $account->institution }}">
                </div>
                <div class="col-lg-2">
                  <label class="h-label">Type</label>
                  <input class="h-input" name="type" value="{{ $account->type }}" required>
                </div>
                <div class="col-lg-1">
                  <label class="h-label">CCY</label>
                  <input class="h-input" name="currency" value="{{ $account->currency }}">
                </div>
                <div class="col-lg-2">
                  <label class="h-label">Balance</label>
                  <input class="h-input" name="balance" type="number" step="0.01" value="{{ $account->balance }}" required>
                </div>
                <div class="col-lg-1">
                  <label class="h-label">Active</label>
                  <div>
                    <label style="display:flex;align-items:center;gap:8px;color:var(--t2);font-size:12px;">
                      <input type="checkbox" name="is_active" value="1" style="accent-color:var(--gold);" {{ $account->is_active ? 'checked' : '' }}>
                      Enabled
                    </label>
                  </div>
                </div>
                <div class="col-lg-1 d-flex gap-2">
                  <button class="h-btn ghost" type="submit" title="Save">
                    <i class="fa-solid fa-floppy-disk"></i>
                  </button>
                  <button
                    class="h-btn danger"
                    type="submit"
                    form="account-delete-{{ $account->id }}"
                    data-confirm="true"
                    data-confirm-title="Delete account?"
                    data-confirm-text="This account will be removed."
                  >
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </form>
          <form id="account-delete-{{ $account->id }}" method="POST" action="{{ route('accounts.delete', $account) }}" data-spa style="display:none;">
            @csrf
            @method('DELETE')
          </form>
        @empty
          <div class="h-alert info">No accounts yet. Create your first account from the left form.</div>
        @endforelse
      </div>
    </div>
  </div>
@endsection
