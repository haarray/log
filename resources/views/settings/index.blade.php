@extends('layouts.app')

@section('title', 'Settings')
@section('page_title', 'Settings')

@section('topbar_extra')
  <span class="h-live-badge">
    <i class="fa-solid fa-sliders"></i>
    Settings Center
  </span>
@endsection

@section('content')
@php
  $defaultSettingsTab = (string) request()->query('tab', 'settings-app');
  $allowedTabs = ['settings-app', 'settings-activity', 'settings-security', 'settings-notifications', 'settings-system'];
  if (!in_array($defaultSettingsTab, $allowedTabs, true)) {
      $defaultSettingsTab = 'settings-app';
  }
  if ($defaultSettingsTab === 'settings-system' && !$canManageSettings) {
      $defaultSettingsTab = 'settings-app';
  }
  $themeColor = (string) ($uiBranding['theme_color'] ?? '#2f7df6');
  $logoUrl = (string) ($uiBranding['logo_url'] ?? '');
  $faviconUrl = (string) ($uiBranding['favicon_url'] ?? '');
  $appIconUrl = (string) ($uiBranding['app_icon_url'] ?? '');
  $notificationSoundValue = (string) ($notificationSoundUrl ?? '');
  $dbLabel = ($dbConnectionInfo['database'] ?? '') !== '' ? (string) $dbConnectionInfo['database'] : 'n/a';
@endphp

