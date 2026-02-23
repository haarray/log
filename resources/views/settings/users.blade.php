@extends('layouts.app')

@section('title', 'Users')
@section('page_title', 'Users')

@section('topbar_extra')
  <span class="h-live-badge">
    <i class="fa-solid fa-users"></i>
    User Management
  </span>
@endsection

@section('content')
<div class="hl-docs hl-settings">
  <div class="doc-head">
    <div>
      <div class="doc-title">Users</div>
      <div class="doc-sub">Server-side DataTable with focused modals: profile, notification channels, and role/permission access.</div>
    </div>
    @if($canManageUsers)
      <span class="h-pill gold">Manage Users</span>
    @else
      <span class="h-pill teal">Read Only</span>
    @endif
  </div>

  <div class="h-card-soft mb-3">
    <div class="head h-split">
      <div>
        <div style="font-family:var(--fd);font-size:16px;font-weight:700;">User Directory</div>
        <div class="h-muted" style="font-size:13px;">Actions: Edit profile, manage notifications, manage role/access, delete.</div>
      </div>
      @if($canManageUsers)
        <div class="d-flex gap-2 flex-wrap justify-content-end">
          <button type="button" class="btn btn-primary btn-sm" id="h-user-create-open">
            <i class="fa-solid fa-user-plus me-2"></i>
            Create User
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-modal-open="settings-users-import-modal">
            <i class="fa-solid fa-file-import me-2"></i>
            Import
          </button>
          <a href="{{ route('settings.users.export') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-file-export me-2"></i>
            Export
          </a>
        </div>
      @endif
    </div>

    <div class="body">
      <div class="table-responsive">
        <table
          class="table table-sm table-striped table-hover align-middle h-table-sticky-actions"
          data-h-datatable
          data-endpoint="{{ route('ui.datatables.users') }}"
          data-page-length="10"
          data-length-menu="10,20,50,100"
          data-order-col="0"
          data-order-dir="desc"
          data-empty-text="Empty"
        >
          <thead>
            <tr>
              <th data-col="id">ID</th>
              <th data-col="name">Name</th>
              <th data-col="email">Email</th>
              <th data-col="role">Role</th>
              <th data-col="channels">Channels</th>
              <th data-col="created_at">Joined</th>
              <th data-col="actions" class="h-col-actions" data-orderable="false" data-searchable="false">Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('modals')
  @if($canManageUsers)
    <div class="h-modal-overlay" id="settings-users-form-modal">
      <div class="h-modal" style="max-width:640px;">
        <div class="h-modal-head">
          <div class="h-modal-title" id="h-user-form-title">Create User</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form
            method="POST"
            action="{{ route('settings.users.store') }}"
            id="h-user-form"
            data-spa
            data-store-action="{{ route('settings.users.store') }}"
            data-update-template="{{ route('settings.users.profile', ['user' => '__ID__']) }}"
          >
            @csrf
            <span id="h-user-method-holder"></span>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="h-label" style="display:block;">Name</label>
                <input type="text" name="name" id="h-user-name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="h-label" style="display:block;">Email</label>
                <input type="email" name="email" id="h-user-email" class="form-control" required>
              </div>
              <div class="col-md-12">
                <label class="h-label" style="display:block;">Password</label>
                <input type="password" name="password" id="h-user-password" class="form-control" minlength="8" required>
                <div class="h-muted mt-1" style="font-size:11px;" id="h-user-password-hint">Required for new users.</div>
              </div>
            </div>

            <div class="h-note mt-3">Role/access and notification channels are managed in dedicated modals from the Actions column.</div>

            <div class="d-flex justify-content-end mt-3">
              <button type="submit" class="btn btn-primary" id="h-user-submit-btn" data-busy-text="Saving...">
                <i class="fa-solid fa-floppy-disk me-2"></i>
                Save User
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="h-modal-overlay" id="settings-users-access-modal">
      <div class="h-modal" style="max-width:1040px;">
        <div class="h-modal-head">
          <div class="h-modal-title" id="h-user-access-title">Role & Permissions</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="#" id="h-user-access-form" data-spa data-access-template="{{ route('settings.users.access', ['user' => '__ID__']) }}">
            @csrf

            <div class="row g-3">
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Role</label>
                <select name="role" id="h-access-role" class="form-select" data-h-select required>
                  @foreach($roles as $roleName)
                    <option value="{{ $roleName }}">{{ strtoupper($roleName) }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-8">
                <label class="h-label" style="display:block;">Direct Permissions</label>
                <select name="permissions[]" id="h-access-permissions" class="form-select" data-h-select multiple>
                  @foreach($permissionOptions as $permissionName)
                    <option value="{{ $permissionName }}">{{ $permissionName }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="h-card-soft mt-3">
              <div class="head">
                <div style="font-family:var(--fd);font-size:15px;font-weight:700;">Module Access Matrix</div>
                <div class="h-muted" style="font-size:12px;">Set access level for each module.</div>
              </div>
              <div class="body">
                <div class="h-user-access-grid">
                  @foreach($accessModules as $moduleKey => $module)
                    <div class="h-user-access-item" data-access-module-key="{{ $moduleKey }}">
                      <div class="h-user-access-copy">
                        <div style="font-weight:700;">{{ $module['label'] }}</div>
                        <div class="h-muted" style="font-size:11px;"><code>{{ $module['view_permission'] }}</code> / <code>{{ $module['manage_permission'] }}</code></div>
                      </div>
                      <div class="h-radio-inline">
                        <label class="h-radio-pill"><input type="radio" name="module_access[{{ $moduleKey }}]" value="none" checked><span>None</span></label>
                        <label class="h-radio-pill"><input type="radio" name="module_access[{{ $moduleKey }}]" value="view"><span>View</span></label>
                        <label class="h-radio-pill"><input type="radio" name="module_access[{{ $moduleKey }}]" value="manage"><span>Manage</span></label>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <button type="submit" class="btn btn-primary" data-busy-text="Saving...">
                <i class="fa-solid fa-user-shield me-2"></i>
                Save Role & Access
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="h-modal-overlay" id="settings-users-notify-modal">
      <div class="h-modal" style="max-width:640px;">
        <div class="h-modal-head">
          <div class="h-modal-title" id="h-user-notify-title">Notification Channels</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="#" id="h-user-notify-form" data-spa data-notify-template="{{ route('settings.users.notifications', ['user' => '__ID__']) }}">
            @csrf

            <div class="mb-3">
              <label class="h-label" style="display:block;">Telegram Chat ID</label>
              <input type="text" name="telegram_chat_id" id="h-notify-telegram" class="form-control" placeholder="optional">
            </div>

            <div class="h-user-channel-grid">
              <div>
                <label class="h-label" style="display:block;margin-bottom:6px;">In-app Notifications</label>
                <label class="h-switch">
                  <input type="hidden" name="receive_in_app_notifications" value="0">
                  <input type="checkbox" name="receive_in_app_notifications" value="1" id="h-notify-inapp">
                  <span class="track"><span class="thumb"></span></span>
                  <span class="h-switch-text">Enabled</span>
                </label>
              </div>
              <div>
                <label class="h-label" style="display:block;margin-bottom:6px;">Telegram Notifications</label>
                <label class="h-switch">
                  <input type="hidden" name="receive_telegram_notifications" value="0">
                  <input type="checkbox" name="receive_telegram_notifications" value="1" id="h-notify-telegram-toggle">
                  <span class="track"><span class="thumb"></span></span>
                  <span class="h-switch-text">Enabled</span>
                </label>
              </div>
              <div>
                <label class="h-label" style="display:block;margin-bottom:6px;">Browser Notifications</label>
                <label class="h-switch">
                  <input type="hidden" name="browser_notifications_enabled" value="0">
                  <input type="checkbox" name="browser_notifications_enabled" value="1" id="h-notify-browser">
                  <span class="track"><span class="thumb"></span></span>
                  <span class="h-switch-text">Enabled</span>
                </label>
              </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <button type="submit" class="btn btn-primary" data-busy-text="Saving...">
                <i class="fa-solid fa-bell me-2"></i>
                Save Notification Channels
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="h-modal-overlay" id="settings-users-import-modal">
      <div class="h-modal" style="max-width:540px;">
        <div class="h-modal-head">
          <div class="h-modal-title">Import Users</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="{{ route('settings.users.import') }}" enctype="multipart/form-data" data-spa>
            @csrf
            <div class="mb-3">
              <label class="h-label" style="display:block;">Import File (xlsx/xls/csv)</label>
              <input type="file" name="import_file" class="form-control" accept=".xlsx,.xls,.csv" required>
            </div>
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-outline-secondary" data-modal-close="settings-users-import-modal">Cancel</button>
              <button type="submit" class="btn btn-primary" data-busy-text="Importing...">
                <i class="fa-solid fa-file-import me-2"></i>
                Import Users
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif
@endsection

@section('scripts')
<script>
(function () {
  const canManageUsers = @json($canManageUsers);
  if (!canManageUsers) return;

  const users = @json($userPayload);

  const userForm = document.getElementById('h-user-form');
  const userFormTitle = document.getElementById('h-user-form-title');
  const userMethodHolder = document.getElementById('h-user-method-holder');
  const userSubmitBtn = document.getElementById('h-user-submit-btn');
  const userPasswordHint = document.getElementById('h-user-password-hint');

  const userName = document.getElementById('h-user-name');
  const userEmail = document.getElementById('h-user-email');
  const userPassword = document.getElementById('h-user-password');

  const accessForm = document.getElementById('h-user-access-form');
  const accessTitle = document.getElementById('h-user-access-title');
  const accessRole = document.getElementById('h-access-role');
  const accessPermissions = document.getElementById('h-access-permissions');

  const notifyForm = document.getElementById('h-user-notify-form');
  const notifyTitle = document.getElementById('h-user-notify-title');
  const notifyTelegram = document.getElementById('h-notify-telegram');

  if (!userForm || !userName || !userEmail || !userPassword || !accessForm || !accessRole || !accessPermissions || !notifyForm || !notifyTelegram) {
    return;
  }

  const openModal = (id) => {
    if (window.HModal) {
      window.HModal.open(id);
      return;
    }
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('show');
  };

  const setPermissions = (selected = []) => {
    const selectedSet = new Set((selected || []).map(String));
    Array.from(accessPermissions.options).forEach((option) => {
      option.selected = selectedSet.has(String(option.value));
    });
    accessPermissions.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const setModuleAccess = (payload = {}) => {
    document.querySelectorAll('[data-access-module-key]').forEach((row) => {
      const key = row.getAttribute('data-access-module-key') || '';
      const value = String(payload[key] || 'none');
      row.querySelectorAll('input[type="radio"]').forEach((radio) => {
        radio.checked = String(radio.value) === value;
      });
    });
  };

  const setNotifyToggle = (name, enabled) => {
    const checkbox = notifyForm.querySelector('input[type="checkbox"][name="' + name + '"]');
    if (checkbox) checkbox.checked = Boolean(enabled);
  };

  const openCreate = () => {
    userForm.setAttribute('action', userForm.dataset.storeAction);
    userMethodHolder.innerHTML = '';
    userFormTitle.textContent = 'Create User';
    userSubmitBtn.innerHTML = '<i class="fa-solid fa-user-plus me-2"></i>Create User';

    userName.value = '';
    userEmail.value = '';
    userPassword.value = '';
    userPassword.required = true;
    userPassword.placeholder = '';
    userPasswordHint.textContent = 'Required for new users.';

    openModal('settings-users-form-modal');
  };

  const openEdit = (userId) => {
    const user = users[String(userId)] || users[userId];
    if (!user) return;

    const action = String(userForm.dataset.updateTemplate || '').replace('__ID__', String(user.id));
    userForm.setAttribute('action', action);
    userMethodHolder.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    userFormTitle.textContent = 'Edit User: ' + String(user.name || 'User');
    userSubmitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Update User';

    userName.value = String(user.name || '');
    userEmail.value = String(user.email || '');
    userPassword.value = '';
    userPassword.required = false;
    userPassword.placeholder = 'Leave blank to keep current password';
    userPasswordHint.textContent = 'Optional for edit.';

    openModal('settings-users-form-modal');
  };

  const openAccess = (userId) => {
    const user = users[String(userId)] || users[userId];
    if (!user) return;

    accessForm.setAttribute('action', String(accessForm.dataset.accessTemplate || '').replace('__ID__', String(user.id)));
    accessTitle.textContent = 'Role & Permissions: ' + String(user.name || 'User');

    accessRole.value = String(user.role || 'user');
    accessRole.dispatchEvent(new Event('change', { bubbles: true }));
    setPermissions(Array.isArray(user.permissions) ? user.permissions : []);
    setModuleAccess(user.module_access || {});

    openModal('settings-users-access-modal');
  };

  const openNotify = (userId) => {
    const user = users[String(userId)] || users[userId];
    if (!user) return;

    notifyForm.setAttribute('action', String(notifyForm.dataset.notifyTemplate || '').replace('__ID__', String(user.id)));
    notifyTitle.textContent = 'Notification Channels: ' + String(user.name || 'User');

    notifyTelegram.value = String(user.telegram_chat_id || '');
    setNotifyToggle('receive_in_app_notifications', Boolean(user.receive_in_app_notifications));
    setNotifyToggle('receive_telegram_notifications', Boolean(user.receive_telegram_notifications));
    setNotifyToggle('browser_notifications_enabled', Boolean(user.browser_notifications_enabled));

    openModal('settings-users-notify-modal');
  };

  const createButton = document.getElementById('h-user-create-open');
  if (createButton) createButton.addEventListener('click', openCreate);

  document.addEventListener('click', (event) => {
    const editBtn = event.target.closest('[data-user-edit-id]');
    if (editBtn) {
      event.preventDefault();
      const userId = Number(editBtn.getAttribute('data-user-edit-id') || 0);
      if (userId) openEdit(userId);
      return;
    }

    const accessBtn = event.target.closest('[data-user-access-id]');
    if (accessBtn) {
      event.preventDefault();
      const userId = Number(accessBtn.getAttribute('data-user-access-id') || 0);
      if (userId) openAccess(userId);
      return;
    }

    const notifyBtn = event.target.closest('[data-user-notify-id]');
    if (notifyBtn) {
      event.preventDefault();
      const userId = Number(notifyBtn.getAttribute('data-user-notify-id') || 0);
      if (userId) openNotify(userId);
    }
  });

  const query = new URLSearchParams(window.location.search);
  const accessUserId = Number(query.get('access_user') || 0);
  const notifyUserId = Number(query.get('notify_user') || 0);
  const editUserId = Number(query.get('edit_user') || 0);

  if (accessUserId > 0) {
    setTimeout(() => {
      openAccess(accessUserId);
      if (window.history && typeof window.history.replaceState === 'function') {
        const url = new URL(window.location.href);
        url.searchParams.delete('access_user');
        window.history.replaceState({}, '', url.toString());
      }
    }, 80);
    return;
  }

  if (notifyUserId > 0) {
    setTimeout(() => {
      openNotify(notifyUserId);
      if (window.history && typeof window.history.replaceState === 'function') {
        const url = new URL(window.location.href);
        url.searchParams.delete('notify_user');
        window.history.replaceState({}, '', url.toString());
      }
    }, 80);
    return;
  }

  if (editUserId > 0) {
    setTimeout(() => {
      openEdit(editUserId);
      if (window.history && typeof window.history.replaceState === 'function') {
        const url = new URL(window.location.href);
        url.searchParams.delete('edit_user');
        window.history.replaceState({}, '', url.toString());
      }
    }, 80);
  }
})();
</script>
@endsection
