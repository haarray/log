@extends('layouts.app')

@php
  $isEdit = (string) ($mode ?? 'create') === 'edit';
  $entryData = is_array($entry ?? null) ? $entry : [];

  $formValues = [
      'model' => (string) old('model', (string) ($entryData['model'] ?? '')),
      'key' => (string) old('key', (string) ($entryData['key'] ?? '')),
      'id' => (string) old('id', (string) ($entryData['id'] ?? 'id')),
      'title' => (string) old('title', (string) ($entryData['title'] ?? '')),
      'subtitle' => (string) old('subtitle', (string) ($entryData['subtitle'] ?? '')),
      'search' => array_values(array_filter(array_map(
          fn ($field) => trim((string) $field),
          (array) old('search', (array) ($entryData['search'] ?? []))
      ))),
      'route' => (string) old('route', (string) ($entryData['route'] ?? '')),
      'query' => (string) old('query', (string) ($entryData['query'] ?? '')),
      'permission' => (string) old('permission', (string) ($entryData['permission'] ?? '')),
      'icon' => (string) old('icon', (string) ($entryData['icon'] ?? 'fa-solid fa-magnifying-glass')),
  ];
@endphp

@section('title', $isEdit ? 'Edit Search Source' : 'Create Search Source')
@section('page_title', $isEdit ? 'Edit Search Source' : 'Create Search Source')

@section('topbar_extra')
  <span class="h-live-badge">
    <i class="fa-solid fa-magnifying-glass"></i>
    Search Builder
  </span>
@endsection

