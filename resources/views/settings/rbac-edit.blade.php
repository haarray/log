@extends('layouts.app')

@section('title', 'Edit Role')
@section('page_title', 'Edit Role')

@section('topbar_extra')
  <span class="h-live-badge">
    <i class="fa-solid fa-pen-to-square"></i>
    RBAC
  </span>
@endsection

@section('content')
@php
  $isProtected = in_array((string) $role->name, $protectedRoleNames ?? [], true);
  $selectedPermissions = $role->permissions->pluck('name')->values()->all();
  $deleteDisabled = $isProtected || (($assignedUsers ?? 0) > 0);
@endphp

<div class="hl-docs hl-settings">
  <div class="doc-head">
    <div>
      <div class="doc-title">Edit Role: {{ strtoupper((string) $role->name) }}</div>
      <div class="doc-sub">Update role details and permissions. Delete is blocked for protected/assigned roles.</div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('settings.rbac') }}" data-spa class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-2"></i>
        Back to Roles
      </a>
      <a href="{{ route('settings.rbac.create') }}" data-spa class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus me-2"></i>
        Create Role
      </a>
    </div>
  </div>

  @if(!$hasSpatiePermissions)
    <div class="alert alert-warning" role="alert">
      <i class="fa-solid fa-triangle-exclamation me-2"></i>
      Spatie permission tables are not ready. Run migrations first.
    </div>
  @else
    <div class="h-card-soft mb-3">
      <div class="head h-split">
        <div>
          <div style="font-family:var(--fd);font-size:16px;font-weight:700;">Role Form</div>
          <div class="h-muted" style="font-size:13px;">
            Users assigned: <strong>{{ (int) ($assignedUsers ?? 0) }}</strong>
            @if($isProtected)
              <span class="ms-2">• Protected role</span>
            @endif
          </div>
        </div>

        <form
          method="POST"
          action="{{ route('settings.roles.delete', $role) }}"
          data-confirm="true"
          data-confirm-title="Delete role?"
          data-confirm-text="Role will be removed permanently if no user is assigned."
        >
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-outline-danger btn-sm" @disabled($deleteDisabled)>
            <i class="fa-solid fa-trash me-2"></i>
            Delete Role
          </button>
        </form>
      </div>
      <div class="body">
        <form method="POST" action="{{ route('settings.roles.update', $role) }}" data-spa>
          @csrf
          @method('PUT')

          <div class="row g-3">
            <div class="col-md-4">
              <label class="h-label" style="display:block;">Role Name</label>
              <input
                type="text"
                name="name"
                class="form-control"
                value="{{ old('name', $role->name) }}"
                placeholder="e.g. auditor"
                @disabled($isProtected)
                required
              >
              @if($isProtected)
                <div class="h-muted mt-1" style="font-size:11px;">Protected role names cannot be renamed.</div>
              @endif
            </div>
            <div class="col-md-8">
              <label class="h-label" style="display:block;">Direct Permissions</label>
              <select name="permissions[]" class="form-select" data-h-select multiple>
                @foreach($permissionOptions as $permissionName)
                  <option
                    value="{{ $permissionName }}"
                    @selected(in_array($permissionName, (array) old('permissions', $selectedPermissions), true))
                  >{{ $permissionName }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="h-note mt-3">
            <div class="h-muted" style="font-size:12px;margin-bottom:8px;">Route permission map for module-level roles</div>
            <div class="h-route-permission-grid">
              @foreach($accessModules as $module)
                <div class="h-route-permission-item">
                  <div class="h-route-permission-title">{{ $module['label'] }}</div>
                  <div class="h-muted" style="font-size:11px;">
                    <code>{{ $module['view_permission'] }}</code><br>
                    <code>{{ $module['manage_permission'] }}</code>
                  </div>
                </div>
              @endforeach
            </div>
          </div>

          <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary" data-busy-text="Saving...">
              <i class="fa-solid fa-floppy-disk me-2"></i>
              Update Role
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif
</div>
@endsection
