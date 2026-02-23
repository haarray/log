@extends('layouts.app')

@section('title', 'Create Role')
@section('page_title', 'Create Role')

@section('topbar_extra')
  <span class="h-live-badge">
    <i class="fa-solid fa-user-plus"></i>
    RBAC
  </span>
@endsection

@section('content')
<div class="hl-docs hl-settings">
  <div class="doc-head">
    <div>
      <div class="doc-title">Create Role</div>
      <div class="doc-sub">Create a new role and assign direct permissions. Module access is managed from Access Matrix.</div>
    </div>
    <a href="{{ route('settings.rbac') }}" data-spa class="btn btn-outline-secondary btn-sm">
      <i class="fa-solid fa-arrow-left me-2"></i>
      Back to Roles
    </a>
  </div>

  @if(!$hasSpatiePermissions)
    <div class="alert alert-warning" role="alert">
      <i class="fa-solid fa-triangle-exclamation me-2"></i>
      Spatie permission tables are not ready. Run migrations first.
    </div>
  @else
    <div class="h-card-soft mb-3">
      <div class="head">
        <div style="font-family:var(--fd);font-size:16px;font-weight:700;">Role Form</div>
        <div class="h-muted" style="font-size:13px;">Use lowercase names with hyphens (example: <code>auditor</code>, <code>team-lead</code>).</div>
      </div>
      <div class="body">
        <form method="POST" action="{{ route('settings.roles.store') }}" data-spa>
          @csrf

          <div class="row g-3">
            <div class="col-md-4">
              <label class="h-label" style="display:block;">Role Name</label>
              <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. auditor" required>
            </div>
            <div class="col-md-8">
              <label class="h-label" style="display:block;">Direct Permissions</label>
              <select name="permissions[]" class="form-select" data-h-select multiple>
                @foreach($permissionOptions as $permissionName)
                  <option value="{{ $permissionName }}" @selected(in_array($permissionName, (array) old('permissions', []), true))>{{ $permissionName }}</option>
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
            <button type="submit" class="btn btn-primary" data-busy-text="Creating...">
              <i class="fa-solid fa-floppy-disk me-2"></i>
              Create Role
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif
</div>
@endsection
