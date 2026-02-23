@extends('layouts.app')

@section('title', 'Global Search')
@section('page_title', 'Global Search')

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
      <div class="doc-title">Global Search Sources</div>
      <div class="doc-sub">List-first flow. Create and edit are full-page forms with model field/relation mapping.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap justify-content-end align-items-center">
      <form method="POST" action="{{ route('settings.search.debounce') }}" class="d-flex align-items-center gap-2" data-spa>
        @csrf
        <label class="h-label mb-0" for="search-debounce-ms">Debounce</label>
        <input
          id="search-debounce-ms"
          type="number"
          name="search_debounce_ms"
          min="80"
          max="1500"
          step="10"
          class="form-control form-control-sm"
          style="width:110px;"
          value="{{ old('search_debounce_ms', (string) ($searchDebounceMs ?? 180)) }}"
        >
        <button type="submit" class="btn btn-outline-secondary btn-sm" data-busy-text="Saving...">
          Save
        </button>
      </form>
      <a href="{{ route('settings.search.create') }}" data-spa class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus me-2"></i>
        Create Source
      </a>
    </div>
  </div>

  <div class="h-card-soft mb-3">
    <div class="head h-split">
      <div>
        <div style="font-family:var(--fd);font-size:16px;font-weight:700;">Source Directory</div>
        <div class="h-muted" style="font-size:13px;">Each source maps one model to global search output (title/subtitle/search fields + route/query).</div>
      </div>
      <span class="h-pill teal">{{ count($entries ?? []) }} Sources</span>
    </div>

    <div class="body">
      @if(empty($entries))
        <div class="h-note">
          No sources configured yet.
          <a href="{{ route('settings.search.create') }}" data-spa class="ms-1">Create your first source</a>.
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-sm align-middle h-table-sticky-actions">
            <thead>
              <tr>
                <th>Key</th>
                <th>Model</th>
                <th>Title</th>
                <th>Subtitle</th>
                <th>Search Fields</th>
                <th>Route</th>
                <th>Permission</th>
                <th class="h-col-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($entries as $entry)
                @php
                  $searchFields = implode(', ', (array) ($entry['search'] ?? []));
                  $routeText = trim((string) ($entry['route'] ?? ''));
                  $queryText = trim((string) ($entry['query'] ?? ''));
                  $permissionText = trim((string) ($entry['permission'] ?? ''));
                  $subtitleText = trim((string) ($entry['subtitle'] ?? ''));
                  $entryKey = (string) ($entry['key'] ?? '');
                @endphp
                <tr>
                  <td><code>{{ $entryKey }}</code></td>
                  <td style="max-width:220px;overflow-wrap:anywhere;">{{ (string) ($entry['model'] ?? 'Model') }}</td>
                  <td><code>{{ (string) ($entry['title'] ?? 'id') }}</code></td>
                  <td>
                    @if($subtitleText !== '')
                      <code>{{ $subtitleText }}</code>
                    @else
                      <span class="h-muted">None</span>
                    @endif
                  </td>
                  <td style="max-width:290px;overflow-wrap:anywhere;"><code>{{ $searchFields }}</code></td>
                  <td style="max-width:220px;overflow-wrap:anywhere;">
                    @if($routeText !== '')
                      <code>{{ $routeText }}</code>
                      @if($queryText !== '')
                        <div class="h-muted" style="font-size:11px;">?{{ $queryText }}</div>
                      @endif
                    @else
                      <span class="h-muted">-</span>
                    @endif
                  </td>
                  <td>
                    @if($permissionText !== '')
                      <code>{{ $permissionText }}</code>
                    @else
                      <span class="h-muted">-</span>
                    @endif
                  </td>
                  <td class="h-col-actions">
                    <span class="h-action-group">
                      <a
                        href="{{ route('settings.search.edit', ['searchKey' => $entryKey]) }}"
                        data-spa
                        class="btn btn-outline-secondary btn-sm h-action-icon"
                        title="Edit source"
                        aria-label="Edit source"
                      >
                        <i class="fa-solid fa-pen-to-square"></i>
                      </a>

                      <form
                        method="POST"
                        action="{{ route('settings.search.delete', ['searchKey' => $entryKey]) }}"
                        data-confirm="true"
                        data-confirm-title="Delete search source?"
                        data-confirm-text="This source will be removed from global search."
                      >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm h-action-icon" title="Delete source" aria-label="Delete source">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    </span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  <div class="h-note">
    Tip: Use relation fields like <code>roles.name</code> in search mappings when the selected model exposes that relation.
  </div>
</div>
@endsection
