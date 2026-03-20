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
      <div class="h-page-sub">Track balances from bank, cash, eSewa, Khalti, and every working wallet in one place.</div>
    </div>
    @can('manage accounts')
      <button type="button" class="h-btn primary" id="h-account-create-open">
        <i class="fa-solid fa-plus"></i>
        Create Account
      </button>
    @endcan
  </div>

  <div class="h-grid-3" style="margin-bottom:16px;">
    <div class="h-stat-card">
      <div class="h-stat-label">Tracked Accounts</div>
      <div class="h-stat-val">{{ number_format($accounts->count()) }}</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-label">Active Accounts</div>
      <div class="h-stat-val teal">{{ number_format($accounts->where('is_active', true)->count()) }}</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-label">Inactive Accounts</div>
      <div class="h-stat-val">{{ number_format($accounts->where('is_active', false)->count()) }}</div>
    </div>
  </div>

  <div class="h-card">
    <div class="h-card-head">
      <div>
        <div class="h-card-title">Account Directory</div>
        <div class="h-card-meta">List-first flow with quick edit/delete actions.</div>
      </div>
    </div>
    <div class="h-card-body" style="overflow:auto;">
      <table
        class="table table-sm align-middle h-table-sticky-actions"
        data-h-datatable
        data-endpoint="{{ route('ui.datatables.accounts') }}"
        data-page-length="10"
        data-length-menu="10,20,50,100"
        data-order-col="0"
        data-order-dir="desc"
        data-empty-text="No accounts found"
      >
        <thead>
          <tr>
            <th data-col="id">ID</th>
            <th data-col="name">Name</th>
            <th data-col="institution">Institution</th>
            <th data-col="type">Type</th>
            <th data-col="balance" class="text-end">Balance</th>
            <th data-col="is_active">Status</th>
            <th data-col="updated_at">Updated</th>
            <th data-col="actions" class="h-col-actions" data-orderable="false" data-searchable="false">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
@endsection

@section('modals')
  @can('manage accounts')
    <div class="h-modal-overlay" id="accounts-form-modal">
      <div class="h-modal" style="max-width:640px;">
        <div class="h-modal-head">
          <div class="h-modal-title" id="h-account-form-title">Create Account</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form
            method="POST"
            action="{{ route('accounts.store') }}"
            id="h-account-form"
            data-spa
            data-store-action="{{ route('accounts.store') }}"
            data-update-template="{{ url('/accounts/__ID__') }}"
          >
            @csrf
            <span id="h-account-method-holder"></span>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="h-label" style="display:block;">Account Name</label>
                <input type="text" name="name" id="h-account-name" class="form-control" placeholder="Nabil Savings" required>
              </div>
              <div class="col-md-6">
                <label class="h-label" style="display:block;">Institution</label>
                <input type="text" name="institution" id="h-account-institution" class="form-control" placeholder="Nabil Bank">
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Type</label>
                <select name="type" id="h-account-type" class="form-select" data-h-select>
                  <option value="bank">Bank</option>
                  <option value="cash">Cash</option>
                  <option value="wallet">Wallet</option>
                  <option value="esewa">eSewa</option>
                  <option value="khalti">Khalti</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Currency</label>
                <input type="text" name="currency" id="h-account-currency" class="form-control" value="NPR" maxlength="8">
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Sort Order</label>
                <input type="number" name="sort_order" id="h-account-sort" class="form-control" value="0" min="0" max="9999">
              </div>
              <div class="col-md-8">
                <label class="h-label" style="display:block;">Balance</label>
                <input type="number" name="balance" id="h-account-balance" class="form-control" value="0" step="0.01" required>
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Status</label>
                <label class="form-check form-switch mt-2" style="display:flex;align-items:center;gap:10px;">
                  <input type="hidden" name="is_active" value="0">
                  <input type="checkbox" name="is_active" value="1" id="h-account-active" class="form-check-input" checked>
                  <span style="color:var(--t2);font-size:13px;">Enabled</span>
                </label>
              </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <button type="submit" class="btn btn-primary" id="h-account-submit-btn" data-busy-text="Saving...">
                <i class="fa-solid fa-floppy-disk me-2"></i>
                Save Account
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endcan
@endsection

@section('scripts')
  @can('manage accounts')
    <script>
      (function () {
        const openButton = document.getElementById('h-account-create-open');
        const form = document.getElementById('h-account-form');
        const title = document.getElementById('h-account-form-title');
        const methodHolder = document.getElementById('h-account-method-holder');
        const submitButton = document.getElementById('h-account-submit-btn');

        const nameInput = document.getElementById('h-account-name');
        const institutionInput = document.getElementById('h-account-institution');
        const typeInput = document.getElementById('h-account-type');
        const currencyInput = document.getElementById('h-account-currency');
        const sortInput = document.getElementById('h-account-sort');
        const balanceInput = document.getElementById('h-account-balance');
        const activeInput = document.getElementById('h-account-active');

        if (!form || !title || !methodHolder || !submitButton || !nameInput || !typeInput || !currencyInput || !sortInput || !balanceInput || !activeInput) {
          return;
        }

        const decodePayload = (value) => {
          try {
            return JSON.parse(window.atob(String(value || '')));
          } catch (error) {
            return null;
          }
        };

        const syncSelect = () => {
          if (window.HSelect && typeof window.HSelect.init === 'function') {
            window.HSelect.init(form);
          }
        };

        const openCreate = () => {
          form.reset();
          form.action = form.dataset.storeAction || form.action;
          methodHolder.innerHTML = '';
          title.textContent = 'Create Account';
          submitButton.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save Account';
          currencyInput.value = 'NPR';
          sortInput.value = '0';
          balanceInput.value = '0';
          activeInput.checked = true;
          typeInput.dispatchEvent(new Event('change', { bubbles: true }));
          syncSelect();
          if (window.HModal) window.HModal.open('accounts-form-modal');
        };

        const openEdit = (payload) => {
          if (!payload || !payload.id) return;

          form.action = String(form.dataset.updateTemplate || '').replace('__ID__', String(payload.id));
          methodHolder.innerHTML = '<input type="hidden" name="_method" value="PUT">';
          title.textContent = 'Edit Account #' + payload.id;
          submitButton.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Update Account';

          nameInput.value = payload.name || '';
          institutionInput.value = payload.institution || '';
          typeInput.value = payload.type || 'bank';
          currencyInput.value = payload.currency || 'NPR';
          sortInput.value = payload.sort_order ?? 0;
          balanceInput.value = payload.balance ?? 0;
          activeInput.checked = Boolean(payload.is_active);

          typeInput.dispatchEvent(new Event('change', { bubbles: true }));
          syncSelect();
          if (window.HModal) window.HModal.open('accounts-form-modal');
        };

        if (openButton) {
          openButton.addEventListener('click', openCreate);
        }

        document.addEventListener('click', (event) => {
          const trigger = event.target.closest('[data-account-edit]');
          if (!trigger) return;
          openEdit(decodePayload(trigger.getAttribute('data-account-edit')));
        });
      })();
    </script>
  @endcan
@endsection
