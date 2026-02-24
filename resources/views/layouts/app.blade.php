{{-- Canonical application layout --}}
<!DOCTYPE html>
@php
  $uiLocale = app()->getLocale();
  if (!in_array($uiLocale, ['en', 'ne'], true)) {
    $uiLocale = 'en';
  }
  $nextUiLocale = $uiLocale === 'ne' ? 'en' : 'ne';
  $hlText = static function (string $en, string $ne = '') use ($uiLocale): string {
    if ($uiLocale === 'ne' && $ne !== '') {
      return $ne;
    }

    return $en;
  };
  $hMenuLabel = static function (string $label) use ($uiLocale): string {
    if ($uiLocale !== 'ne') {
      return $label;
    }

    $map = [
      'Control Hub' => 'कन्ट्रोल हब',
      'Users' => 'प्रयोगकर्ता',
      'Roles & Access' => 'भूमिका र पहुँच',
      'Global Search' => 'ग्लोबल खोज',
      'Media Manager' => 'मिडिया म्यानेजर',
      'App & Branding' => 'एप र ब्रान्डिङ',
      'Activity' => 'गतिविधि',
      'Security' => 'सुरक्षा',
      'Notifications' => 'सूचनाहरू',
      'System Config' => 'सिस्टम कन्फिग',
      'Diagnostics' => 'डायग्नोस्टिक्स',
      'Profile' => 'प्रोफाइल',
    ];

    return $map[$label] ?? $label;
  };
