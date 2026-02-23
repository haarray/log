@extends('layouts.app')

@section('title', 'Media Manager')
@section('page_title', 'Media Manager')

@section('topbar_extra')
  <span class="h-live-badge">
    <i class="fa-solid fa-photo-film"></i>
    Open Source Media
  </span>
@endsection

@section('content')
<div class="hl-docs hl-settings h-elfinder-page">
  <div class="doc-head">
    <div>
      <div class="doc-title">Media Manager</div>
      <div class="doc-sub">Powered by open-source elFinder with folder operations, right-click actions, zip archive download, image resize, and mobile-friendly view.</div>
    </div>
    <span class="h-pill teal">{{ $storageLabel }}</span>
  </div>

  <div class="h-note mb-2">
    Root path: <code>/public/uploads</code>. Folders created here are visible in your project root at <code>public/uploads</code>.
  </div>

  <div class="h-elfinder-shell">
    <div id="settings-media-elfinder"
      data-connector-url="{{ route('settings.media.connector') }}"
      data-read-only="{{ $canManageSettings ? '0' : '1' }}"
      data-mode="page"></div>
  </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
  if (window.HMediaManager && typeof window.HMediaManager.mountPage === 'function') {
    window.HMediaManager.mountPage();
  }
})();
</script>
@endsection
