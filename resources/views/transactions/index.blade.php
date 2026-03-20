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
      <div class="h-page-sub">Save every debit/credit, keep balances synced, and manage categories without leaving the page.</div>
    </div>
    @can('manage transactions')
      <div class="h-page-actions">
        <button type="button" class="h-btn primary" data-modal-open="transactions-create-modal">
          <i class="ri-add-circle-line"></i>
          Quick Add
        </button>
        <button type="button" class="h-btn ghost" data-modal-open="transactions-categories-modal">
          <i class="ri-price-tag-3-line"></i>
          Categories
        </button>
      </div>
    @endcan
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
      <div class="h-stat-label">Net Balance</div>
      <div class="h-stat-val {{ $summary['net'] >= 0 ? 'teal' : '' }}">NPR {{ number_format($summary['net'], 2) }}</div>
    </div>
  </div>

  <div class="h-card">
    <div class="h-card-head h-card-toolbar">
      <div>
        <div class="h-card-title">Transaction Directory</div>
        <div class="h-card-meta">Server-side table with search, pagination, and sticky row actions.</div>
      </div>
      <span class="h-badge muted">{{ number_format($categories->count()) }} categories</span>
    </div>
    <div class="h-card-body" style="overflow:auto;">
      <table
        class="table table-sm align-middle h-table-sticky-actions"
        data-h-datatable
        data-endpoint="{{ route('ui.datatables.transactions') }}"
        data-page-length="10"
        data-length-menu="10,20,50,100"
        data-order-col="0"
        data-order-dir="desc"
        data-empty-text="No transactions found"
      >
        <thead>
          <tr>
            <th data-col="id">ID</th>
            <th data-col="transaction_date">Date</th>
            <th data-col="type">Type</th>
            <th data-col="title">Title</th>
            <th data-col="category_name">Category</th>
            <th data-col="account_name">Account</th>
            <th data-col="amount" class="text-end">Amount</th>
            <th data-col="actions" class="h-col-actions" data-orderable="false" data-searchable="false">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
@endsection

@section('modals')
  @can('manage transactions')
    <div class="h-modal-overlay" id="transactions-create-modal">
      <div class="h-modal" style="max-width:760px;">
        <div class="h-modal-head">
          <div class="h-modal-title">Quick Add Transaction</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="{{ route('transactions.store') }}" class="row g-3" data-spa>
            @csrf
            <div class="col-md-4">
              <label class="h-label" style="display:block;">Type</label>
              <select class="form-select" name="type" data-h-select required>
                <option value="debit">Debit (Expense)</option>
                <option value="credit">Credit (Income)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="h-label" style="display:block;">Amount</label>
              <input class="form-control" type="number" name="amount" step="0.01" min="0.01" required>
            </div>
            <div class="col-md-4">
              <label class="h-label" style="display:block;">Date</label>
              <input class="form-control" type="date" name="transaction_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-6">
              <label class="h-label" style="display:block;">Account</label>
              <select class="form-select" name="account_id" data-h-select>
                <option value="">No account</option>
                @foreach($accounts as $account)
                  <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->currency }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="h-label" style="display:block;">Category</label>
              <select class="form-select" name="category_id" data-h-select>
                <option value="">No category</option>
                @foreach($categories as $category)
                  <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="h-label" style="display:block;">Title</label>
              <input class="form-control" name="title" placeholder="Lunch / Salary / Rent">
            </div>
            <div class="col-12">
              <label class="h-label" style="display:block;">Notes</label>
              <textarea class="form-control" name="notes" rows="3" placeholder="Optional"></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-primary" type="submit" data-busy-text="Saving...">
                <i class="fa-solid fa-plus me-2"></i>
                Save Transaction
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="h-modal-overlay" id="transactions-categories-modal">
      <div class="h-modal" style="max-width:980px;">
        <div class="h-modal-head">
          <div class="h-modal-title">Manage Categories</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="{{ route('transactions.categories.store') }}" class="row g-2 mb-3" data-spa>
            @csrf
            <div class="col-md-5">
              <input class="form-control" name="name" placeholder="Category name" required>
            </div>
            <div class="col-md-5">
              <input class="form-control" name="slug" placeholder="Slug (optional)">
            </div>
            <div class="col-md-2 d-grid">
              <button class="h-btn primary" type="submit">
                <i class="fa-solid fa-plus"></i>
                Add
              </button>
            </div>
          </form>

          <div style="display:grid;gap:8px;max-height:420px;overflow:auto;padding-right:4px;">
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
                    <div class="col-md-5">
                      <label class="h-label">Name</label>
                      <input class="form-control" name="name" value="{{ $category->name }}" {{ $owned ? '' : 'readonly' }}>
                    </div>
                    <div class="col-md-4">
                      <label class="h-label">Slug</label>
                      <input class="form-control" name="slug" value="{{ $category->slug }}" {{ $owned ? '' : 'readonly' }}>
                    </div>
                    <div class="col-md-3 d-flex gap-2 justify-content-end">
                      @if($owned && auth()->user()->can('manage transactions'))
                        <button class="h-btn ghost" type="submit" title="Save category">
                          <i class="fa-solid fa-floppy-disk"></i>
                        </button>
                        <button
                          class="h-btn danger"
                          type="submit"
                          form="category-delete-{{ $category->id }}"
                          data-confirm="true"
                          data-confirm-title="Delete category?"
                          data-confirm-text="Existing transactions keep working but the category link will be removed."
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
              @if($owned && auth()->user()->can('manage transactions'))
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
  @endcan

  @can('manage transactions')
    <div class="h-modal-overlay" id="transactions-edit-modal">
      <div class="h-modal" style="max-width:760px;">
        <div class="h-modal-head">
          <div class="h-modal-title" id="h-transaction-form-title">Edit Transaction</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="#" id="h-transaction-form" data-spa data-update-template="{{ url('/transactions/__ID__') }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Type</label>
                <select class="form-select" name="type" id="h-transaction-type" data-h-select required>
                  <option value="debit">Debit</option>
                  <option value="credit">Credit</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Amount</label>
                <input class="form-control" type="number" name="amount" id="h-transaction-amount" step="0.01" min="0.01" required>
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Date</label>
                <input class="form-control" type="date" name="transaction_date" id="h-transaction-date" required>
              </div>
              <div class="col-md-6">
                <label class="h-label" style="display:block;">Account</label>
                <select class="form-select" name="account_id" id="h-transaction-account" data-h-select>
                  <option value="">No account</option>
                  @foreach($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->currency }})</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="h-label" style="display:block;">Category</label>
                <select class="form-select" name="category_id" id="h-transaction-category" data-h-select>
                  <option value="">No category</option>
                  @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-12">
                <label class="h-label" style="display:block;">Title</label>
                <input class="form-control" name="title" id="h-transaction-title" placeholder="Lunch / Salary / Rent">
              </div>
              <div class="col-12">
                <label class="h-label" style="display:block;">Notes</label>
                <textarea class="form-control" name="notes" id="h-transaction-notes" rows="4" placeholder="Optional"></textarea>
              </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
              <button type="submit" class="btn btn-primary" data-busy-text="Saving...">
                <i class="fa-solid fa-floppy-disk me-2"></i>
                Update Transaction
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endcan
@endsection