@section('content')
<div class="hl-docs hl-settings">
  <div class="doc-head">
    <div>
      <div class="doc-title">{{ $isEdit ? 'Edit Search Source' : 'Create Search Source' }}</div>
      <div class="doc-sub">Select model, map direct/relation fields, and define result route behavior.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap justify-content-end">
      <a href="{{ route('settings.search.index') }}" data-spa class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-2"></i>
        Back to Sources
      </a>
      @if($isEdit)
        <a href="{{ route('settings.search.create') }}" data-spa class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus me-2"></i>
          Create Source
        </a>
      @endif
    </div>
  </div>

  <div class="h-card-soft mb-3">
    <div class="head">
      <div style="font-family:var(--fd);font-size:16px;font-weight:700;">Source Form</div>
      <div class="h-muted" style="font-size:13px;">Relation fields are supported with dot-path syntax (example: <code>roles.name</code>).</div>
    </div>

    <div class="body">
      <form method="POST" action="{{ $formAction }}" data-spa id="h-search-source-form">
        @csrf
        @if(strtoupper((string) ($formMethod ?? 'POST')) !== 'POST')
          @method($formMethod)
        @endif

        <div class="row g-3">
          <div class="col-md-4">
            <label class="h-label" for="h-search-model" style="display:block;">Model</label>
            <select id="h-search-model" name="model" class="form-select" required>
              <option value="">Choose model</option>
              @foreach(($searchCatalog ?? []) as $catalogItem)
                @php
                  $modelClass = (string) ($catalogItem['model'] ?? '');
                  $modelLabel = (string) ($catalogItem['label'] ?? $modelClass);
                @endphp
                <option value="{{ $modelClass }}" @selected($formValues['model'] === $modelClass)>{{ $modelLabel }}</option>
              @endforeach
            </select>
            @error('model')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="h-label" for="h-search-key" style="display:block;">Key</label>
            <input id="h-search-key" type="text" name="key" class="form-control" value="{{ $formValues['key'] }}" placeholder="user" required>
            @error('key')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="h-label" for="h-search-icon" style="display:block;">Icon Class</label>
            <input id="h-search-icon" type="text" name="icon" class="form-control" value="{{ $formValues['icon'] }}" placeholder="fa-solid fa-user">
            @error('icon')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="h-label" for="h-search-id" style="display:block;">ID Field</label>
            <select id="h-search-id" name="id" class="form-select" required></select>
            @error('id')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="h-label" for="h-search-title" style="display:block;">Result Title Field</label>
            <select id="h-search-title" name="title" class="form-select" required></select>
            @error('title')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="h-label" for="h-search-subtitle" style="display:block;">Result Subtitle Field</label>
            <select id="h-search-subtitle" name="subtitle" class="form-select"></select>
            @error('subtitle')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="h-label" for="h-search-route" style="display:block;">Route Name</label>
            <input id="h-search-route" type="text" name="route" class="form-control" value="{{ $formValues['route'] }}" placeholder="settings.users.index">
            @error('route')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="h-label" for="h-search-query" style="display:block;">Route Query Template</label>
            <input id="h-search-query" type="text" name="query" class="form-control" value="{{ $formValues['query'] }}" placeholder="edit_user={id}">
            @error('query')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label class="h-label" for="h-search-permission" style="display:block;">Permission</label>
            <input id="h-search-permission" type="text" name="permission" class="form-control" value="{{ $formValues['permission'] }}" placeholder="view users">
            @error('permission')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="col-12">
            <label class="h-label" style="display:block;">Searchable Fields</label>
            <div class="h-note mb-2" style="font-size:11px;">Select one or more fields. Relation fields are queryable and rendered from dot-path values.</div>
            <div id="h-search-fields-grid" class="h-search-fields-grid"></div>
            @error('search')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
            @error('search.*')<div class="h-error-msg mt-1">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="d-flex justify-content-end mt-3">
          <button type="submit" class="btn btn-primary" data-busy-text="Saving...">
            <i class="fa-solid fa-floppy-disk me-2"></i>
            {{ $isEdit ? 'Update Source' : 'Create Source' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
  const form = document.getElementById('h-search-source-form');
  if (!form) return;

  const catalog = @json($searchCatalog ?? []);
  const initial = @json($formValues);
  const isEdit = @json($isEdit);

  const modelSelect = document.getElementById('h-search-model');
  const keyInput = document.getElementById('h-search-key');
  const idSelect = document.getElementById('h-search-id');
  const titleSelect = document.getElementById('h-search-title');
  const subtitleSelect = document.getElementById('h-search-subtitle');
  const routeInput = document.getElementById('h-search-route');
  const queryInput = document.getElementById('h-search-query');
  const permissionInput = document.getElementById('h-search-permission');
  const iconInput = document.getElementById('h-search-icon');
  const fieldsGrid = document.getElementById('h-search-fields-grid');

  if (!modelSelect || !keyInput || !idSelect || !titleSelect || !subtitleSelect || !routeInput || !queryInput || !permissionInput || !iconInput || !fieldsGrid) {
    return;
  }

  const unique = (values) => Array.from(new Set((values || []).map((value) => String(value || '').trim()).filter(Boolean)));

  const fillSelect = (selectEl, values, selected, includeBlank = false) => {
    const selectedValue = String(selected || '');
    const options = [];

    if (includeBlank) {
      options.push('<option value="">None</option>');
    }

    unique(values).forEach((value) => {
      const safe = String(value || '');
      options.push('<option value="' + safe.replace(/"/g, '&quot;') + '"' + (safe === selectedValue ? ' selected' : '') + '>' + safe + '</option>');
    });

    selectEl.innerHTML = options.join('');
  };

  const getCatalogByModel = (modelClass) => {
    return (catalog || []).find((item) => String(item.model || '') === String(modelClass || '')) || null;
  };

  const renderFields = (catalogItem, selectedFields) => {
    if (!catalogItem) {
      fieldsGrid.innerHTML = '<div class="h-note">Choose a model to load searchable fields.</div>';
      return;
    }

    const selectedSet = new Set(unique(selectedFields));
    const directFields = unique(catalogItem.columns || []);
    const relationFields = unique(catalogItem.relation_fields || []);
    const cards = [];

    if (directFields.length) {
      cards.push(
        '<div class="h-card-soft" style="padding:10px;">'
          + '<div class="h-muted mb-2" style="font-size:11px;">Direct fields</div>'
          + directFields.map((field) => {
            const checked = selectedSet.has(field) ? ' checked' : '';
            return '<label class="form-check" style="margin-bottom:6px;">'
              + '<input class="form-check-input" type="checkbox" name="search[]" value="' + field + '"' + checked + '>'
              + '<span class="form-check-label">' + field + '</span>'
              + '</label>';
          }).join('')
        + '</div>'
      );
    }

    if (relationFields.length) {
      cards.push(
        '<div class="h-card-soft" style="padding:10px;">'
          + '<div class="h-muted mb-2" style="font-size:11px;">Relation fields</div>'
          + relationFields.map((field) => {
            const checked = selectedSet.has(field) ? ' checked' : '';
            return '<label class="form-check" style="margin-bottom:6px;">'
              + '<input class="form-check-input" type="checkbox" name="search[]" value="' + field + '"' + checked + '>'
              + '<span class="form-check-label">' + field + '</span>'
              + '</label>';
          }).join('')
        + '</div>'
      );
    }

    fieldsGrid.innerHTML = cards.length ? cards.join('') : '<div class="h-note">No searchable fields found for this model.</div>';
  };

  const applyModel = (modelClass, useInitialValues) => {
    const catalogItem = getCatalogByModel(modelClass);
    if (!catalogItem) {
      idSelect.innerHTML = '';
      titleSelect.innerHTML = '';
      subtitleSelect.innerHTML = '<option value="">None</option>';
      renderFields(null, []);
      return;
    }

    const fieldPaths = unique([].concat(catalogItem.columns || [], catalogItem.relation_fields || []));
    const defaults = catalogItem.defaults || {};

    const idValue = useInitialValues
      ? String(initial.id || defaults.id || catalogItem.id_field || fieldPaths[0] || 'id')
      : String(defaults.id || catalogItem.id_field || fieldPaths[0] || 'id');

    const titleValue = useInitialValues
      ? String(initial.title || defaults.title || catalogItem.title_field || idValue)
      : String(defaults.title || catalogItem.title_field || idValue);

    const subtitleValue = useInitialValues
      ? String(initial.subtitle || defaults.subtitle || '')
      : String(defaults.subtitle || '');

    fillSelect(idSelect, fieldPaths.length ? fieldPaths : ['id'], idValue);
    fillSelect(titleSelect, fieldPaths.length ? fieldPaths : ['id'], titleValue);
    fillSelect(subtitleSelect, fieldPaths, subtitleValue, true);

    if (useInitialValues) {
      routeInput.value = String(initial.route || '');
      queryInput.value = String(initial.query || '');
      permissionInput.value = String(initial.permission || '');
      iconInput.value = String(initial.icon || 'fa-solid fa-magnifying-glass');
    } else {
      routeInput.value = String(defaults.route || '');
      queryInput.value = String(defaults.query || '');
      permissionInput.value = String(defaults.permission || '');
      iconInput.value = String(defaults.icon || 'fa-solid fa-magnifying-glass');
      if (!isEdit) {
        keyInput.value = String(defaults.key || '').trim();
      }
    }

    const selectedSearch = useInitialValues
      ? (Array.isArray(initial.search) ? initial.search : [])
      : (Array.isArray(defaults.search) ? defaults.search : []);

    renderFields(catalogItem, selectedSearch);
  };

  modelSelect.addEventListener('change', () => {
    const selectedModel = String(modelSelect.value || '');
    if (!selectedModel) {
      renderFields(null, []);
      return;
    }

    applyModel(selectedModel, false);
  });

  const initialModel = String(initial.model || modelSelect.value || '');
  if (initialModel) {
    modelSelect.value = initialModel;
    applyModel(initialModel, true);
  } else {
    renderFields(null, []);
  }
})();
</script>
@endsection