<div class="hl-docs hl-settings">
  <div class="doc-head">
    <div>
      <div class="doc-title">Settings Control Hub</div>
      <div class="doc-sub">
        Clean starter settings for branding, activity, security, notifications, and system configuration.
      </div>
    </div>
    <span class="h-pill teal">DB: {{ $dbLabel }}</span>
  </div>

  <div class="h-tab-shell h-settings-shell" id="settings-main-tabs" data-ui-tabs data-default-tab="{{ $defaultSettingsTab }}">
    <div class="h-tab-nav" role="tablist" aria-label="Settings sections">
      <button type="button" class="h-tab-btn" data-tab-btn="settings-app"><i class="fa-solid fa-palette"></i> Branding</button>
      <button type="button" class="h-tab-btn" data-tab-btn="settings-activity"><i class="fa-solid fa-chart-line"></i> Activity</button>
      <button type="button" class="h-tab-btn" data-tab-btn="settings-security"><i class="fa-solid fa-user-shield"></i> Security</button>
      <button type="button" class="h-tab-btn" data-tab-btn="settings-notifications"><i class="fa-solid fa-bell"></i> Notifications</button>
      @if($canManageSettings)
        <button type="button" class="h-tab-btn" data-tab-btn="settings-system"><i class="fa-solid fa-gear"></i> System</button>
      @endif
    </div>

    <div class="h-tab-panel" data-tab-panel="settings-app">
      @if($canManageSettings)
        <div class="h-card-soft mb-3">
          <div class="head">
            <div style="font-family:var(--fd);font-size:16px;font-weight:700;">Branding, Icons, Theme</div>
            <div class="h-muted" style="font-size:13px;">Use Media Library injection for logo/favicon/app icon/sound. No separate upload fields needed here.</div>
          </div>
          <div class="body">
            <form method="POST" action="{{ route('settings.branding') }}" data-spa>
              @csrf
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="h-label" style="display:block;">App Name</label>
                  <input type="text" name="ui_display_name" class="form-control" value="{{ old('ui_display_name', $uiBranding['display_name'] ?? config('app.name')) }}" required>
                </div>
                <div class="col-md-4">
                  <label class="h-label" style="display:block;">Brand Subtitle</label>
                  <input type="text" name="ui_brand_subtitle" class="form-control" value="{{ old('ui_brand_subtitle', $uiBranding['brand_subtitle'] ?? ('by ' . config('haarray.brand_name'))) }}">
                </div>
                <div class="col-md-2">
                  <label class="h-label" style="display:block;">Mark</label>
                  <input type="text" name="ui_brand_mark" class="form-control" maxlength="8" value="{{ old('ui_brand_mark', $uiBranding['brand_mark'] ?? 'H') }}" required>
                </div>
                <div class="col-md-2">
                  <label class="h-label" style="display:block;">Theme Color</label>
                  <input type="color" name="ui_theme_color" class="form-control form-control-color" value="{{ old('ui_theme_color', $themeColor) }}">
                </div>

                <div class="col-md-6">
                  <label class="h-label" style="display:block;">Logo URL</label>
                  <div class="input-group">
                    <input type="text" name="ui_logo_url" id="ui-logo-url" class="form-control" value="{{ old('ui_logo_url', $logoUrl) }}" placeholder="/uploads/... or https://...">
                    <button type="button" class="btn btn-outline-secondary" data-media-manager-open data-media-target="ui-logo-url">
                      <i class="fa-solid fa-photo-film me-1"></i>
                      Inject
                    </button>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="h-label" style="display:block;">Favicon URL</label>
                  <div class="input-group">
                    <input type="text" name="ui_favicon_url" id="ui-favicon-url" class="form-control" value="{{ old('ui_favicon_url', $faviconUrl) }}" placeholder="/uploads/... or https://...">
                    <button type="button" class="btn btn-outline-secondary" data-media-manager-open data-media-target="ui-favicon-url">
                      <i class="fa-solid fa-photo-film me-1"></i>
                      Inject
                    </button>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="h-label" style="display:block;">App Icon URL</label>
                  <div class="input-group">
                    <input type="text" name="ui_app_icon_url" id="ui-app-icon-url" class="form-control" value="{{ old('ui_app_icon_url', $appIconUrl) }}" placeholder="/uploads/... or https://...">
                    <button type="button" class="btn btn-outline-secondary" data-media-manager-open data-media-target="ui-app-icon-url">
                      <i class="fa-solid fa-photo-film me-1"></i>
                      Inject
                    </button>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="h-label" style="display:block;">Notification Sound URL</label>
                  <div class="input-group">
                    <input type="text" name="ui_notification_sound_url" id="ui-notification-sound-url" class="form-control" value="{{ old('ui_notification_sound_url', $notificationSoundValue) }}" placeholder="/uploads/... or https://...">
                    <button type="button" class="btn btn-outline-secondary" data-media-manager-open data-media-target="ui-notification-sound-url">
                      <i class="fa-solid fa-photo-film me-1"></i>
                      Inject
                    </button>
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="h-label" style="display:block;">Logo Preview</label>
                  <div class="h-note" style="min-height:86px;display:flex;align-items:center;justify-content:center;">
                    @if(trim($logoUrl) !== '')
                      <img src="{{ \App\Support\AppSettings::resolveUiAsset($logoUrl) }}" alt="Logo" style="max-width:100%;max-height:72px;object-fit:contain;">
                    @else
                      <span class="h-muted">No logo selected.</span>
                    @endif
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="h-label" style="display:block;">Favicon Preview</label>
                  <div class="h-note" style="min-height:86px;display:flex;align-items:center;justify-content:center;">
                    @if(trim($faviconUrl) !== '')
                      <img src="{{ \App\Support\AppSettings::resolveUiAsset($faviconUrl) }}" alt="Favicon" style="width:38px;height:38px;object-fit:contain;">
                    @else
                      <span class="h-muted">No favicon selected.</span>
                    @endif
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="h-label" style="display:block;">App Icon Preview</label>
                  <div class="h-note" style="min-height:86px;display:flex;align-items:center;justify-content:center;">
                    @if(trim($appIconUrl) !== '')
                      <img src="{{ \App\Support\AppSettings::resolveUiAsset($appIconUrl) }}" alt="App icon" style="width:44px;height:44px;object-fit:contain;">
                    @else
                      <span class="h-muted">No app icon selected.</span>
                    @endif
                  </div>
                </div>

                @if(trim($notificationSoundValue) !== '')
                  <div class="col-12">
                    <label class="h-label" style="display:block;">Sound Preview</label>
                    <audio controls preload="none" class="w-100" src="{{ $notificationSoundValue }}"></audio>
                  </div>
                @endif

              </div>

              <div class="h-note mt-3">
                Active DB from `.env`: <code>{{ $dbConnectionInfo['connection'] }}</code> / <code>{{ $dbConnectionInfo['database'] ?: 'n/a' }}</code>
              </div>

              <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary" data-busy-text="Saving...">
                  <i class="fa-solid fa-floppy-disk me-2"></i>
                  Save App Settings
                </button>
              </div>
            </form>
          </div>
        </div>

      @else
        <div class="h-note">Only users with <code>manage settings</code> can update branding.</div>
      @endif
    </div>

    <div class="h-tab-panel" data-tab-panel="settings-activity">
      <div class="h-card-soft mb-3">
        <div class="head h-split">
          <div>
            <div style="font-family:var(--fd);font-size:16px;font-weight:700;">User Activity Log</div>
            <div class="h-muted" style="font-size:13px;">Tracks route, method, status, and user details.</div>
          </div>
          <a href="{{ route('settings.activity.export') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-file-export me-2"></i>
            Export Activity
          </a>
        </div>

        <div class="body">
          <div class="table-responsive">
            <table
              class="table table-sm align-middle"
              data-h-datatable
              data-endpoint="{{ route('ui.datatables.activities') }}"
              data-page-length="20"
              data-length-menu="10,20,50,100"
              data-order-col="0"
              data-order-dir="desc"
              data-empty-text="Empty"
            >
              <thead>
                <tr>
                  <th data-col="id">ID</th>
                  <th data-col="created_at">Datetime</th>
                  <th data-col="user">User</th>
                  <th data-col="method">Method</th>
                  <th data-col="path">Path</th>
                  <th data-col="route_name">Route</th>
                  <th data-col="status">Status</th>
                  <th data-col="ip_address">IP</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="h-tab-panel" data-tab-panel="settings-security">
      <div class="h-card-soft mb-3">
        <div class="head">
          <div style="font-family:var(--fd);font-size:16px;font-weight:700;">Personal Security</div>
          <div class="h-muted" style="font-size:13px;">2FA and your channel preferences.</div>
        </div>
        <div class="body">
          <form method="POST" action="{{ route('settings.security') }}" data-spa>
            @csrf
            <div class="row g-3">
              <div class="col-md-6">
                <label class="h-label" style="display:block;">Telegram Chat ID</label>
                <input type="text" name="telegram_chat_id" class="form-control" value="{{ old('telegram_chat_id', auth()->user()->telegram_chat_id) }}" placeholder="e.g. 123456789">
              </div>
              <div class="col-md-6" style="padding-top:24px;">
                <div class="h-switch-wrap">
                  <label class="h-switch">
                    <input type="hidden" name="two_factor_enabled" value="0">
                    <input type="checkbox" name="two_factor_enabled" value="1" @checked(auth()->user()->two_factor_enabled)>
                    <span class="track"><span class="thumb"></span></span>
                    <span class="h-switch-text">Enable 2FA Login</span>
                  </label>
                  <label class="h-switch">
                    <input type="hidden" name="receive_in_app_notifications" value="0">
                    <input type="checkbox" name="receive_in_app_notifications" value="1" @checked(auth()->user()->receive_in_app_notifications)>
                    <span class="track"><span class="thumb"></span></span>
                    <span class="h-switch-text">In-App Notifications</span>
                  </label>
                  <label class="h-switch">
                    <input type="hidden" name="receive_telegram_notifications" value="0">
                    <input type="checkbox" name="receive_telegram_notifications" value="1" @checked(auth()->user()->receive_telegram_notifications)>
                    <span class="track"><span class="thumb"></span></span>
                    <span class="h-switch-text">Telegram Notifications</span>
                  </label>
                  <label class="h-switch">
                    <input type="hidden" name="browser_notifications_enabled" value="0">
                    <input type="checkbox" name="browser_notifications_enabled" value="1" @checked(auth()->user()->browser_notifications_enabled)>
                    <span class="track"><span class="thumb"></span></span>
                    <span class="h-switch-text">Browser Notifications</span>
                  </label>
                </div>
              </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
              <button type="submit" class="btn btn-primary" data-busy-text="Saving...">
                <i class="fa-solid fa-shield-halved me-2"></i>
                Save Security Preferences
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="h-tab-panel" data-tab-panel="settings-notifications">
      <div class="h-card-soft mb-3">
        <div class="head">
          <div style="font-family:var(--fd);font-size:16px;font-weight:700;">Broadcast Notifications</div>
          <div class="h-muted" style="font-size:13px;">Send in-app + Telegram messages by audience from UI.</div>
        </div>
        <div class="body">
          @can('manage notifications')
            <form method="POST" action="{{ route('notifications.broadcast') }}" data-spa>
              @csrf
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="h-label" style="display:block;">Title</label>
                  <input type="text" name="title" class="form-control" placeholder="System update" required>
                </div>
                <div class="col-md-6">
                  <label class="h-label" style="display:block;">Level</label>
                  <select name="level" class="form-select" data-h-select>
                    <option value="info">Info</option>
                    <option value="success">Success</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                  </select>
                </div>
                <div class="col-md-12">
                  <label class="h-label" style="display:block;">Message</label>
                  <textarea name="message" class="form-control" rows="3" placeholder="Write notification message..." required></textarea>
                </div>
                <div class="col-md-4">
                  <label class="h-label" style="display:block;">Audience</label>
                  <select name="audience" class="form-select" data-h-select>
                    <option value="admins">Admins Only</option>
                    <option value="all">All users</option>
                    <option value="role">By role</option>
                    <option value="users">Specific users</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="h-label" style="display:block;">Channels</label>
                  <div class="h-radio-stack" style="grid-template-columns:1fr;">
                    <label class="form-check">
                      <input class="form-check-input" type="checkbox" name="channels[]" value="in_app" checked>
                      <span class="form-check-label">In-app</span>
                    </label>
                    <label class="form-check">
                      <input class="form-check-input" type="checkbox" name="channels[]" value="telegram">
                      <span class="form-check-label">Telegram</span>
                    </label>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="h-label" style="display:block;">Role (if audience=role)</label>
                  <select name="role" class="form-select" data-h-select>
                    <option value="">Choose role</option>
                    @foreach($roleOptions as $roleName)
                      <option value="{{ $roleName }}">{{ strtoupper($roleName) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="h-label" style="display:block;">Users (if audience=users)</label>
                  <select name="user_ids[]" class="form-select" multiple data-select2-remote data-endpoint="{{ route('ui.options.leads') }}" data-placeholder="Search users..." data-min-input="1" data-dropdown-parent="#settings-main-tabs"></select>
                </div>
              </div>

              <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary" data-busy-text="Sending...">
                  <i class="fa-solid fa-paper-plane me-2"></i>
                  Send Broadcast
                </button>
              </div>
            </form>
          @else
            <div class="h-note">You need <code>manage notifications</code> permission to send broadcast messages.</div>
          @endcan
        </div>
      </div>

      <div class="h-card-soft mb-3">
        <div class="head">
          <div style="font-family:var(--fd);font-size:16px;font-weight:700;">Controller Notification Helper</div>
          <div class="h-muted" style="font-size:13px;">Use one global helper from any controller/service instead of UI automation rules.</div>
        </div>
        <div class="body">
          <pre><code>// Send to one user
app(\App\Support\Notifier::class)->toUser(
    $user,
    'Invoice Created',
    "Invoice {$invoice->number} created.",
    ['level' => 'success', 'channels' => ['in_app', 'telegram'], 'url' => route('invoices.show', $invoice)]
);

// Send to role
app(\App\Support\Notifier::class)->toRole(
    'manager',
    'Approval Required',
    'A new invoice is waiting for approval.',
    ['channels' => ['in_app']]
);</code></pre>
        </div>
      </div>
    </div>

    @if($canManageSettings)
      <div class="h-tab-panel" data-tab-panel="settings-system">
        @if(!$envWritable)
          <div class="alert alert-danger mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <strong>.env is not writable.</strong> Update file permissions first.
          </div>
        @endif

        <form method="POST" action="{{ route('settings.update') }}" data-spa>
          @csrf
          @foreach($sections as $sectionKey => $section)
            <div class="h-card-soft mb-3">
              <div class="head">
                <div style="font-family:var(--fd);font-size:16px;font-weight:700;">{{ $section['title'] }}</div>
                <div class="h-muted" style="font-size:13px;">{{ $section['description'] }}</div>
              </div>
              <div class="body">
                <div class="row g-3">
                  @foreach($fields as $key => $field)
                    @continue($field['section'] !== $sectionKey)
                    @php
                      $current = old($key, $values[$key] ?? ($field['default'] ?? ''));
                      $type = $field['type'] ?? 'text';
                    @endphp
                    <div class="col-md-6">
                      <label for="{{ $key }}" class="form-label h-label" style="display:block;">{{ $field['label'] }}</label>
                      @if($type === 'select')
                        <select id="{{ $key }}" name="{{ $key }}" class="form-select" data-h-select {{ ($field['required'] ?? false) ? 'required' : '' }}>
                          @foreach($field['options'] ?? [] as $option)
                            <option value="{{ $option }}" @selected((string) $current === (string) $option)>{{ strtoupper($option) }}</option>
                          @endforeach
                        </select>
                      @elseif($type === 'bool')
                        <select id="{{ $key }}" name="{{ $key }}" class="form-select" data-h-select {{ ($field['required'] ?? false) ? 'required' : '' }}>
                          <option value="true" @selected(in_array(strtolower((string) $current), ['1','true','on','yes'], true))>Enabled</option>
                          <option value="false" @selected(in_array(strtolower((string) $current), ['0','false','off','no'], true))>Disabled</option>
                        </select>
                      @else
                        <input id="{{ $key }}" name="{{ $key }}" type="{{ in_array($type, ['email','password','url','number','decimal'], true) ? ($type === 'decimal' ? 'number' : $type) : 'text' }}" value="{{ $current }}" class="form-control" {{ ($field['required'] ?? false) ? 'required' : '' }} autocomplete="off" @if($type === 'decimal') step="0.01" min="0" max="100" @endif>
                      @endif
                      @error($key)
                        <div class="h-error-msg mt-1">{{ $message }}</div>
                      @enderror
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          @endforeach

          <div class="d-flex justify-content-end gap-2 mt-3 mb-4">
            <button type="submit" class="btn btn-primary" data-busy-text="Saving..." @disabled(!$envWritable)>
              <i class="fa-solid fa-floppy-disk me-2"></i>
              Save Environment Config
            </button>
          </div>
        </form>
      </div>
    @endif
  </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
  const tabs = document.getElementById('settings-main-tabs');
  if (!tabs) return;

  const updateQuery = (tabId) => {
    const url = new URL(window.location.href);
    if (tabId) url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url.toString());
  };

  document.addEventListener('h:tabs:changed', function (event) {
    if (!event.detail || event.detail.container !== tabs) return;
    updateQuery(event.detail.tabId);
  });
})();
</script>
@endsection