@endphp
<html lang="{{ $uiLocale }}" data-theme="dark">
<head>
  @php
    $request = request();
    $requestBaseUrl = trim((string) $request->getBaseUrl());
    $requestAssetBase = rtrim(
      $request->getSchemeAndHttpHost() . ($requestBaseUrl !== '' ? $requestBaseUrl : ''),
      '/'
    );
    $hAsset = static function (string $path) use ($requestAssetBase): string {
      $cleanPath = ltrim($path, '/');
      return $requestAssetBase . '/' . $cleanPath;
    };

    $uiBranding = \App\Support\AppSettings::uiBranding();
    $brandFavicon = \App\Support\AppSettings::resolveUiAsset((string) ($uiBranding['favicon_url'] ?? ''));
    $brandLogo = \App\Support\AppSettings::resolveUiAsset((string) ($uiBranding['logo_url'] ?? ''));
    $brandAppIcon = \App\Support\AppSettings::resolveUiAsset((string) ($uiBranding['app_icon_url'] ?? ''));
    $brandNotificationSound = \App\Support\AppSettings::resolveUiAsset(\App\Support\AppSettings::get('ui.notification_sound_url', ''));
    $themeColor = trim((string) ($uiBranding['theme_color'] ?? '#2f7df6'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
      $themeColor = '#2f7df6';
    }
    $brandMark = trim((string) ($uiBranding['brand_mark'] ?? config('haarray.app_initial', 'H')));
    if ($brandMark === '') {
      $brandMark = (string) config('haarray.app_initial', 'H');
    }
    $brandSubtitle = trim((string) ($uiBranding['brand_subtitle'] ?? ''));
    if ($brandSubtitle === '') {
      $brandSubtitle = 'by ' . ((string) config('haarray.brand_name', 'Haarray'));
    }
    $brandDisplayName = trim((string) ($uiBranding['display_name'] ?? config('app.name', 'HariLog')));
    if ($brandDisplayName === '') {
      $brandDisplayName = (string) config('app.name', 'HariLog');
    }
    $searchDebounceMs = max(80, min((int) \App\Support\AppSettings::get('search.debounce_ms', '180'), 1500));
    $hotReloadEnabled = app()->environment('local') && (bool) config('haarray.ops.hot_reload', false);
    $notifyAutoPoll = (bool) config('haarray.realtime.auto_poll', false);
    $haarrayCssVersion = (int) (file_exists(public_path('css/haarray.app.css')) ? (filemtime(public_path('css/haarray.app.css')) ?: time()) : time());
    $haarrayJsVersion = (int) (file_exists(public_path('js/haarray.app.js')) ? (filemtime(public_path('js/haarray.app.js')) ?: time()) : time());
    $haarrayNepaliDateVersion = (int) (file_exists(public_path('js/haarray.nepali-date.js')) ? (filemtime(public_path('js/haarray.nepali-date.js')) ?: time()) : $haarrayJsVersion);
    $elfinderAssetVersion = (int) max(
      (int) (file_exists(public_path('css/elfinder.jquery-ui.min.css')) ? (filemtime(public_path('css/elfinder.jquery-ui.min.css')) ?: 0) : 0),
      (int) (file_exists(public_path('css/elfinder.min.css')) ? (filemtime(public_path('css/elfinder.min.css')) ?: 0) : 0),
      (int) (file_exists(public_path('css/elfinder.theme.css')) ? (filemtime(public_path('css/elfinder.theme.css')) ?: 0) : 0),
      (int) (file_exists(public_path('js/elfinder.jquery-ui.min.js')) ? (filemtime(public_path('js/elfinder.jquery-ui.min.js')) ?: 0) : 0),
      (int) (file_exists(public_path('js/elfinder.min.js')) ? (filemtime(public_path('js/elfinder.min.js')) ?: 0) : 0)
    );
    if ($elfinderAssetVersion <= 0) {
      $elfinderAssetVersion = time();
    }
  @endphp
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Dashboard') — {{ $brandDisplayName }}</title>
  <link rel="icon" type="image/x-icon" href="{{ $brandFavicon !== '' ? $brandFavicon : $hAsset('favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
  <link rel="stylesheet" href="{{ $hAsset('css/haarray.app.css') }}?v={{ $haarrayCssVersion }}">
  <style>
    :root {
      --gold: {{ $themeColor }};
      --gold-dk: {{ $themeColor }};
    }
  </style>
  <meta name="theme-color" content="{{ $themeColor }}">
  @if($brandAppIcon !== '')
    <link rel="apple-touch-icon" href="{{ $brandAppIcon }}">
  @endif
  @if(config('haarray.enable_pwa'))
    <link rel="manifest" href="{{ $hAsset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ $brandAppIcon !== '' ? $brandAppIcon : $hAsset('icons/pwa-192.png') }}">
  @endif
  @yield('styles')
</head>
<body
  data-notifications-feed-url="{{ auth()->user()->can('view notifications') ? route('notifications.feed') : '' }}"
  data-notification-read-url-template="{{ auth()->user()->can('view notifications') ? route('notifications.read', ['id' => '__ID__']) : '' }}"
  data-notifications-poll-seconds="{{ (int) config('haarray.realtime.poll_seconds', 20) }}"
  data-notifications-auto-poll="{{ $notifyAutoPoll ? '1' : '0' }}"
  data-browser-notify-enabled="{{ auth()->user()->browser_notifications_enabled ? '1' : '0' }}"
  data-pwa-enabled="{{ config('haarray.enable_pwa') ? '1' : '0' }}"
  data-sw-url="{{ $hAsset('sw.js') }}"
  data-icon-sprite-url="{{ $hAsset('icons/icons.svg') }}"
  data-favicon-url="{{ $brandFavicon !== '' ? $brandFavicon : $hAsset('favicon.ico') }}"
  data-file-manager-list-url="{{ route('ui.filemanager.index') }}"
  data-file-manager-upload-url="{{ route('ui.filemanager.upload') }}"
  data-file-manager-delete-url="{{ route('ui.filemanager.delete') }}"
  data-file-manager-folder-url="{{ route('ui.filemanager.folder') }}"
  data-file-manager-export-url="{{ route('ui.filemanager.export') }}"
  data-file-manager-resize-url="{{ route('ui.filemanager.resize') }}"
  data-global-search-url="{{ route('ui.search.global') }}"
  data-global-search-debounce="{{ $searchDebounceMs }}"
  data-theme-color="{{ $themeColor }}"
  data-notification-read-all-url="{{ auth()->user()->can('view notifications') ? route('notifications.read_all') : '' }}"
  data-notification-sound-url="{{ $brandNotificationSound }}"
  data-hot-reload-enabled="{{ $hotReloadEnabled ? '1' : '0' }}"
  data-hot-reload-stream-url="{{ route('ui.hot_reload.stream') }}"
  data-ui-locale="{{ $uiLocale }}"
  data-elfinder-ui-css-url="{{ $hAsset('css/elfinder.jquery-ui.min.css') }}"
  data-elfinder-ui-js-url="{{ $hAsset('js/elfinder.jquery-ui.min.js') }}"
  data-elfinder-css-url="{{ $hAsset('css/elfinder.min.css') }}"
  data-elfinder-theme-css-url="{{ $hAsset('css/elfinder.theme.css') }}"
  data-elfinder-js-url="{{ $hAsset('js/elfinder.min.js') }}"
>

{{-- Sidebar overlay (mobile) --}}
<div class="h-sidebar-overlay" id="h-sidebar-overlay"></div>

{{-- Mobile toggle --}}
<button class="h-menu-toggle" aria-label="Menu">
  <i class="fa-solid fa-bars"></i>
</button>

{{-- ═══ SIDEBAR ═══ --}}
<aside class="h-sidebar" id="h-sidebar">

  {{-- Brand --}}
  <div class="h-brand" id="h-sidebar-brand">
    @if($brandLogo !== '')
      <img src="{{ $brandLogo }}" alt="{{ $brandDisplayName }} logo" class="h-brand-logo" width="38" height="38">
    @else
      <div class="h-brand-mark">{{ strtoupper(substr($brandMark, 0, 1)) }}</div>
    @endif
    <div>
      <div class="h-brand-name">{{ $brandDisplayName }}</div>
      <div class="h-brand-sub">{{ $brandSubtitle }}</div>
    </div>
    <button type="button" class="h-sidebar-collapse-btn" data-sidebar-collapse-toggle aria-label="Collapse sidebar" title="Collapse sidebar">
      <i class="fa-solid fa-angles-left"></i>
    </button>
  </div>

  <div class="h-sidebar-nav" id="h-sidebar-nav">
    {{-- Nav --}}
    <div class="h-nav-sec">{{ $hlText('Finance', 'वित्त') }}</div>
    @can('view dashboard')
      <a data-spa href="{{ route('dashboard') }}" class="h-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <i class="h-nav-icon fa-solid fa-gauge-high fa-fw"></i>
        {{ $hlText('Dashboard', 'ड्यासबोर्ड') }}
      </a>
    @endcan
    @can('view transactions')
      <a data-spa href="{{ route('transactions.index') }}" class="h-nav-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
        <i class="h-nav-icon fa-solid fa-money-bill-transfer fa-fw"></i>
        {{ $hlText('Transactions', 'लेनदेन') }}
      </a>
    @endcan
    @can('view accounts')
      <a data-spa href="{{ route('accounts.index') }}" class="h-nav-item {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
        <i class="h-nav-icon fa-solid fa-building-columns fa-fw"></i>
        {{ $hlText('Accounts', 'खाताहरू') }}
      </a>
    @endcan
    @can('view portfolio')
      <a data-spa href="{{ route('portfolio.index') }}" class="h-nav-item {{ request()->routeIs('portfolio.*') && !request()->has('panel') ? 'active' : '' }}">
        <i class="h-nav-icon fa-solid fa-chart-line fa-fw"></i>
        {{ $hlText('Portfolio', 'पोर्टफोलियो') }}
      </a>
    @endcan

    <div class="h-nav-sec">{{ $hlText('Market', 'बजार') }}</div>
    @can('view portfolio')
      <a
        data-spa
        href="{{ route('portfolio.index', ['panel' => 'ipo']) }}"
        class="h-nav-item {{ request()->routeIs('portfolio.*') && request()->query('panel') === 'ipo' ? 'active' : '' }}"
      >
        <i class="h-nav-icon fa-solid fa-clock fa-fw"></i>
        {{ $hlText('IPO Tracker', 'आईपीओ ट्र्याकर') }}
      </a>
      <a
        data-spa
        href="{{ route('portfolio.index', ['panel' => 'gold']) }}"
        class="h-nav-item {{ request()->routeIs('portfolio.*') && request()->query('panel') === 'gold' ? 'active' : '' }}"
      >
        <i class="h-nav-icon fa-solid fa-coins fa-fw"></i>
        {{ $hlText('Gold & Forex', 'सुन र फरेक्स') }}
      </a>
    @endcan

    <div class="h-nav-sec">{{ $hlText('Intelligence', 'विश्लेषण') }}</div>
    @can('view suggestions')
      <a data-spa href="{{ route('suggestions.index') }}" class="h-nav-item {{ request()->routeIs('suggestions.*') ? 'active' : '' }}">
        <i class="h-nav-icon fa-solid fa-lightbulb fa-fw"></i>
        {{ $hlText('Suggestions', 'सुझावहरू') }}
      </a>
    @endcan
    @can('view docs')
      <a data-spa href="{{ route('docs.index', ['tab' => 'docs-tutorials']) }}" class="h-nav-item">
        <i class="h-nav-icon fa-brands fa-telegram fa-fw"></i>
        {{ $hlText('Telegram Bot', 'टेलिग्राम बट') }}
      </a>
    @endcan

    <div class="h-nav-sec">{{ $hlText('System', 'सिस्टम') }}</div>
    @can('view docs')
      <a data-spa href="{{ route('docs.index') }}" class="h-nav-item {{ request()->routeIs('docs.*') ? 'active' : '' }}">
        <i class="h-nav-icon fa-solid fa-book-open fa-fw"></i>
        {{ $hlText('Docs', 'डक्स') }}
      </a>
    @endcan
    @if(auth()->user()->can('view settings'))
      @php
        $settingsRouteActive = request()->routeIs('settings.index') || request()->routeIs('settings.users.*') || request()->routeIs('settings.media.*') || request()->routeIs('settings.rbac*') || request()->routeIs('settings.search.*');
      @endphp
      <div
        class="h-nav-group {{ $settingsRouteActive ? 'open' : '' }}"
        data-nav-group="settings"
        data-expanded="{{ $settingsRouteActive ? '1' : '0' }}"
      >
        <button
          type="button"
          class="h-nav-item h-nav-parent {{ $settingsRouteActive ? 'active' : '' }}"
          data-nav-toggle="settings"
          aria-expanded="{{ $settingsRouteActive ? 'true' : 'false' }}"
        >
          <span class="h-row">
            <i class="h-nav-icon fa-solid fa-sliders fa-fw"></i>
            {{ $hlText('Settings', 'सेटिङ्स') }}
          </span>
          <i class="fa-solid fa-chevron-right h-nav-caret"></i>
        </button>

        <div class="h-nav-sub">
          @foreach((array) config('menu.settings_nav', []) as $item)
            @php
              $permission = (string) ($item['permission'] ?? '');
              if ($permission !== '' && !auth()->user()->can($permission)) {
                  continue;
              }

              $routeName = (string) ($item['route'] ?? '');
              if ($routeName === '' || !\Illuminate\Support\Facades\Route::has($routeName)) {
                  continue;
              }

              $params = is_array($item['params'] ?? null) ? $item['params'] : [];
              $activeRoute = (string) ($item['active_route'] ?? $routeName);
              $matchQuery = trim((string) ($item['match_query'] ?? ''));
              $isActive = request()->routeIs($activeRoute);

              if ($isActive && $matchQuery !== '') {
                  $queryPairs = array_filter(array_map('trim', explode('&', $matchQuery)));
                  foreach ($queryPairs as $pair) {
                      [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                      if ($key !== '' && request()->query($key) !== $value) {
                          $isActive = false;
                          break;
                      }
                  }
              }
            @endphp
            <a
              data-spa
              href="{{ route($routeName, $params) }}"
              @if($matchQuery !== '') data-match-query="{{ $matchQuery }}" @endif
              class="h-nav-sub-item {{ $isActive ? 'active' : '' }}"
            >
              <i class="{{ $item['icon'] ?? 'fa-solid fa-circle' }}"></i>
              {{ $hMenuLabel((string) ($item['label'] ?? 'Menu')) }}
            </a>
          @endforeach
        </div>
      </div>
    @endif
  </div>

  {{-- User + logout --}}
  <div class="h-sidebar-bottom">
    <div class="h-user-card" data-modal-open="user-menu-modal">
      <div class="h-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
      <div style="flex:1;min-width:0;">
        <div class="h-user-name">{{ auth()->user()->name }}</div>
        <div class="h-user-role">HariLog Free</div>
      </div>
      <i class="fa-solid fa-ellipsis-vertical" style="color:var(--t3);flex-shrink:0;"></i>
    </div>
  </div>

</aside>

{{-- ═══ MAIN ═══ --}}
<div class="h-main" id="h-main">

  {{-- Topbar --}}
  <header class="h-topbar">
    <span class="h-page-title-bar" id="h-page-title">@yield('page_title', $hlText('Dashboard', 'ड्यासबोर्ड'))</span>
    <span id="h-clock" style="font-family:var(--fm);font-size:11px;color:var(--t3);">{{ \App\Support\UiDate::dual(now(), true, $uiLocale) }}</span>
    <div class="h-topbar-right">
      <div id="h-topbar-extra">
        @yield('topbar_extra')
      </div>
      @if(\Illuminate\Support\Facades\Route::has('ui.locale.set'))
        <form method="POST" action="{{ route('ui.locale.set') }}" class="h-locale-form">
          @csrf
          <input type="hidden" name="locale" value="{{ $nextUiLocale }}">
          <button
            class="h-icon-btn h-locale-toggle"
            type="submit"
            title="{{ $uiLocale === 'ne' ? 'Switch to English' : 'नेपालीमा बदल्नुहोस्' }}"
            aria-label="{{ $uiLocale === 'ne' ? 'Switch to English' : 'Switch to Nepali' }}"
          >
            <span class="h-locale-pill">{{ $uiLocale === 'ne' ? 'EN' : 'ने' }}</span>
          </button>
        </form>
      @endif
      <button class="h-icon-btn" type="button" title="{{ $hlText('Search (⌘K / Ctrl+K)', 'खोज्नुहोस् (⌘K / Ctrl+K)') }}" data-global-search-open aria-label="{{ $hlText('Global Search', 'ग्लोबल खोज') }}">
        <i class="fa-solid fa-magnifying-glass"></i>
      </button>
      @can('view settings')
        <button class="h-icon-btn" type="button" title="{{ $hlText('Media Library', 'मिडिया लाइब्रेरी') }}" data-media-manager-open aria-label="{{ $hlText('Media Library', 'मिडिया लाइब्रेरी') }}">
          <i class="fa-solid fa-photo-film"></i>
        </button>
      @endcan
      @if(config('haarray.enable_pwa'))
        <button class="h-icon-btn" type="button" id="h-pwa-install" title="{{ $hlText('Install app', 'एप इन्स्टल गर्नुहोस्') }}" style="display:none;">
          <i class="fa-solid fa-download"></i>
        </button>
      @endif
      @can('view notifications')
        <button class="h-icon-btn h-notif-toggle" type="button" title="{{ $hlText('Notifications', 'सूचनाहरू') }}" data-notif-toggle aria-label="{{ $hlText('Notifications', 'सूचनाहरू') }}">
          <i class="fa-solid fa-bell"></i>
          <span class="h-notif-dot is-hidden"></span>
        </button>
      @endcan
      <button class="h-icon-btn" type="button" title="{{ $hlText('Debug Console', 'डिबग कन्सोल') }}" data-debug-toggle aria-label="{{ $hlText('Debug Console', 'डिबग कन्सोल') }}">
        <i class="fa-solid fa-bug"></i>
      </button>
      {{-- Theme toggle --}}
      <button class="h-theme-toggle h-icon-btn" title="{{ $hlText('Toggle theme', 'थिम बदल्नुहोस्') }}">
        <span class="moon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg></span>
        <span class="sun" style="display:none"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg></span>
      </button>
    </div>
  </header>

  {{-- Flash messages --}}
  <div id="h-page-flash" class="visually-hidden" aria-hidden="true">
    @if(session('success'))
      <div class="h-alert success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="h-alert error">{{ session('error') }}</div>
    @endif
  </div>

  {{-- Page content --}}
  <div class="h-page" id="h-spa-content">
    @yield('content')
  </div>

</div>

@can('view notifications')
  {{-- Notification Tray --}}
  <div class="h-notif-tray" id="h-notif-tray" aria-hidden="true">
    <div class="h-notif-head">
      <div>
        <div class="h-notif-title">Notifications</div>
        <div class="h-notif-sub">System alerts and updates</div>
      </div>
      <div class="h-row" style="gap:6px;">
        <button type="button" class="h-icon-btn" data-notif-mark-all aria-label="Mark all as read" title="Mark all read">
          <i class="fa-solid fa-check-double"></i>
        </button>
        <button type="button" class="h-icon-btn" data-notif-refresh aria-label="Refresh notifications" title="Refresh">
          <i class="fa-solid fa-rotate"></i>
        </button>
        <button type="button" class="h-modal-close" data-notif-close aria-label="Close notifications">×</button>
      </div>
    </div>
    <div class="h-notif-list" id="h-notif-list">
      <div class="h-notif-empty">
        <i class="fa-regular fa-bell-slash"></i>
        <span>No notifications yet.</span>
      </div>
    </div>
  </div>
@endcan

{{-- Debug Tray --}}
<div class="h-debug-tray" id="h-debug-tray" aria-hidden="true">
  <div class="h-debug-head">
    <div>
      <div class="h-notif-title">Debug Console</div>
      <div class="h-notif-sub">Client errors and diagnostic events</div>
    </div>
    <div class="h-row" style="gap:6px;">
      <button type="button" class="h-icon-btn" data-debug-refresh aria-label="Refresh debug log" title="Refresh">
        <i class="fa-solid fa-rotate"></i>
      </button>
      <button type="button" class="h-icon-btn" data-debug-clear aria-label="Clear debug log" title="Clear">
        <i class="fa-solid fa-trash"></i>
      </button>
      <button type="button" class="h-modal-close" data-debug-close aria-label="Close debug console">×</button>
    </div>
  </div>
  <div class="h-debug-list" id="h-debug-list"></div>
</div>

{{-- ═══ GLOBAL MODALS ═══ --}}

{{-- User menu modal --}}
<div class="h-modal-overlay" id="user-menu-modal">
  <div class="h-modal" style="max-width:300px;">
    <div class="h-modal-head">
      <div class="h-modal-title">{{ auth()->user()->name }}</div>
      <button class="h-modal-close">×</button>
    </div>
    <div class="h-modal-body">
      <p style="font-size:13px;color:var(--t2);margin-bottom:18px;">{{ auth()->user()->email }}</p>
      <button type="button" class="btn btn-outline-secondary w-100 mb-2" data-modal-open="user-profile-modal" data-modal-close>
        <i class="fa-solid fa-user-gear me-2"></i>
        Profile Settings
      </button>
      <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="h-btn danger full">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign Out
        </button>
      </form>
    </div>
  </div>
</div>

{{-- Profile modal --}}
<div class="h-modal-overlay" id="user-profile-modal">
  <div class="h-modal" style="max-width:560px;">
    <div class="h-modal-head">
      <div class="h-modal-title">Profile Settings</div>
      <button class="h-modal-close">×</button>
    </div>
    <div class="h-modal-body">
      <form method="POST" action="{{ route('profile.update') }}" data-spa>
        @csrf
        <div class="row g-3">
          <div class="col-md-6">
            <label class="h-label" style="display:block;">Name</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', auth()->user()->name) }}" required>
          </div>
          <div class="col-md-6">
            <label class="h-label" style="display:block;">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', auth()->user()->email) }}" required>
          </div>
          <div class="col-md-6">
            <label class="h-label" style="display:block;">Telegram Chat ID</label>
            <input type="text" name="telegram_chat_id" class="form-control" value="{{ old('telegram_chat_id', auth()->user()->telegram_chat_id) }}" placeholder="optional">
          </div>
          <div class="col-md-6">
            <label class="h-label" style="display:block;">Browser Notifications</label>
            <select name="browser_notifications_enabled" class="form-select" data-h-select>
              <option value="1" @selected(old('browser_notifications_enabled', auth()->user()->browser_notifications_enabled ? '1' : '0') === '1')>On</option>
              <option value="0" @selected(old('browser_notifications_enabled', auth()->user()->browser_notifications_enabled ? '1' : '0') === '0')>Off</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="h-label" style="display:block;">New Password</label>
            <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep current">
          </div>
          <div class="col-md-6">
            <label class="h-label" style="display:block;">Confirm Password</label>
            <input type="password" name="password_confirmation" class="form-control" minlength="8" autocomplete="new-password" placeholder="Confirm password">
          </div>
        </div>
        <div class="d-flex justify-content-end mt-3">
          <button type="submit" class="btn btn-primary" data-busy-text="Saving...">
            <i class="fa-solid fa-floppy-disk me-2"></i>
            Save Profile
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Global search modal --}}
<div class="h-modal-overlay" id="h-global-search-modal">
  <div class="h-modal h-search-modal">
    <div class="h-modal-head">
      <div class="h-modal-title">Global Search</div>
      <button class="h-modal-close">×</button>
    </div>
    <div class="h-modal-body">
      <div class="h-search-headline">Press <code>⌘K</code> / <code>Ctrl+K</code> to open quickly</div>
      <input type="text" id="h-global-search-input" class="form-control" placeholder="Search users, docs, settings, activities...">
      <div class="h-global-search-results" id="h-global-search-results">
        <div class="h-notif-empty"><i class="fa-solid fa-magnifying-glass"></i><span>Start typing to search.</span></div>
      </div>
    </div>
  </div>