@section('scripts')
  @can('manage transactions')
    <script>
      (function () {
        const form = document.getElementById('h-transaction-form');
        const title = document.getElementById('h-transaction-form-title');
        const typeInput = document.getElementById('h-transaction-type');
        const amountInput = document.getElementById('h-transaction-amount');
        const dateInput = document.getElementById('h-transaction-date');
        const accountInput = document.getElementById('h-transaction-account');
        const categoryInput = document.getElementById('h-transaction-category');
        const textInput = document.getElementById('h-transaction-title');
        const notesInput = document.getElementById('h-transaction-notes');

        if (!form || !title || !typeInput || !amountInput || !dateInput || !accountInput || !categoryInput || !textInput || !notesInput) {
          return;
        }

        const decodePayload = (value) => {
          try {
            return JSON.parse(window.atob(String(value || '')));
          } catch (error) {
            return null;
          }
        };

        const syncSelects = () => {
          if (window.HSelect && typeof window.HSelect.init === 'function') {
            window.HSelect.init(form);
          }
        };

        document.addEventListener('click', (event) => {
          const trigger = event.target.closest('[data-transaction-edit]');
          if (!trigger) return;

          const payload = decodePayload(trigger.getAttribute('data-transaction-edit'));
          if (!payload || !payload.id) return;

          form.action = String(form.dataset.updateTemplate || '').replace('__ID__', String(payload.id));
          title.textContent = 'Edit Transaction #' + payload.id;

          typeInput.value = payload.type || 'debit';
          amountInput.value = payload.amount ?? '';
          dateInput.value = payload.transaction_date || '';
          accountInput.value = payload.account_id > 0 ? String(payload.account_id) : '';
          categoryInput.value = payload.category_id > 0 ? String(payload.category_id) : '';
          textInput.value = payload.title || '';
          notesInput.value = payload.notes || '';

          typeInput.dispatchEvent(new Event('change', { bubbles: true }));
          accountInput.dispatchEvent(new Event('change', { bubbles: true }));
          categoryInput.dispatchEvent(new Event('change', { bubbles: true }));
          syncSelects();
          if (window.HModal) window.HModal.open('transactions-edit-modal');
        });
      })();
    </script>
  @endcan
@endsection