</div>

@can('view settings')
  {{-- Global media library modal --}}
  <div class="h-modal-overlay" id="h-media-manager-modal">
    <div class="h-modal h-media-manager-modal">
      <div class="h-modal-head">
        <div class="h-modal-title">Media Library</div>
        <button class="h-modal-close">×</button>
      </div>
      <div class="h-modal-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <div class="h-note mb-0" id="h-media-manager-target-note" hidden></div>
          <a data-spa href="{{ route('settings.media.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-up-right-from-square me-1"></i>
            Open Full Manager
          </a>
        </div>
        <div class="h-elfinder-shell h-elfinder-shell-modal">
          <div id="h-media-manager-elfinder"
            data-connector-url="{{ route('settings.media.connector') }}"
            data-read-only="{{ auth()->user()->can('manage settings') ? '0' : '1' }}"
            data-mode="picker"></div>
        </div>
      </div>
    </div>
  </div>
@endcan

{{-- App modals --}}
<div id="h-page-modals">
  @yield('modals')
</div>

{{-- FAB --}}
<div id="h-page-fab">
  @yield('fab')
</div>

{{-- Confirm Modal --}}
<x-confirm-modal />
{{-- Scripts --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script src="{{ $hAsset('js/haarray.nepali-date.js') }}?v={{ $haarrayNepaliDateVersion }}"></script>
<script src="{{ $hAsset('js/haarray.app.js') }}?v={{ $haarrayJsVersion }}"></script>
<div id="h-page-scripts">
  @yield('scripts')
</div>

</body>
</html>
