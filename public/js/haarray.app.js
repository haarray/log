(function ($, window, document) {
  'use strict';

  const HCore = {
    emit(name, detail = {}) {
      document.dispatchEvent(new CustomEvent(name, { detail }));
      if (window.HDebug && typeof window.HDebug.captureEvent === 'function') {
        window.HDebug.captureEvent(name, detail);
      }
    },
  };

  /* ── THEME ────────────────────────────────────────────── */
  const HTheme = {
    init() {
      const saved = localStorage.getItem('h_theme') || 'dark';
      this.apply(saved, false);

      $(document).on('click', '.h-theme-toggle', () => {
        this.apply(this.current() === 'dark' ? 'light' : 'dark');
      });
    },

    apply(theme, toast = true) {
      const finalTheme = theme === 'light' ? 'light' : 'dark';
      $('html').attr('data-theme', finalTheme);
      localStorage.setItem('h_theme', finalTheme);
      $('.h-theme-toggle .moon').toggle(finalTheme === 'dark');
      $('.h-theme-toggle .sun').toggle(finalTheme === 'light');
      if (toast) HToast.info('Switched to ' + finalTheme + ' mode');
    },

    current() {
      return $('html').attr('data-theme') === 'light' ? 'light' : 'dark';
    },
  };

  /* ── SIDEBAR ──────────────────────────────────────────── */
  const HSidebar = {
    storageKey: 'h_nav_group_state',
    compactStorageKey: 'h_sidebar_compact',

    init() {
      $(document).on('click', '.h-menu-toggle', () => this.toggle());
      $(document).on('click', '.h-sidebar-overlay', () => this.close());
      $(document).on('click', '[data-sidebar-collapse-toggle]', (event) => {
        event.preventDefault();
        this.toggleCompact();
      });
      $(document).on('click', '.h-nav-item, .h-nav-sub-item', (event) => {
        if ($(window).width() > 768) return;
        if ($(event.currentTarget).is('[data-nav-toggle]')) return;
        this.close();
      });

      $(document).on('click', '[data-nav-toggle]', (event) => {
        event.preventDefault();
        const key = String($(event.currentTarget).data('nav-toggle') || '').trim();
        if (!key) return;
        this.toggleGroup(key);
      });

      this.restoreGroupState();
      this.restoreCompactState();
      this.syncGroups();
      this._syncCompactToggleIcon();

      $(window).on('resize', () => {
        if ($(window).width() <= 768) {
          this.applyCompact(false, false);
        } else {
          this.restoreCompactState();
        }
      });
    },

    toggle() {
      $('#h-sidebar').hasClass('open') ? this.close() : this.open();
    },

    open() {
      $('#h-sidebar').addClass('open');
      $('.h-sidebar-overlay').addClass('show');
      $('body').css('overflow', 'hidden');
    },

    close() {
      $('#h-sidebar').removeClass('open');
      $('.h-sidebar-overlay').removeClass('show');
      $('body').css('overflow', '');
    },

    toggleCompact() {
      if ($(window).width() <= 768) {
        this.toggle();
        return;
      }

      const isCollapsed = $('body').hasClass('h-sidebar-compact');
      this.applyCompact(!isCollapsed);
    },

    applyCompact(compact, persist = true) {
      const shouldCompact = compact === true && $(window).width() > 768;
      $('body').toggleClass('h-sidebar-compact', shouldCompact);
      this._syncCompactToggleIcon();

      if (!persist) return;
      try {
        localStorage.setItem(this.compactStorageKey, shouldCompact ? '1' : '0');
      } catch (error) {
        // Ignore storage errors.
      }
    },

    restoreCompactState() {
      if ($(window).width() <= 768) return;

      let compact = false;
      try {
        compact = localStorage.getItem(this.compactStorageKey) === '1';
      } catch (error) {
        compact = false;
      }
      this.applyCompact(compact, false);
    },

    toggleGroup(key) {
      const group = document.querySelector('[data-nav-group="' + key + '"]');
      if (!group) return;
      const expanded = group.dataset.expanded === '1';
      group.dataset.manual = '1';
      group.dataset.expanded = expanded ? '0' : '1';
      this.persistGroupState();
      this.syncGroups();
    },

    syncGroups() {
      document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const hasActiveChild = Boolean(group.querySelector('.h-nav-sub-item.active'));
        const expandedByUser = group.dataset.expanded === '1';
        const manualOverride = group.dataset.manual === '1';
        const shouldOpen = manualOverride ? expandedByUser : (hasActiveChild || expandedByUser);

        group.classList.toggle('open', shouldOpen);

        const toggle = group.querySelector('[data-nav-toggle]');
        if (toggle) {
          toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
          toggle.classList.toggle('active', hasActiveChild);
        }
      });
    },

    restoreGroupState() {
      let payload = {};
      try {
        payload = JSON.parse(localStorage.getItem(this.storageKey) || '{}');
      } catch (error) {
        payload = {};
      }

      if (!payload || typeof payload !== 'object') return;

      document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const key = String(group.dataset.navGroup || '').trim();
        if (!key || !payload[key]) return;

        const state = payload[key];
        if (state && typeof state === 'object') {
          if (state.expanded === '1' || state.expanded === '0') {
            group.dataset.expanded = state.expanded;
          }
          if (state.manual === '1' || state.manual === '0') {
            group.dataset.manual = state.manual;
          }
        }
      });
    },

    persistGroupState() {
      const state = {};
      document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const key = String(group.dataset.navGroup || '').trim();
        if (!key) return;
        state[key] = {
          expanded: group.dataset.expanded === '1' ? '1' : '0',
          manual: group.dataset.manual === '1' ? '1' : '0',
        };
      });

      try {
        localStorage.setItem(this.storageKey, JSON.stringify(state));
      } catch (error) {
        // Ignore storage errors.
      }
    },

    _syncCompactToggleIcon() {
      const button = document.querySelector('[data-sidebar-collapse-toggle]');
      if (!button) return;
      const isCollapsed = document.body.classList.contains('h-sidebar-compact');
      button.setAttribute('title', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
      button.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
      button.innerHTML = isCollapsed
        ? '<i class="fa-solid fa-angles-right"></i>'
        : '<i class="fa-solid fa-angles-left"></i>';
    },
  };

  /* ── TOAST ────────────────────────────────────────────── */
  const HToast = {
    init() {
      if (!$('#h-toasts').length) $('body').append('<div id="h-toasts"></div>');
    },

    show(message, type = 'info', duration = 3800) {
      const safeMessage = String(message || '').trim() || 'Done';
      const tone = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
      const icons = {
        success: 'fa-circle-check',
        error: 'fa-circle-xmark',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info',
      };
      const titles = {
        success: 'Success',
        error: 'Error',
        warning: 'Warning',
        info: 'Info',
      };

      const $toast = $(`
        <div class="h-toast h-toast-${tone}" role="status" aria-live="polite" style="--h-toast-duration:${Math.max(1200, duration)}ms;">
          <div class="h-toast-icon"><i class="fa-solid ${icons[tone]}"></i></div>
          <div class="h-toast-body">
            <div class="h-toast-title">${titles[tone]}</div>
            <div class="h-toast-msg">${this._escape(safeMessage)}</div>
          </div>
          <button type="button" class="h-toast-close" aria-label="Dismiss">
            <i class="fa-solid fa-xmark"></i>
          </button>
          <span class="h-toast-progress"></span>
        </div>
      `);

      $('#h-toasts').append($toast);
      requestAnimationFrame(() => $toast.addClass('show'));

      const removeToast = () => {
        $toast.removeClass('show');
        setTimeout(() => $toast.remove(), 300);
      };

      $toast.find('.h-toast-close').on('click', removeToast);
      setTimeout(removeToast, duration);
    },

    success(message, duration) {
      this.show(message, 'success', duration);
    },
    error(message, duration) {
      this.show(message, 'error', duration);
    },
    warning(message, duration) {
      this.show(message, 'warning', duration);
    },
    info(message, duration) {
      this.show(message, 'info', duration);
    },

    _escape(input) {
      return String(input ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },
  };

  /* ── MODAL ────────────────────────────────────────────── */
  const HModal = {
    init() {
      $(document).on('click', '[data-modal-open]', function () {
        HModal.open($(this).data('modal-open'));
      });

      $(document).on('click', '.h-modal-close, [data-modal-close]', function () {
        HModal.close($(this).closest('.h-modal-overlay').attr('id'));
      });

      $(document).on('click', '.h-modal-overlay', function (event) {
        if ($(event.target).hasClass('h-modal-overlay')) {
          HModal.close($(this).attr('id'));
        }
      });

      $(document).on('keydown', (event) => {
        if (event.key === 'Escape') HModal.closeAll();
      });
    },

    open(id) {
      if (!id) return;
      $('#' + id).addClass('show');
      $('body').css('overflow', 'hidden');
    },

    close(id) {
      if (!id) return;
      $('#' + id).removeClass('show');
      if (!$('.h-modal-overlay.show').length) $('body').css('overflow', '');
    },

    closeAll() {
      $('.h-modal-overlay').removeClass('show');
      $('body').css('overflow', '');
    },
  };

  /* ── NOTIFICATIONS ───────────────────────────────────── */
  const HNotify = {
    _pollTimer: null,
    _isLoading: false,
    _bootstrapped: false,
    _knownUnreadIds: new Set(),
    _lastAttentionAt: 0,

    init() {
      if (!$('#h-notif-tray').length) return;

      $(document).on('click', '[data-notif-toggle]', (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.toggle();
      });

      $(document).on('click', '[data-notif-close]', (event) => {
        event.preventDefault();
        this.close();
      });

      $(document).on('click', (event) => {
        const $tray = $('#h-notif-tray');
        if (!$tray.length || !$tray.hasClass('show')) return;
        if ($(event.target).closest('#h-notif-tray, [data-notif-toggle]').length) return;
        this.close();
      });

      $(document).on('keydown', (event) => {
        if (event.key === 'Escape') this.close();
      });

      $(document).on('click', '[data-notif-refresh]', (event) => {
        event.preventDefault();
        this.refresh(false);
      });

      $(document).on('click', '[data-notif-mark-all]', (event) => {
        event.preventDefault();
        this.markAllRead();
      });

      $(document).on('click', '[data-notif-mark-read]', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const id = String($(event.currentTarget).data('notifMarkRead') || '').trim();
        if (!id) return;
        this.markRead(id);
      });

      $(document).on('click', '.h-notif-item[data-notif-id]', (event) => {
        if ($(event.target).closest('[data-notif-mark-read]').length) return;
        const itemEl = event.currentTarget;
        const id = itemEl.getAttribute('data-notif-id');
        if (!id) return;

        this.markRead(id);

        const targetUrl = itemEl.getAttribute('data-notif-url');
        if (!targetUrl) return;

        if (window.HUtils && HUtils.isSameOrigin(targetUrl) && window.HSPA) {
          HSPA.navigate(targetUrl, true);
        } else {
          window.location.href = targetUrl;
        }
      });

      document.addEventListener('hspa:afterLoad', () => this.refresh(true));

      this.refresh(true);
      const autoPoll = Number($('body').data('notificationsAutoPoll') || 0) === 1;
      if (autoPoll) {
        const pollSeconds = Number($('body').data('notificationsPollSeconds') || 20);
        const safePollMs = Math.max(10, Math.min(pollSeconds, 300)) * 1000;
        this._pollTimer = window.setInterval(() => this.refresh(true), safePollMs);
      }
    },

    open() {
      $('#h-notif-tray').addClass('show').attr('aria-hidden', 'false');
      this._requestBrowserPermission();
    },

    close() {
      $('#h-notif-tray').removeClass('show').attr('aria-hidden', 'true');
    },

    toggle() {
      const $tray = $('#h-notif-tray');
      if (!$tray.length) return;
      if ($tray.hasClass('show')) {
        this.close();
        return;
      }

      this.open();
      this.refresh(true);
    },

    refresh(silent = true) {
      const endpoint = $('body').data('notificationsFeedUrl');
      if (!endpoint || this._isLoading || !window.HApi) return;
      if (silent && document.hidden) return;

      this._isLoading = true;
      HApi.get(endpoint)
        .done((payload) => {
          this.render(payload || {});
        })
        .fail(() => {
          if (!silent) HToast.error('Unable to load notifications.');
        })
        .always(() => {
          this._isLoading = false;
        });
    },

    render(payload) {
      const unreadCount = Number(payload.unread_count || 0);
      const items = Array.isArray(payload.items) ? payload.items : [];

      this._updateBell(unreadCount);

      const $list = $('#h-notif-list');
      if (!$list.length) return;

      if (!items.length) {
        this._knownUnreadIds = new Set();
        this._bootstrapped = true;
        $list.html(`
          <div class="h-notif-empty">
            <i class="fa-regular fa-bell-slash"></i>
            <span>No notifications yet.</span>
          </div>
        `);
        return;
      }

      const levelIcons = {
        info: 'fa-circle-info',
        success: 'fa-circle-check',
        warning: 'fa-triangle-exclamation',
        error: 'fa-circle-exclamation',
      };

      const rows = items
        .map((item) => {
          const level = String(item.level || 'info');
          const icon = levelIcons[level] || levelIcons.info;
          const isUnread = !item.read;
          const title = this._escape(item.title || 'Notification');
          const message = this._escape(item.message || '');
          const time = this._escape(item.time || '');
          const url = item.url ? this._escape(item.url) : '';

          return `
            <div class="h-notif-item ${isUnread ? 'is-unread' : ''}" data-notif-id="${this._escape(item.id || '')}" ${url ? `data-notif-url="${url}"` : ''}>
              <span class="h-notif-icon"><i class="fa-solid ${icon}"></i></span>
              <div style="min-width:0;">
                <div class="h-notif-item-title">${title}</div>
                ${message ? `<div class="h-notif-item-meta">${message}</div>` : ''}
                <div class="h-notif-item-time">${time}</div>
              </div>
              ${isUnread ? `
                <button type="button" class="h-notif-read-btn" data-notif-mark-read="${this._escape(item.id || '')}" title="Mark as read" aria-label="Mark as read">
                  <i class="fa-solid fa-check"></i>
                </button>
              ` : ''}
            </div>
          `;
        })
        .join('');

      $list.html(rows);
      this._emitNewAlerts(items);
    },

    markRead(id) {
      const template = $('body').data('notificationReadUrlTemplate');
      if (!template || !id || !window.HApi) return;

      const readUrl = String(template).replace('__ID__', encodeURIComponent(String(id)));

      HApi.post(readUrl).done(() => {
        const $item = $('.h-notif-item[data-notif-id="' + id + '"]');
        $item.removeClass('is-unread');
        this.refresh(true);
      });
    },

    markAllRead() {
      const endpoint = String($('body').data('notificationReadAllUrl') || '').trim();
      if (!endpoint || !window.HApi) return;

      HApi.post(endpoint)
        .done((payload) => {
          const marked = Number(payload && payload.marked ? payload.marked : 0);
          if (marked > 0) {
            HToast.success('Marked ' + marked + ' notification(s) as read.');
          } else {
            HToast.info('No unread notifications.');
          }
          this.refresh(true);
        })
        .fail(() => {
          HToast.error('Unable to mark notifications as read.');
        });
    },

    _updateBell(unreadCount) {
      const $dot = $('.h-notif-dot').first();
      if (!$dot.length) return;

      if (unreadCount > 0) {
        $dot.removeClass('is-hidden');
        $dot.attr('title', unreadCount + ' unread notifications');
      } else {
        $dot.addClass('is-hidden');
        $dot.removeAttr('title');
      }
    },

    _emitNewAlerts(items) {
      const unread = items.filter((item) => !item.read && item.id);
      const nextUnreadIds = new Set(unread.map((item) => String(item.id)));

      if (!this._bootstrapped) {
        this._knownUnreadIds = nextUnreadIds;
        this._bootstrapped = true;
        return;
      }

      const newItems = unread.filter((item) => !this._knownUnreadIds.has(String(item.id)));
      this._knownUnreadIds = nextUnreadIds;

      if (!newItems.length) return;

      const first = newItems[0];
      HToast.info('New notification: ' + String(first.title || 'Update'));
      this._triggerAttention();
      this._notifyBrowser(first);
    },

    _notifyBrowser(item) {
      if (!this._browserNotifyEnabled()) return;
      if (!('Notification' in window)) return;
      if (Notification.permission !== 'granted') return;

      const iconUrl = String($('body').data('faviconUrl') || '/favicon.ico');

      const notification = new Notification(String(item.title || 'HariLog Notification'), {
        body: String(item.message || ''),
        icon: iconUrl,
      });

      notification.onclick = () => {
        window.focus();
        if (item.url && window.HSPA && HUtils.isSameOrigin(item.url)) {
          HSPA.navigate(item.url, true);
        } else if (item.url) {
          window.location.href = item.url;
        }
        notification.close();
      };
    },

    _requestBrowserPermission() {
      if (!this._browserNotifyEnabled()) return;
      if (!('Notification' in window)) return;
      if (Notification.permission !== 'default') return;

      Notification.requestPermission().catch(() => {
        // Ignore permission prompt failures.
      });
    },

    _browserNotifyEnabled() {
      return Number($('body').data('browserNotifyEnabled') || 0) === 1;
    },

    _triggerAttention() {
      const now = Date.now();
      if (now - this._lastAttentionAt < 3000) return;
      this._lastAttentionAt = now;

      try {
        if (navigator && typeof navigator.vibrate === 'function') {
          navigator.vibrate([130, 45, 130]);
        }
      } catch (error) {
        // Ignore unsupported vibration API.
      }

      this._playBeep();
    },

    _playBeep() {
      const customSoundUrl = String($('body').data('notificationSoundUrl') || '').trim();
      if (customSoundUrl !== '') {
        try {
          if (!this._soundPlayer || this._soundPlayer.src !== customSoundUrl) {
            this._soundPlayer = new Audio(customSoundUrl);
          }
          this._soundPlayer.currentTime = 0;
          this._soundPlayer.volume = 0.65;
          this._soundPlayer.play().catch(() => {
            this._playOscillatorBeep();
          });
          return;
        } catch (error) {
          // Fall through to oscillator beep.
        }
      }
      this._playOscillatorBeep();
    },

    _playOscillatorBeep() {
      try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (typeof AudioContextClass !== 'function') return;

        const context = new AudioContextClass();
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 1120;
        gain.gain.value = 0.03;

        oscillator.connect(gain);
        gain.connect(context.destination);

        const now = context.currentTime;
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(0.03, now + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.25);

        oscillator.start(now);
        oscillator.stop(now + 0.26);

        oscillator.onended = () => {
          try {
            context.close();
          } catch (closeError) {
            // Ignore close errors.
          }
        };
      } catch (error) {
        // Ignore autoplay or audio context restrictions.
      }
    },

    _escape(input) {
      return String(input ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },
  };

  /* ── DEV HOT RELOAD ───────────────────────────────── */
  const HHotReload = {
    _source: null,
    _signature: '',
    _streamEndpoint: '',

    init() {
      const enabled = Number($('body').data('hotReloadEnabled') || 0) === 1;
      this._streamEndpoint = String($('body').data('hotReloadStreamUrl') || '').trim();

      if (!enabled || !this._streamEndpoint || typeof window.EventSource !== 'function') {
        return;
      }

      this.connect();

      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          this.close();
          return;
        }
        this.connect(true);
      });

      window.addEventListener('beforeunload', () => this.close());
    },

    connect(force = false) {
      if (
        !force &&
        this._source &&
        this._source.readyState !== window.EventSource.CLOSED
      ) {
        return;
      }

      this.close();

      const endpoint = this._streamUrl();
      if (!endpoint) return;

      try {
        this._source = new window.EventSource(endpoint, { withCredentials: true });
      } catch (error) {
        this._source = null;
        return;
      }

      this._source.addEventListener('signature', (event) => {
        const payload = this._parsePayload(event && event.data ? event.data : '');
        const nextSignature = String(payload && payload.signature ? payload.signature : '').trim();
        if (!nextSignature) return;

        if (this._signature && this._signature !== nextSignature) {
          window.location.reload();
          return;
        }

        this._signature = nextSignature;
      });

      this._source.addEventListener('error', () => {
        // EventSource reconnects automatically. Keep silent.
      });
    },

    close() {
      if (!this._source) return;
      this._source.close();
      this._source = null;
    },

    _streamUrl() {
      const endpoint = String(this._streamEndpoint || '').trim();
      if (!endpoint) return '';
      if (!this._signature) return endpoint;
      const joiner = endpoint.includes('?') ? '&' : '?';
      return endpoint + joiner + 'sig=' + encodeURIComponent(this._signature);
    },

    _parsePayload(raw) {
      try {
        return JSON.parse(String(raw || '{}'));
      } catch (error) {
        return {};
      }
    },
  };

  /* ── GLOBAL SEARCH ──────────────────────────────────── */
  const HSearch = {
    _timer: null,
    _debounceMs: 180,

    init() {
      if (!$('#h-global-search-modal').length) return;

      const configuredDebounce = Number($('body').data('globalSearchDebounce') || 180);
      this._debounceMs = Number.isFinite(configuredDebounce)
        ? Math.max(80, Math.min(configuredDebounce, 1500))
        : 180;

      $(document).on('click', '[data-global-search-open]', (event) => {
        event.preventDefault();
        this.open();
      });

      $(document).on('keydown', (event) => {
        const isCombo = (event.metaKey || event.ctrlKey) && String(event.key || '').toLowerCase() === 'k';
        if (!isCombo) return;
        event.preventDefault();
        this.open();
      });

      $(document).on('input', '#h-global-search-input', (event) => {
        const query = String(event.currentTarget.value || '').trim();
        window.clearTimeout(this._timer);
        this._timer = window.setTimeout(() => this.search(query), this._debounceMs);
      });

      $(document).on('click', '[data-search-open-url]', (event) => {
        event.preventDefault();
        const url = String($(event.currentTarget).data('searchOpenUrl') || '').trim();
        if (!url) return;

        HModal.close('h-global-search-modal');
        if (window.HSPA && HUtils.isSameOrigin(url)) {
          HSPA.navigate(url, true);
          return;
        }
        window.location.href = url;
      });
    },

    open() {
      HModal.open('h-global-search-modal');
      const input = document.getElementById('h-global-search-input');
      if (!input) return;
      setTimeout(() => input.focus(), 40);
      if (!String(input.value || '').trim()) {
        this.render([]);
      }
    },

    search(query) {
      if (!query || query.length < 2) {
        this.render([]);
        return;
      }

      const endpoint = String($('body').data('globalSearchUrl') || '').trim();
      if (!endpoint || !window.HApi) return;

      HApi.get(endpoint, { q: query, limit: 20, per_source: 8 })
        .done((payload) => {
          const items = Array.isArray(payload.items) ? payload.items : [];
          this.render(items);
        })
        .fail(() => {
          this.render([]);
          HToast.error('Search failed. Please try again.');
        });
    },

    render(items) {
      const $list = $('#h-global-search-results');
      if (!$list.length) return;

      if (!Array.isArray(items) || items.length === 0) {
        $list.html('<div class="h-notif-empty"><i class="fa-solid fa-magnifying-glass"></i><span>No results yet.</span></div>');
        return;
      }

      const rows = items.map((item) => {
        const title = this._escape(item.title || 'Result');
        const subtitle = this._escape(item.subtitle || '');
        const url = this._escape(item.url || '');
        const icon = this._escape(item.icon || 'fa-solid fa-file-lines');

        return `
          <button type="button" class="h-search-row" data-search-open-url="${url}">
            <span class="h-search-row-icon"><i class="${icon}"></i></span>
            <span class="h-search-row-copy">
              <span class="h-search-row-title">${title}</span>
              ${subtitle ? `<span class="h-search-row-sub">${subtitle}</span>` : ''}
            </span>
            <span class="h-search-row-arrow"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
          </button>
        `;
      }).join('');

      $list.html(rows);
    },

    _escape(input) {
      return String(input ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },
  };

  /* ── MEDIA MANAGER ──────────────────────────────────── */
  const HMediaManager = {
    _targetInputId: '',
    _selectionHandler: null,
    _assetPromise: null,
    _fileInputSeed: 0,

    init() {
      const hasModal = Boolean(document.getElementById('h-media-manager-modal'));
      const hasPage = Boolean(document.getElementById('settings-media-elfinder'));
      if (!hasModal && !hasPage) return;

      $(document).on('click', '[data-media-manager-open]', (event) => {
        event.preventDefault();
        this._targetInputId = String($(event.currentTarget).data('mediaTarget') || '').trim();
        this.open();
      });

      this.enhanceFileInputs(document);
      this.mountPage();

      document.addEventListener('hspa:afterSwap', () => {
        this.enhanceFileInputs(document);
        this.mountPage();
      });
    },

    open(options = {}) {
      if (!document.getElementById('h-media-manager-modal')) return;
      const opts = options && typeof options === 'object' ? options : {};
      if (Object.prototype.hasOwnProperty.call(opts, 'targetInputId')) {
        this._targetInputId = String(opts.targetInputId || '').trim();
      }
      this._selectionHandler = typeof opts.onSelect === 'function' ? opts.onSelect : null;
      this._syncTargetNote();
      HModal.open('h-media-manager-modal');
      window.setTimeout(() => this.mountPicker(), 40);
    },

    mountPage() {
      const container = document.getElementById('settings-media-elfinder');
      if (!container) return;
      this._mountElfinder(container, false);
    },

    mountPicker() {
      const container = document.getElementById('h-media-manager-elfinder');
      if (!container) return;
      this._mountElfinder(container, true);
    },

    enhanceFileInputs(root) {
      const scope = root && root.querySelectorAll ? root : document;
      const inputs = Array.from(scope.querySelectorAll('input[type="file"]:not([data-media-enhanced])'));
      if (!inputs.length) return;

      inputs.forEach((input) => {
        if (!(input instanceof HTMLInputElement)) return;
        if (input.closest('#h-media-manager-modal')) return;
        if (input.hidden || input.classList.contains('d-none') || input.closest('.d-none')) return;
        if (input.dataset.editorMediaUpload === '1') return;

        input.dataset.mediaEnhanced = '1';
        input.classList.add('h-file-native');
        if (!input.classList.contains('form-control')) {
          input.classList.add('form-control');
        }

        const hiddenId = this._ensureHiddenTarget(input);
        const holder = document.createElement('div');
        holder.className = 'h-media-input-tools mt-2 d-flex flex-column gap-1';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary btn-sm align-self-start';
        button.setAttribute('data-media-manager-open', '');
        button.setAttribute('data-media-target', hiddenId);
        button.innerHTML = '<i class="fa-solid fa-photo-film me-2"></i>Choose from Media Library';

        const hint = document.createElement('small');
        hint.className = 'text-muted';
        hint.id = hiddenId + '-hint';
        hint.textContent = 'Optional: use an existing file URL from Media Library.';

        holder.appendChild(button);
        holder.appendChild(hint);
        input.insertAdjacentElement('afterend', holder);

        const hidden = document.getElementById(hiddenId);
        if (hidden && hidden instanceof HTMLInputElement) {
          hidden.addEventListener('change', () => {
            hint.textContent = hidden.value
              ? ('Media URL selected: ' + hidden.value)
              : 'Optional: use an existing file URL from Media Library.';
          });
        }
      });
    },

    applySelection(url, file = null) {
      const selectedUrl = String(url || '').trim();
      if (!selectedUrl) return;

      if (typeof this._selectionHandler === 'function') {
        const handler = this._selectionHandler;
        this._selectionHandler = null;
        try {
          handler(selectedUrl, file);
        } catch (error) {
          if (window.console && typeof window.console.warn === 'function') {
            window.console.warn('Media selection handler failed:', error);
          }
        }
        HToast.success('Media selected.');
        HModal.close('h-media-manager-modal');
        return;
      }

      if (!this._targetInputId) {
        if (navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          navigator.clipboard.writeText(selectedUrl)
            .then(() => HToast.success('Media URL copied to clipboard.'))
            .catch(() => HToast.info('Media selected.'));
        } else {
          HToast.info('Media selected.');
        }
        return;
      }

      const target = document.getElementById(this._targetInputId);
      if (!target || !('value' in target)) {
        HToast.warning('Target input not found for selected media.');
        return;
      }

      target.value = selectedUrl;
      target.dispatchEvent(new Event('input', { bubbles: true }));
      target.dispatchEvent(new Event('change', { bubbles: true }));
      HToast.success('Media selected.');
      HModal.close('h-media-manager-modal');
    },

    _mountElfinder(container, pickerMode) {
      const rawConnectorUrl = String(container.dataset.connectorUrl || '').trim();
      const connectorCandidates = this._resolveConnectorUrls(rawConnectorUrl);
      if (!connectorCandidates.length) {
        container.innerHTML = '<div class="h-note">Media connector is not configured.</div>';
        return;
      }

      this.ensureAssets()
        .then(() => this._pickConnectorUrl(connectorCandidates))
        .then((resolvedConnectorUrl) => {
          if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.elfinder !== 'function') {
            throw new Error('elFinder did not load correctly.');
          }

          const $container = window.jQuery(container);
          const mounted = String(container.dataset.elfinderMounted || '') === '1';
          if (mounted) {
            const instance = $container.elfinder('instance');
            if (instance && typeof instance.exec === 'function') {
              instance.exec('reload');
            }
            return;
          }

          const connectorUrl = String(resolvedConnectorUrl || container.dataset.elfinderConnectorResolved || connectorCandidates[0] || '').trim();
          if (!connectorUrl) {
            throw new Error('Media connector URL is empty.');
          }
          container.dataset.elfinderConnectorResolved = connectorUrl;

          const readOnly = String(container.dataset.readOnly || '0') === '1';
          const token = String((document.querySelector('meta[name="csrf-token"]') || {}).content || '');
          const disabled = readOnly
            ? ['upload', 'mkdir', 'mkfile', 'rename', 'rm', 'cut', 'paste', 'duplicate', 'archive', 'extract', 'resize', 'edit']
            : [];

          const toolbar = readOnly
            ? [
              ['back', 'forward'],
              ['reload'],
              ['home', 'up'],
              ['open', 'quicklook', 'download', 'getfile'],
              ['search'],
              ['view', 'sort'],
              ['info'],
            ]
            : [
              ['back', 'forward'],
              ['reload'],
              ['home', 'up'],
              ['mkdir', 'upload'],
              ['open', 'quicklook', 'download', 'getfile'],
              ['copy', 'cut', 'paste', 'duplicate'],
              ['rm', 'rename'],
              ['archive', 'extract', 'resize'],
              ['search'],
              ['view', 'sort'],
              ['info'],
            ];

          const baseOptions = {
            url: connectorUrl,
            customData: token ? { _token: token } : {},
            useBrowserHistory: false,
            cssAutoLoad: false,
            rememberLastDir: true,
            resizable: false,
            height: pickerMode ? Math.max(420, window.innerHeight - 280) : Math.max(560, window.innerHeight - 210),
            disabled,
            uiOptions: {
              toolbar,
            },
            commandsOptions: {
              getfile: {
                folders: false,
                multiple: false,
                onlyURL: false,
                oncomplete: pickerMode ? 'close' : '',
              },
            },
          };

          if (pickerMode) {
            baseOptions.getFileCallback = (file) => {
              const url = String(file && file.url ? file.url : '').trim();
              if (!url) {
                HToast.warning('Selected item has no public URL.');
                return;
              }
              this.applySelection(url, file);
            };
          }

          $container.elfinder(baseOptions);
          container.dataset.elfinderMounted = '1';
        })
        .catch((error) => {
          this._assetPromise = null;
          const reason = String(error && error.message ? error.message : 'Unknown initialization error');
          container.innerHTML = '<div class="h-note">Unable to load media manager: ' + this._escape(reason) + '.</div>';
          if (window.console && typeof window.console.error === 'function') {
            window.console.error('Media manager init failed:', error);
          }
          HToast.error('Media manager failed to initialize.');
        });
    },

    ensureAssets() {
      if (this._assetPromise) return this._assetPromise;
      const body = document.body;
      const uiCssCandidates = this._resolveAssetUrls(
        String((body && body.dataset ? body.dataset.elfinderUiCssUrl : '') || ''),
        'vendor/elfinder/jquery-ui/jquery-ui.min.css'
      );
      const uiJsCandidates = this._resolveAssetUrls(
        String((body && body.dataset ? body.dataset.elfinderUiJsUrl : '') || ''),
        'vendor/elfinder/jquery-ui/jquery-ui.min.js'
      );
      const fmCssCandidates = this._resolveAssetUrls(
        String((body && body.dataset ? body.dataset.elfinderCssUrl : '') || ''),
        'vendor/elfinder/elfinder/css/elfinder.min.css'
      );
      const fmThemeCssCandidates = this._resolveAssetUrls(
        String((body && body.dataset ? body.dataset.elfinderThemeCssUrl : '') || ''),
        'vendor/elfinder/elfinder/css/theme.css'
      );
      const fmJsCandidates = this._resolveAssetUrls(
        String((body && body.dataset ? body.dataset.elfinderJsUrl : '') || ''),
        'vendor/elfinder/elfinder/js/elfinder.min.js'
      );

      this._assetPromise = Promise.resolve()
        .then(() => this._ensureStyle('h-elfinder-jquery-ui-css', uiCssCandidates))
        .then(() => this._ensureStyle('h-elfinder-css', fmCssCandidates))
        .then(() => this._ensureStyle('h-elfinder-theme-css', fmThemeCssCandidates))
        .then(() => this._ensureScript('h-elfinder-jquery-ui-js', uiJsCandidates, () => {
          const jq = window.jQuery;
          return Boolean(
            jq &&
            jq.ui &&
            jq.ui.draggable &&
            jq.ui.droppable &&
            jq.ui.resizable &&
            jq.ui.selectable &&
            jq.ui.button &&
            jq.ui.slider
          );
        }))
        .then(() => this._ensureScript('h-elfinder-js', fmJsCandidates, () => {
          const jq = window.jQuery;
          return Boolean(jq && jq.fn && typeof jq.fn.elfinder === 'function');
        }));

      return this._assetPromise;
    },

    _ensureStyle(id, hrefCandidates) {
      const urls = Array.isArray(hrefCandidates) ? hrefCandidates : [hrefCandidates];
      const cleanUrls = urls
        .map((url) => String(url || '').trim())
        .filter(Boolean);

      if (!cleanUrls.length) {
        return Promise.reject(new Error('Style URL is missing.'));
      }

      const byId = document.getElementById(id);
      if (byId && byId.tagName === 'LINK') {
        const currentHref = String(byId.getAttribute('href') || '').trim();
        if (cleanUrls.includes(currentHref)) return Promise.resolve();
        byId.remove();
      }

      const existing = cleanUrls.find((url) => document.querySelector('link[href="' + url + '"]'));
      if (existing) return Promise.resolve();

      return this._loadStyleWithFallback(id, cleanUrls);
    },

    _ensureScript(id, srcCandidates, validator) {
      const urls = Array.isArray(srcCandidates) ? srcCandidates : [srcCandidates];
      const cleanUrls = urls
        .map((url) => String(url || '').trim())
        .filter(Boolean);

      if (!cleanUrls.length) {
        return Promise.reject(new Error('Script URL is missing.'));
      }

      const byId = document.getElementById(id);
      if (byId && byId.tagName === 'SCRIPT') {
        const currentSrc = String(byId.getAttribute('src') || '').trim();
        if (cleanUrls.includes(currentSrc) && byId.dataset.hLoaded === '1' && (!validator || validator())) {
          return Promise.resolve();
        }
        byId.remove();
      }

      if (validator && !validator()) {
        cleanUrls.forEach((url) => {
          const existing = document.querySelector('script[src="' + url + '"]');
          if (existing && existing.id !== id) {
            existing.remove();
          }
        });
      }

      const loaded = cleanUrls.find((url) => {
        const existing = document.querySelector('script[src="' + url + '"]');
        return existing && existing.dataset.hLoaded === '1' && (!validator || validator());
      });
      if (loaded) return Promise.resolve();

      return this._loadScriptWithFallback(id, cleanUrls, 0, validator);
    },

    _loadStyleWithFallback(id, urls, idx = 0) {
      if (idx >= urls.length) {
        return Promise.reject(new Error('Failed to load style: ' + urls.join(', ')));
      }

      const href = urls[idx];
      return new Promise((resolve, reject) => {
        const style = document.createElement('link');
        style.id = id;
        style.rel = 'stylesheet';
        style.href = href;
        style.onload = () => resolve();
        style.onerror = () => {
          style.remove();
          reject(new Error('Failed to load style: ' + href));
        };
        document.head.appendChild(style);
      }).catch(() => this._loadStyleWithFallback(id, urls, idx + 1));
    },

    _loadScriptWithFallback(id, urls, idx = 0, validator = null) {
      if (idx >= urls.length) {
        return Promise.reject(new Error('Failed to load script: ' + urls.join(', ')));
      }

      const src = urls[idx];
      return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.id = id;
        script.src = src;
        script.async = false;
        script.onload = () => {
          if (validator && !validator()) {
            script.remove();
            reject(new Error('Script loaded but did not initialize: ' + src));
            return;
          }
          script.dataset.hLoaded = '1';
          resolve();
        };
        script.onerror = () => {
          script.remove();
          reject(new Error('Failed to load script: ' + src));
        };
        document.body.appendChild(script);
      }).catch(() => this._loadScriptWithFallback(id, urls, idx + 1, validator));
    },

    _resolveAssetUrls(preferredUrl, relativePath) {
      const urls = [];
      const push = (value) => {
        const url = String(value || '').trim();
        if (!url || urls.includes(url)) return;
        urls.push(url);
      };

      push(preferredUrl);

      const rel = String(relativePath || '').replace(/^\/+/, '');
      if (!rel) return urls;

      const origin = String(window.location.origin || '').trim();
      const basePrefix = this._inferAppBasePrefix();
      const publicRel = 'public/' + rel;

      if (origin) {
        push(origin + '/' + rel);
        if (basePrefix !== '') {
          push(origin + basePrefix + '/' + rel);
        }
        push(origin + '/' + publicRel);
        if (basePrefix !== '') {
          push(origin + basePrefix + '/' + publicRel);
        }
      }

      push('/' + rel);
      if (basePrefix !== '') {
        push(basePrefix + '/' + rel);
      }
      push('/' + publicRel);
      if (basePrefix !== '') {
        push(basePrefix + '/' + publicRel);
      }

      return urls;
    },

    _inferAppBasePrefix() {
      const pathname = String(window.location.pathname || '/');
      const segments = pathname.split('/').filter(Boolean);
      if (!segments.length) return '';

      const routeRoots = new Set(['settings', 'dashboard', 'docs', 'login', 'register', '2fa', 'auth', 'notifications', 'ui']);
      const idx = segments.findIndex((segment) => routeRoots.has(segment.toLowerCase()));
      if (idx <= 0) return '';

      return '/' + segments.slice(0, idx).join('/');
    },

    _resolveConnectorUrls(preferredUrl) {
      const urls = [];
      const push = (value) => {
        const url = String(value || '').trim();
        if (!url || urls.includes(url)) return;
        urls.push(url);
      };

      const basePrefix = this._inferAppBasePrefix();
      const origin = String(window.location.origin || '').trim();
      const sameOriginPath = (basePrefix ? basePrefix : '') + '/settings/media/connector';

      push(sameOriginPath);
      push('/settings/media/connector');
      if (origin) {
        push(origin + sameOriginPath);
        push(origin + '/settings/media/connector');
      }
      push(preferredUrl);

      return urls;
    },

    _pickConnectorUrl(urlCandidates) {
      const urls = Array.isArray(urlCandidates)
        ? urlCandidates.map((url) => String(url || '').trim()).filter(Boolean)
        : [];

      if (!urls.length) {
        return Promise.reject(new Error('No connector URL candidates available.'));
      }

      const tryAt = (idx) => {
        if (idx >= urls.length) {
          throw new Error('No working media connector endpoint found.');
        }

        const candidate = urls[idx];
        const probeUrl = candidate + (candidate.includes('?') ? '&' : '?') + 'cmd=open&init=1&tree=1';

        return window.fetch(probeUrl, {
          method: 'GET',
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
          },
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error('HTTP ' + response.status + ' on ' + candidate);
            }
            return response.text().then((text) => ({ text, candidate }));
          })
          .then(({ text, candidate: resolved }) => {
            const payload = String(text || '').trim();
            if (!payload.startsWith('{') && !payload.startsWith('[')) {
              throw new Error('Invalid connector response from ' + resolved);
            }
            return resolved;
          })
          .catch(() => tryAt(idx + 1));
      };

      return Promise.resolve()
        .then(() => tryAt(0))
        .then((resolved) => {
          return resolved;
        });
    },

    _escape(input) {
      return String(input ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },

    _ensureHiddenTarget(fileInput) {
      const sourceId = String(fileInput.id || '').trim();
      const hiddenId = sourceId
        ? (sourceId + '-media-url')
        : ('h-media-file-url-' + (++this._fileInputSeed));

      let hidden = document.getElementById(hiddenId);
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.id = hiddenId;
        if (fileInput.name) {
          hidden.name = fileInput.name + '_media_url';
        }
        fileInput.insertAdjacentElement('afterend', hidden);
      }

      return hiddenId;
    },

    _syncTargetNote() {
      const note = document.getElementById('h-media-manager-target-note');
      if (!note) return;

      if (!this._targetInputId) {
        note.hidden = false;
        note.textContent = this._selectionHandler
          ? 'Selecting media for editor. Double-click a file or use toolbar action to inject URL.'
          : 'Tip: right-click a file and choose "Get file URL", or use the toolbar select button.';
        return;
      }

      note.hidden = false;
      note.textContent = 'Selecting file for #' + this._targetInputId + '. Use "Get file URL" to inject.';
    },
  };

  /* ── PWA ─────────────────────────────────────────────── */
  const HPWA = {
    deferredPrompt: null,

    init() {
      if (Number($('body').data('pwaEnabled') || 0) !== 1) return;

      const installButton = document.getElementById('h-pwa-install');
      if (installButton) {
        installButton.addEventListener('click', () => this.install());
      }

      window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        this.deferredPrompt = event;
        this.toggleInstallButton(true);
      });

      window.addEventListener('appinstalled', () => {
        this.deferredPrompt = null;
        this.toggleInstallButton(false);
        HToast.success('HariLog installed successfully.');
      });

      if ('serviceWorker' in navigator) {
        const swUrl = String($('body').data('swUrl') || '/sw.js');
        window.addEventListener('load', () => {
          navigator.serviceWorker.register(swUrl).catch(() => {
            // Keep app functional even if SW registration fails.
          });
        });
      }
    },

    async install() {
      if (!this.deferredPrompt) {
        HToast.info('Install prompt is not available on this browser/session yet.');
        return;
      }

      this.deferredPrompt.prompt();

      try {
        await this.deferredPrompt.userChoice;
      } catch (error) {
        // Ignore prompt cancel/failure noise.
      }

      this.deferredPrompt = null;
      this.toggleInstallButton(false);
    },

    toggleInstallButton(show) {
      const installButton = document.getElementById('h-pwa-install');
      if (!installButton) return;
      installButton.style.display = show ? 'inline-flex' : 'none';
    },
  };

  /* ── DEBUG STORE ─────────────────────────────────────── */
  const HDebug = {
    key: 'h_client_errors',
    maxItems: 120,
    _bound: false,
    _isOpen: false,

    init() {
      if (this._bound) return;

      window.addEventListener('error', (event) => {
        this.push({
          type: 'error',
          message: event.message || 'Unhandled error',
          source: event.filename || '',
          line: event.lineno || 0,
          time: new Date().toISOString(),
          path: window.location.pathname + window.location.search,
        });
      });

      window.addEventListener('unhandledrejection', (event) => {
        const reason = event.reason && event.reason.message ? event.reason.message : String(event.reason || 'Promise rejection');
        this.push({
          type: 'promise',
          message: reason,
          source: '',
          line: 0,
          time: new Date().toISOString(),
          path: window.location.pathname + window.location.search,
        });
      });

      $(document).on('click', '[data-debug-toggle]', (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.toggle();
      });

      $(document).on('click', '[data-debug-close]', (event) => {
        event.preventDefault();
        this.close();
      });

      $(document).on('click', '[data-debug-refresh]', (event) => {
        event.preventDefault();
        this.render();
        HToast.info('Debug console refreshed.');
      });

      $(document).on('click', '[data-debug-clear]', (event) => {
        event.preventDefault();
        this.clear();
        HToast.success('Debug console cleared.');
      });

      $(document).on('click', (event) => {
        const $tray = $('#h-debug-tray');
        if (!$tray.length || !$tray.hasClass('show')) return;
        if ($(event.target).closest('#h-debug-tray, [data-debug-toggle]').length) return;
        this.close();
      });

      $(document).on('keydown', (event) => {
        if (event.key === 'Escape') this.close();
      });

      this.render();
      this._bound = true;
    },

    push(entry) {
      try {
        const items = this.read();
        items.unshift({
          type: String(entry.type || 'info'),
          message: String(entry.message || 'Debug event'),
          source: String(entry.source || ''),
          line: Number(entry.line || 0),
          time: String(entry.time || new Date().toISOString()),
          path: String(entry.path || (window.location.pathname + window.location.search)),
        });
        localStorage.setItem(this.key, JSON.stringify(items.slice(0, this.maxItems)));
      } catch (error) {
        // Ignore localStorage failures.
      }
      this.render();
    },

    read() {
      try {
        const raw = localStorage.getItem(this.key);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        return [];
      }
    },

    clear() {
      try {
        localStorage.removeItem(this.key);
      } catch (error) {
        // Ignore localStorage failures.
      }
      this.render();
    },

    open() {
      const tray = document.getElementById('h-debug-tray');
      if (!tray) return;
      this._isOpen = true;
      tray.classList.add('show');
      tray.setAttribute('aria-hidden', 'false');
      this.render();
    },

    close() {
      const tray = document.getElementById('h-debug-tray');
      if (!tray) return;
      this._isOpen = false;
      tray.classList.remove('show');
      tray.setAttribute('aria-hidden', 'true');
    },

    toggle() {
      if (this._isOpen) {
        this.close();
        return;
      }
      this.open();
    },

    captureEvent(name, detail = {}) {
      if (name !== 'hspa:error') return;
      this.push({
        type: 'hspa',
        message: String(detail.message || 'SPA navigation error'),
        source: String(detail.url || ''),
        line: Number(detail.status || 0),
        time: new Date().toISOString(),
        path: window.location.pathname + window.location.search,
      });
    },

    render() {
      const list = document.getElementById('h-debug-list');
      if (!list) return;

      const items = this.read();
      if (!items.length) {
        list.innerHTML = `
          <div class="h-notif-empty">
            <i class="fa-regular fa-square-check"></i>
            <span>No client-side errors captured.</span>
          </div>
        `;
        return;
      }

      list.innerHTML = items.map((item) => {
        const type = this._escape(item.type || 'info').toLowerCase();
        const tone = ['error', 'promise', 'hspa'].includes(type) ? type : 'info';
        const message = this._escape(item.message || 'Debug event');
        const source = this._escape(item.source || '');
        const line = Number(item.line || 0);
        const path = this._escape(item.path || '');
        const time = this._escape(this._formatTime(item.time));
        const sourceLabel = source !== '' ? source + (line > 0 ? ':' + line : '') : (path || '-');

        return `
          <article class="h-debug-item h-debug-${tone}">
            <div class="h-debug-row">
              <span class="h-debug-badge">${tone.toUpperCase()}</span>
              <span class="h-debug-time">${time}</span>
            </div>
            <div class="h-debug-message">${message}</div>
            <div class="h-debug-meta">${sourceLabel}</div>
          </article>
        `;
      }).join('');
    },

    _formatTime(value) {
      const date = new Date(String(value || ''));
      if (Number.isNaN(date.getTime())) return String(value || '');
      return date.toLocaleString();
    },

    _escape(input) {
      return String(input ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },
  };

  /* ── PASSWORD TOGGLE ──────────────────────────────────── */
  $(document).on('click', '.h-pw-toggle', function () {
    const $input = $(this).closest('.h-input-wrap').find('.h-input');
    if (!$input.length) return;

    const nextType = $input.attr('type') === 'password' ? 'text' : 'password';
    $input.attr('type', nextType);
    $(this).find('.eye-on').toggle(nextType === 'text');
    $(this).find('.eye-off').toggle(nextType === 'password');
  });

  /* ── CLOCK ────────────────────────────────────────────── */
  function updateClock() {
    const $clock = $('#h-live-clock,#h-clock');
    if (!$clock.length) return;
    const userLocale = String(document.body?.dataset?.uiLocale || 'en').toLowerCase() === 'ne' ? 'ne' : 'en';
    const now = new Date();
    if (window.HNepaliDate && typeof window.HNepaliDate.dual === 'function') {
      $clock.text(window.HNepaliDate.dual(now, { locale: userLocale, withTime: true }));
      return;
    }

    const english = now.toLocaleString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      timeZone: 'Asia/Kathmandu',
    });
    const nepali = now.toLocaleString('ne-NP', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
      timeZone: 'Asia/Kathmandu',
    });

    $clock.text(userLocale === 'ne' ? (nepali + ' | ' + english) : (english + ' | ' + nepali));
  }

  /* ── AJAX BASE CONFIG ─────────────────────────────────── */
  function initAjax() {
    const token = $('meta[name="csrf-token"]').attr('content');
    const headers = {
      'X-Requested-With': 'XMLHttpRequest',
    };

    if (token) {
      headers['X-CSRF-TOKEN'] = token;
    }

    $.ajaxSetup({ headers });
  }

  /* ── UTILITIES ────────────────────────────────────────── */
  const HUtils = {
    formatNPR(numberValue) {
      const parsed = typeof numberValue === 'number' ? numberValue : Number(numberValue) || 0;
      const userLocale = String(document.body?.dataset?.uiLocale || 'en').toLowerCase() === 'ne' ? 'ne-NP' : 'en-IN';
      return 'रू ' + parsed.toLocaleString(userLocale, { minimumFractionDigits: 2 });
    },

    htmlToDoc(html) {
      return new DOMParser().parseFromString(html, 'text/html');
    },

    extractContainer(html, selector) {
      const doc = this.htmlToDoc(html);
      return doc.querySelector(selector);
    },

    isSameOrigin(url) {
      try {
        const parsed = new URL(url, window.location.href);
        return parsed.origin === window.location.origin;
      } catch (error) {
        return false;
      }
    },
  };

  /* ── API WRAPPER ──────────────────────────────────────── */
  const HApi = {
    request(settings = {}) {
      const options = $.extend(true, {}, settings);
      const method = String(options.method || options.type || 'GET').toUpperCase();
      const token = $('meta[name="csrf-token"]').attr('content');

      options.headers = $.extend(
        {
          'X-Requested-With': 'XMLHttpRequest',
        },
        options.headers || {}
      );

      if (token) {
        options.headers['X-CSRF-TOKEN'] = token;
      }

      if (
        token &&
        ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) &&
        options.data &&
        !(options.data instanceof window.FormData)
      ) {
        if (typeof options.data === 'string') {
          if (!/(^|&)_token=/.test(options.data)) {
            options.data += (options.data ? '&' : '') + '_token=' + encodeURIComponent(token);
          }
        } else if (typeof options.data === 'object') {
          if (!Object.prototype.hasOwnProperty.call(options.data, '_token')) {
            options.data._token = token;
          }
        }
      }

      return $.ajax(options);
    },

    get(url, data = {}, opts = {}) {
      return this.request($.extend({ url, method: 'GET', data }, opts));
    },

    post(url, data = {}, opts = {}) {
      return this.request($.extend({ url, method: 'POST', data }, opts));
    },

    submitForm($form, opts = {}) {
      const url = $form.attr('action') || window.location.href;
      const method = ($form.attr('method') || 'POST').toUpperCase();
      const hasFile = $form.find('input[type="file"]').length > 0;
      const token = $('meta[name="csrf-token"]').attr('content');

      if (token && method !== 'GET' && !$form.find('input[name="_token"]').length) {
        $form.append(`<input type="hidden" name="_token" value="${token}">`);
      }

      const settings = {
        url,
        method,
        headers: {
          'X-HSPA': '1',
        },
      };

      if (hasFile) {
        settings.data = new FormData($form[0]);
        settings.processData = false;
        settings.contentType = false;
      } else {
        settings.data = $form.serialize();
      }

      return this.request(settings)
        .done((res, status, xhr) => {
          if (typeof opts.success === 'function') opts.success(res, status, xhr);
        })
        .fail((xhr) => {
          if (typeof opts.error === 'function') opts.error(xhr);
        });
    },
  };

  /* ── SPA LAYER ────────────────────────────────────────── */
  const HSPA = {
    container: '#h-spa-content',
    progress: null,

    init() {
      this.createProgress();
      this.bindLinks();
      this.bindForms();
      this.highlightActiveNav(window.location.pathname + window.location.search);
      this._normalizeSidebarBrand();

      window.addEventListener('popstate', () => {
        this.load(window.location.pathname + window.location.search);
      });
    },

    createProgress() {
      if (!document.querySelector('.h-spa-progress')) {
        const progress = document.createElement('div');
        progress.className = 'h-spa-progress hide';
        document.body.appendChild(progress);
        this.progress = progress;
      } else {
        this.progress = document.querySelector('.h-spa-progress');
      }
    },

    showProgress() {
      if (!this.progress) this.createProgress();
      this.progress.classList.remove('hide');
      this.progress.style.width = '8%';
      this.progress.offsetWidth;

      setTimeout(() => {
        if (this.progress) this.progress.style.width = '45%';
      }, 100);

      document.documentElement.classList.add('h-spa-loading');
    },

    finishProgress() {
      if (!this.progress) return;

      this.progress.style.width = '100%';
      setTimeout(() => {
        if (!this.progress) return;
        this.progress.classList.add('hide');
        this.progress.style.width = '0%';
      }, 180);

      document.documentElement.classList.remove('h-spa-loading');
    },

    bindLinks() {
      $(document).on('click', 'a[data-spa]', (event) => {
        if ($(event.currentTarget).is('[data-confirm="true"]')) return;
        if (!this.shouldHandleLink(event)) return;

        event.preventDefault();
        const href = $(event.currentTarget).attr('href');
        this.navigate(href, true);
      });
    },

    shouldHandleLink(event) {
      const anchor = event.currentTarget;
      const href = anchor.getAttribute('href');

      if (!href || href.startsWith('#')) return false;
      if (href.startsWith('mailto:') || href.startsWith('tel:')) return false;
      if (anchor.hasAttribute('download')) return false;
      if (anchor.getAttribute('target') === '_blank') return false;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return false;
      if (!HUtils.isSameOrigin(anchor.href)) return false;

      return true;
    },

    bindForms() {
      $(document).on('submit', 'form[data-spa]', (event) => {
        const $form = $(event.currentTarget);
        if ($form.is('[data-confirm="true"]') && !$form.is('[data-confirm-bypass="1"]')) {
          return;
        }

        event.preventDefault();
        const fallbackUrl = window.location.href;
        const $submit = $form.find('button[type="submit"]').first();
        const originalText = $submit.text();

        if ($submit.length) {
          $submit.prop('disabled', true).text($submit.data('busy-text') || 'Working...');
        }

        this.showProgress();
        HCore.emit('hspa:beforeLoad', {
          url: $form.attr('action') || fallbackUrl,
          type: 'form',
        });

        HApi.submitForm($form, {
          success: (res, status, xhr) => {
            this._handleResponse(res, xhr, true, fallbackUrl);
            if ($submit.length) $submit.prop('disabled', false).text(originalText);
          },
          error: (xhr) => {
            this._handleError(xhr);
            if ($submit.length) $submit.prop('disabled', false).text(originalText);
          },
        });
      });
    },

    navigate(url, push = true) {
      if (!url) return;

      this.showProgress();
      HCore.emit('hspa:beforeLoad', { url, type: 'link' });

      return $.ajax({
        url,
        method: 'GET',
        dataType: 'html',
        headers: {
          'X-HSPA': '1',
        },
      })
        .done((html, status, xhr) => {
          this._handleResponse(html, xhr, push, url);
        })
        .fail((xhr) => {
          this.finishProgress();
          HToast.error('Failed to load page. Falling back to full navigation.');
          HCore.emit('hspa:error', { url, status: xhr.status });
          window.location.href = url;
        });
    },

    load(url) {
      return this.navigate(url, false);
    },

    _handleResponse(response, xhr, push, requestUrl = '') {
      const contentType = xhr && xhr.getResponseHeader ? xhr.getResponseHeader('Content-Type') || '' : '';

      if (contentType.includes('application/json') || (typeof response !== 'string' && typeof response === 'object')) {
        try {
          const json = typeof response === 'string' ? JSON.parse(response) : response;

          if (json.redirect) {
            this.navigate(json.redirect);
            return;
          }

          if (json.message) HToast.success(json.message);
          this.finishProgress();
          HCore.emit('hspa:afterLoad', { url: requestUrl || window.location.href, mode: 'json' });
          return;
        } catch (error) {
          HToast.warning('Response parsed with fallback mode.');
          this.finishProgress();
          return;
        }
      }

      if (typeof response === 'string') {
        const doc = HUtils.htmlToDoc(response);
        const currentContainer = document.querySelector(this.container);
        const incomingContainer = doc.querySelector(this.container);

        if (currentContainer && incomingContainer) {
          this._syncCsrfToken(doc);
          this._syncBodyDataset(doc);
          currentContainer.innerHTML = incomingContainer.innerHTML;

          const title = doc.querySelector('title');
          if (title) document.title = title.textContent;

          const resolvedUrl = (xhr && xhr.responseURL) || requestUrl || window.location.href;
          const next = new URL(resolvedUrl, window.location.href);
          const nextPath = next.pathname + next.search;

          this._syncTopbar(doc);
          this._syncDynamicRegions(doc);
          this._emitDocumentToasts(doc);

          if (push !== false) history.pushState({}, '', next.href);
          this.highlightActiveNav(nextPath);
          this._runInlineScripts(currentContainer);
          this._runInlineScripts(document.querySelector('#h-page-scripts'));
          this._rehydrate({
            container: currentContainer,
            url: next.href,
          });

          window.scrollTo({ top: 0, behavior: 'auto' });
          this.finishProgress();
          return;
        }

        this._replaceDocument(response);
        return;
      }

      this.finishProgress();
    },

    _replaceDocument(html) {
      this.finishProgress();
      document.open();
      document.write(html);
      document.close();
    },

    _handleError(xhr) {
      let message = 'Something went wrong.';
      const contentType = xhr.getResponseHeader ? xhr.getResponseHeader('Content-Type') || '' : '';

      if (xhr.status === 419) {
        HToast.warning('Session token expired. Reloading page...');
        setTimeout(() => window.location.reload(), 800);
        this.finishProgress();
        HCore.emit('hspa:error', { status: xhr.status, message: 'CSRF token expired.' });
        return;
      }

      if (contentType.includes('application/json')) {
        try {
          const json = JSON.parse(xhr.responseText || '{}');
          message =
            json.message ||
            (json.errors && Object.values(json.errors)[0] && Object.values(json.errors)[0][0]) ||
            message;
        } catch (error) {
          // keep fallback message
        }
      } else if (typeof xhr.responseText === 'string') {
        const doc = HUtils.htmlToDoc(xhr.responseText);
        const errorEl = doc.querySelector('.h-alert.error, .alert-danger');
        if (errorEl) message = errorEl.textContent.trim().slice(0, 220);
      }

      HToast.error(message);
      this.finishProgress();
      HCore.emit('hspa:error', { status: xhr.status, message });
    },

    _runInlineScripts(container) {
      const scripts = container.querySelectorAll('script');

      scripts.forEach((script) => {
        if (script.src) {
          if (document.querySelector('script[src="' + script.src + '"]')) return;
          const next = document.createElement('script');
          next.src = script.src;
          next.async = false;
          document.body.appendChild(next);
          return;
        }

        if (script.textContent && script.textContent.trim()) {
          try {
            window.eval(script.textContent);
          } catch (error) {
            console.error('Inline script error:', error);
          }
        }
      });
    },

    _syncTopbar(doc) {
      if (!doc) return;

      const nextPageTitle = doc.querySelector('#h-page-title');
      const currentPageTitle = document.querySelector('#h-page-title');
      if (nextPageTitle && currentPageTitle) {
        currentPageTitle.textContent = nextPageTitle.textContent || '';
      }

      const nextExtra = doc.querySelector('#h-topbar-extra');
      const currentExtra = document.querySelector('#h-topbar-extra');
      if (nextExtra && currentExtra) {
        currentExtra.innerHTML = nextExtra.innerHTML;
      }
    },

    _syncDynamicRegions(doc) {
      if (!doc) return;
      this._syncRegion(doc, '#h-page-flash');
      this._syncRegion(doc, '#h-page-modals');
      this._syncRegion(doc, '#h-page-fab');
      this._syncRegion(doc, '#h-page-scripts');
      this._syncRegion(doc, '#h-sidebar-brand');
      this._normalizeSidebarBrand();
      this._syncFavicon(doc);
      this._syncThemeColor(doc);
    },

    _syncCsrfToken(doc) {
      if (!doc) return;

      const incomingMeta = doc.querySelector('meta[name="csrf-token"]');
      if (!incomingMeta) return;

      const nextToken = (incomingMeta.getAttribute('content') || '').trim();
      if (!nextToken) return;

      const currentMeta = document.querySelector('meta[name="csrf-token"]');
      if (currentMeta) {
        currentMeta.setAttribute('content', nextToken);
      }

      $.ajaxSetup({
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': nextToken,
        },
      });
    },

    _syncBodyDataset(doc) {
      if (!doc || !doc.body || !document.body) return;

      const keys = [
        'notificationsFeedUrl',
        'notificationReadUrlTemplate',
        'notificationsPollSeconds',
        'notificationsAutoPoll',
        'browserNotifyEnabled',
        'pwaEnabled',
        'swUrl',
        'iconSpriteUrl',
        'fileManagerListUrl',
        'fileManagerUploadUrl',
        'fileManagerDeleteUrl',
        'fileManagerFolderUrl',
        'fileManagerExportUrl',
        'fileManagerResizeUrl',
        'globalSearchUrl',
        'globalSearchDebounce',
        'faviconUrl',
        'themeColor',
        'notificationReadAllUrl',
        'notificationSoundUrl',
        'uiLocale',
        'hotReloadStreamUrl',
        'elfinderUiCssUrl',
        'elfinderUiJsUrl',
        'elfinderCssUrl',
        'elfinderThemeCssUrl',
        'elfinderJsUrl',
      ];

      keys.forEach((key) => {
        const nextValue = doc.body.dataset ? doc.body.dataset[key] : null;
        if (typeof nextValue === 'undefined' || nextValue === null) return;
        document.body.dataset[key] = nextValue;
      });
    },

    _syncRegion(doc, selector) {
      const incoming = doc.querySelector(selector);
      const current = document.querySelector(selector);
      if (!incoming || !current) return;
      current.innerHTML = incoming.innerHTML;
    },

    _syncFavicon(doc) {
      if (!doc) return;

      const incoming = doc.querySelector('link[rel="icon"]');
      if (!incoming) return;

      const href = String(incoming.getAttribute('href') || '').trim();
      if (!href) return;

      let current = document.querySelector('link[rel="icon"]');
      if (!current) {
        current = document.createElement('link');
        current.setAttribute('rel', 'icon');
        document.head.appendChild(current);
      }

      current.setAttribute('href', href);
    },

    _syncThemeColor(doc) {
      if (!doc) return;

      const incomingMeta = doc.querySelector('meta[name="theme-color"]');
      if (!incomingMeta) return;

      const color = String(incomingMeta.getAttribute('content') || '').trim();
      if (!/^#[0-9a-fA-F]{6}$/.test(color)) return;

      let currentMeta = document.querySelector('meta[name="theme-color"]');
      if (!currentMeta) {
        currentMeta = document.createElement('meta');
        currentMeta.setAttribute('name', 'theme-color');
        document.head.appendChild(currentMeta);
      }

      currentMeta.setAttribute('content', color);
      document.documentElement.style.setProperty('--gold', color);
      document.documentElement.style.setProperty('--gold-dk', color);
      document.body.dataset.themeColor = color;
    },

    _emitDocumentToasts(doc) {
      if (!doc) return;

      const success = doc.querySelector('.h-alert.success, .alert-success');
      if (success && success.textContent) {
        HToast.success(success.textContent.trim().slice(0, 220));
      }

      const error = doc.querySelector('.h-alert.error, .alert-danger');
      if (error && error.textContent) {
        HToast.error(error.textContent.trim().slice(0, 220));
      }
    },

    _rehydrate(payload = {}) {
      updateClock();
      if (window.HModal && typeof window.HModal.closeAll === 'function') {
        window.HModal.closeAll();
      }
      HSidebar.close();
      HNotify.close();
      if (window.HDebug && typeof window.HDebug.close === 'function') {
        window.HDebug.close();
      }
      HCore.emit('hspa:afterSwap', payload);
      HCore.emit('hspa:afterLoad', payload);
    },

    _normalizeSidebarBrand() {
      const logo = document.querySelector('#h-sidebar-brand .h-brand-logo');
      if (!logo) return;
      logo.setAttribute('width', '38');
      logo.setAttribute('height', '38');
      logo.style.width = '38px';
      logo.style.height = '38px';
      logo.style.minWidth = '38px';
      logo.style.minHeight = '38px';
      logo.style.maxWidth = '38px';
      logo.style.maxHeight = '38px';
    },

    highlightActiveNav(pathnameWithQuery) {
      if (!pathnameWithQuery) return;

      let currentUrl;
      try {
        currentUrl = new URL(pathnameWithQuery, window.location.origin);
      } catch (error) {
        currentUrl = new URL(window.location.href);
      }

      const currentPath = currentUrl.pathname.replace(/\/$/, '') || '/';
      const currentQuery = currentUrl.searchParams;

      document.querySelectorAll('.h-nav-item, .h-nav-sub-item').forEach((item) => {
        const href = item.getAttribute('href');
        if (!href || href === '#') return;

        let navPath = '';
        try {
          navPath = new URL(href, window.location.origin).pathname.replace(/\/$/, '') || '/';
        } catch (error) {
          return;
        }

        let isActive = navPath === currentPath;
        const matchQueryRaw = String(item.getAttribute('data-match-query') || '').trim();

        if (isActive && matchQueryRaw !== '') {
          const queryPairs = matchQueryRaw.split('&').map((pair) => pair.trim()).filter(Boolean);
          isActive = queryPairs.every((pair) => {
            const [key, value = ''] = pair.split('=');
            if (!key) return true;
            return currentQuery.get(key) === value;
          });
        }

        item.classList.toggle('active', isActive);
      });

      if (window.HSidebar && typeof window.HSidebar.syncGroups === 'function') {
        window.HSidebar.syncGroups();
      }
    },
  };

  /* ── INIT ─────────────────────────────────────────────── */
  $(function () {
    HTheme.init();
    HSidebar.init();
    HToast.init();
    HModal.init();
    HSearch.init();
    HMediaManager.init();
    HNotify.init();
    HHotReload.init();
    HPWA.init();
    HDebug.init();
    initAjax();

    updateClock();
    setInterval(updateClock, 30000);

    window.HCore = HCore;
    window.HTheme = HTheme;
    window.HSidebar = HSidebar;
    window.HToast = HToast;
    window.HModal = HModal;
    window.HSearch = HSearch;
    window.HMediaManager = HMediaManager;
    window.HNotify = HNotify;
    window.HHotReload = HHotReload;
    window.HPWA = HPWA;
    window.HDebug = HDebug;
    window.HApi = HApi;
    window.HSPA = HSPA;
    window.HUtils = HUtils;

    HSPA.init();
  });
})(jQuery, window, document);
(function (window, document, $) {
  'use strict';

  if (!$) return;

  const HPlugins = {
    init(root) {
      const target = root || document;
      HConfirm.init();
      HTabs.init(target);
      HIcons.init(target);
      HSelect.init(target);
      HSelectRemote.init(target);
      HDataTable.init(target);
      HEditor.init(target);
      HSvgPie.init(target);
    },
  };

  /* --------------------------
     HConfirm: confirm modal
  ---------------------------*/
  const HConfirm = {
    _bound: false,

    init() {
      this.$modal = $('[data-h-confirm]').first();
      if (!this.$modal.length || this._bound) return;

      this.$title = this.$modal.find('#h-confirm-title');
      this.$text = this.$modal.find('#h-confirm-text');
      this.$ok = this.$modal.find('[data-h-confirm-ok]');
      this.$cancel = this.$modal.find('[data-h-confirm-cancel]');
      this.$close = this.$modal.find('[data-h-confirm-close]');
      this.current = null;
      this.pending = false;

      $(document).on('click', 'a[data-confirm="true"]', (event) => {
        const $anchor = $(event.currentTarget);
        if (event.ctrlKey || event.metaKey || event.which === 2) return;
        event.preventDefault();
        this._openFromAnchor($anchor);
      });

      $(document).on('submit', 'form[data-confirm="true"]', (event) => {
        const $form = $(event.currentTarget);
        if ($form.is('[data-confirm-bypass="1"]')) return;
        event.preventDefault();
        this._openFromForm($form);
      });

      this.$cancel.on('click', () => this.close());
      this.$close.on('click', () => this.close());
      this.$modal.on('click', (event) => {
        if (event.target === this.$modal[0]) this.close();
      });
      this.$ok.on('click', () => this._doConfirm());

      $(document).on('keydown', (event) => {
        if (event.key === 'Escape') this.close();
      });

      this._bound = true;
    },

    _openFromAnchor($anchor) {
      this.current = {
        type: 'link',
        href: $anchor.attr('href') || '',
        method: String($anchor.data('confirm-method') || 'GET').toUpperCase(),
      };

      this._show(
        $anchor.data('confirm-title') || 'Confirm action',
        $anchor.data('confirm-text') || 'Are you sure you want to continue?',
        $anchor.data('confirm-ok') || 'Continue',
        $anchor.data('confirm-cancel') || 'Cancel'
      );
    },

    _openFromForm($form) {
      this.current = {
        type: 'form',
        $form,
      };

      this._show(
        $form.data('confirm-title') || 'Confirm action',
        $form.data('confirm-text') || 'Are you sure you want to continue?',
        $form.data('confirm-ok') || 'Continue',
        $form.data('confirm-cancel') || 'Cancel'
      );
    },

    _show(title, text, okText, cancelText) {
      this.$title.text(title);
      this.$text.text(text);
      this.$ok.text(okText);
      this.$cancel.text(cancelText);
      this.pending = false;
      this.$ok.prop('disabled', false);
      this.$cancel.prop('disabled', false);
      this.$modal.addClass('show').attr('aria-hidden', 'false');
      $('body').css('overflow', 'hidden');
    },

    close() {
      if (!this.$modal || !this.$modal.length) return;
      this.$modal.removeClass('show').attr('aria-hidden', 'true');
      $('body').css('overflow', '');
      this.current = null;
      this.pending = false;
      this.$ok.prop('disabled', false);
      this.$cancel.prop('disabled', false);
    },

    _doConfirm() {
      if (!this.current) {
        this.close();
        return;
      }
      if (this.pending) return;

      this.pending = true;
      this.$ok.prop('disabled', true);
      this.$cancel.prop('disabled', true);

      if (this.current.type === 'link') {
        const method = this.current.method;
        const href = this.current.href;

        if (!href) {
          this.pending = false;
          this.$ok.prop('disabled', false);
          this.$cancel.prop('disabled', false);
          this.close();
          return;
        }

        if (method === 'GET') {
          window.location.href = href;
          this.close();
          return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = href;
        form.style.display = 'none';

        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (tokenMeta) {
          const csrf = document.createElement('input');
          csrf.type = 'hidden';
          csrf.name = '_token';
          csrf.value = tokenMeta.getAttribute('content') || '';
          form.appendChild(csrf);
        }

        if (method !== 'POST') {
          const override = document.createElement('input');
          override.type = 'hidden';
          override.name = '_method';
          override.value = method;
          form.appendChild(override);
        }

        document.body.appendChild(form);
        form.submit();
        this.close();
        return;
      }

      if (this.current.type === 'form' && this.current.$form) {
        const $form = this.current.$form;
        $form.attr('data-confirm-bypass', '1');
        $form.trigger('submit');
        setTimeout(() => $form.removeAttr('data-confirm-bypass'), 0);
        this.close();
      }
    },
  };

  $(document).on('submit', 'form[data-confirm="true"][data-confirm-bypass="1"]', function (event) {
    event.preventDefault();
    event.stopImmediatePropagation();
    this.submit();
    return false;
  });

  /* --------------------------
     HTabs: reusable tab UI
  ---------------------------*/
  const HTabs = {
    selector: '[data-ui-tabs]',

    init(root) {
      const ctx = root && root.querySelectorAll ? root : document;
      ctx.querySelectorAll(this.selector).forEach((container) => this.setup(container));
    },

    setup(container) {
      if (!container || container.dataset.hTabsReady === '1') return;

      const buttons = Array.from(container.querySelectorAll('[data-tab-btn]')).filter((button) => {
        const owner = button.closest('[data-ui-tabs]');
        return owner === container;
      });
      const panels = Array.from(container.querySelectorAll('[data-tab-panel]')).filter((panel) => {
        const owner = panel.closest('[data-ui-tabs]');
        return owner === container;
      });
      if (!buttons.length || !panels.length) return;

      const fallback = buttons[0].dataset.tabBtn;
      const initial = container.dataset.defaultTab || fallback;

      const activate = (tabId, shouldFocus = false) => {
        const requested = String(tabId || '').trim();
        const hasMatch = buttons.some((button) => button.dataset.tabBtn === requested);
        const activeId = hasMatch ? requested : fallback;

        buttons.forEach((button) => {
          const isActive = button.dataset.tabBtn === activeId;
          button.classList.toggle('is-active', isActive);
          button.setAttribute('aria-selected', isActive ? 'true' : 'false');
          button.setAttribute('tabindex', isActive ? '0' : '-1');
          if (isActive && shouldFocus) button.focus();
        });

        let activePanel = null;
        panels.forEach((panel) => {
          const isActive = panel.dataset.tabPanel === activeId;
          panel.classList.toggle('is-active', isActive);
          panel.hidden = !isActive;
          if (isActive) activePanel = panel;
        });

        container.dataset.activeTab = activeId;
        document.dispatchEvent(
          new CustomEvent('h:tabs:changed', {
            detail: {
              tabId: activeId,
              container,
              panel: activePanel,
            },
          })
        );
      };

      buttons.forEach((button) => {
        button.addEventListener('click', () => activate(button.dataset.tabBtn));
        button.addEventListener('keydown', (event) => {
          const index = buttons.indexOf(button);
          if (index < 0) return;

          if (event.key === 'ArrowRight') {
            event.preventDefault();
            const next = buttons[(index + 1) % buttons.length];
            activate(next.dataset.tabBtn, true);
            return;
          }

          if (event.key === 'ArrowLeft') {
            event.preventDefault();
            const next = buttons[(index - 1 + buttons.length) % buttons.length];
            activate(next.dataset.tabBtn, true);
          }
        });
      });

      activate(initial);
      container.dataset.hTabsReady = '1';
    },
  };

  /* --------------------------
     HSelect: custom select
  ---------------------------*/
  const HSelect = {
    selector: 'select[data-h-select]:not([data-select2-remote]), select.h-select2:not([data-select2-remote])',
    _bound: false,

    init(root) {
      const ctx = root && root.querySelectorAll ? root : document;
      ctx.querySelectorAll(this.selector).forEach((select) => this.setup(select));
      this._bindGlobalHandlers();
    },

    setup(select) {
      if (select.dataset.hSelectReady === '1') return;

      const options = Array.from(select.options);
      const isMultiple = select.multiple;
      const searchEnabled = select.dataset.hSearch !== 'false' && options.length >= (Number(select.dataset.hSearchMin) || 8);
      const placeholder = select.dataset.placeholder || 'Select an option';

      const wrapper = document.createElement('div');
      wrapper.className = 'h-select2-wrap';
      wrapper.dataset.multiple = isMultiple ? 'true' : 'false';

      wrapper.innerHTML = `
        <button type="button" class="h-select2-control" aria-haspopup="listbox" aria-expanded="false">
          <span class="h-select2-value"></span>
          <span class="h-select2-chevron" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
        </button>
        <div class="h-select2-panel" hidden>
          <div class="h-select2-search ${searchEnabled ? '' : 'is-hidden'}">
            <input type="text" class="h-select2-search-input" placeholder="Search..." autocomplete="off">
          </div>
          <ul class="h-select2-options" role="listbox"></ul>
        </div>
      `;

      select.classList.add('h-select-native');
      select.dataset.hSelectReady = '1';
      select.after(wrapper);

      const instance = {
        select,
        wrapper,
        control: wrapper.querySelector('.h-select2-control'),
        valueEl: wrapper.querySelector('.h-select2-value'),
        panel: wrapper.querySelector('.h-select2-panel'),
        searchInput: wrapper.querySelector('.h-select2-search-input'),
        list: wrapper.querySelector('.h-select2-options'),
        isMultiple,
        searchEnabled,
        placeholder,
        query: '',
      };

      wrapper.__hSelect = instance;
      this._render(instance);
      this._bindInstance(instance);
    },

    _bindInstance(instance) {
      const { control, panel, searchInput, select } = instance;

      control.addEventListener('click', () => {
        const isOpen = instance.wrapper.classList.contains('is-open');
        isOpen ? this.close(instance) : this.open(instance);
      });

      control.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          this.open(instance);
        }
      });

      panel.addEventListener('click', (event) => {
        const item = event.target.closest('[data-value]');
        if (!item || item.classList.contains('is-disabled')) return;

        const option = Array.from(select.options).find((opt) => opt.value === item.dataset.value);
        if (!option) return;

        if (instance.isMultiple) {
          option.selected = !option.selected;
          this._render(instance);
          this.open(instance);
        } else {
          select.value = option.value;
          this._render(instance);
          this.close(instance);
        }

        select.dispatchEvent(new Event('change', { bubbles: true }));
      });

      if (instance.searchEnabled) {
        searchInput.addEventListener('input', (event) => {
          instance.query = event.target.value.toLowerCase().trim();
          this._renderOptions(instance);
        });
      }

      select.addEventListener('change', () => {
        this._render(instance);
      });
    },

    _bindGlobalHandlers() {
      if (this._bound) return;

      document.addEventListener('click', (event) => {
        document.querySelectorAll('.h-select2-wrap.is-open').forEach((wrap) => {
          if (wrap.contains(event.target)) return;
          this.close(wrap.__hSelect);
        });
      });

      document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.h-select2-wrap.is-open').forEach((wrap) => {
          this.close(wrap.__hSelect);
        });
      });

      this._bound = true;
    },

    open(instance) {
      if (!instance) return;

      document.querySelectorAll('.h-select2-wrap.is-open').forEach((wrap) => {
        if (wrap === instance.wrapper) return;
        this.close(wrap.__hSelect);
      });

      instance.wrapper.classList.add('is-open');
      instance.control.setAttribute('aria-expanded', 'true');
      instance.panel.hidden = false;

      if (instance.searchEnabled && instance.searchInput) {
        instance.searchInput.focus();
      }
    },

    close(instance) {
      if (!instance) return;
      instance.wrapper.classList.remove('is-open');
      instance.control.setAttribute('aria-expanded', 'false');
      instance.panel.hidden = true;
    },

    _render(instance) {
      this._renderValue(instance);
      this._renderOptions(instance);
    },

    _renderValue(instance) {
      const selected = Array.from(instance.select.selectedOptions).filter((opt) => opt.value !== '');

      if (!selected.length) {
        instance.valueEl.innerHTML = `<span class="h-select2-placeholder">${instance.placeholder}</span>`;
        return;
      }

      if (!instance.isMultiple) {
        instance.valueEl.textContent = selected[0].textContent;
        return;
      }

      const tags = selected
        .slice(0, 3)
        .map((opt) => `<span class="h-select2-tag">${opt.textContent}</span>`)
        .join('');

      const extra = selected.length > 3 ? `<span class="h-select2-more">+${selected.length - 3}</span>` : '';
      instance.valueEl.innerHTML = `<span class="h-select2-tags">${tags}${extra}</span>`;
    },

    _renderOptions(instance) {
      const selectedValues = new Set(
        Array.from(instance.select.selectedOptions).map((opt) => String(opt.value))
      );

      const query = instance.query;
      const html = Array.from(instance.select.options)
        .filter((option) => {
          if (!query) return true;
          return option.textContent.toLowerCase().includes(query);
        })
        .map((option) => {
          const value = String(option.value);
          const isSelected = selectedValues.has(value);
          const classes = [
            'h-select2-option',
            isSelected ? 'is-selected' : '',
            option.disabled ? 'is-disabled' : '',
          ]
            .filter(Boolean)
            .join(' ');

          return `<li class="${classes}" data-value="${value}" role="option" aria-selected="${isSelected ? 'true' : 'false'}">${option.textContent}</li>`;
        })
        .join('');

      instance.list.innerHTML = html || '<li class="h-select2-empty">No options found</li>';
    },
  };

  /* --------------------------
     HSelectRemote: select2 ajax
  ---------------------------*/
  const HSelectRemote = {
    selector: 'select[data-select2-remote]',

    init(root) {
      if (typeof $.fn.select2 !== 'function') return;

      const $root = root && root.querySelectorAll ? $(root) : $(document);
      const $targets = $root.is(this.selector) ? $root : $root.find(this.selector);

      $targets.each((_, element) => this.setup($(element)));
    },

    setup($select) {
      if ($select.data('hSelect2Ready')) return;

      const endpoint = $select.data('endpoint');
      if (!endpoint) return;

      const placeholder = $select.data('placeholder') || 'Search options';
      const minInput = Number($select.data('minInput') || 1);
      const valueField = $select.data('valueField') || 'id';
      const textField = $select.data('textField') || 'text';
      const imageField = $select.data('imageField') || 'image';
      const subtitleField = $select.data('subtitleField') || 'subtitle';
      const dropdownParent = $($select.data('dropdownParent') || document.body);

      const renderOption = (item) => {
        if (item.loading) return item.text;

        const image = item[imageField] || (item.element ? item.element.dataset.image : '');
        const subtitle = item[subtitleField] || (item.element ? item.element.dataset.subtitle : '');
        const label = item[textField] || item.text || '';
        const imgHtml = image ? `<img src="${image}" alt="" class="h-s2-img">` : '<span class="h-s2-avatar"><i class="fa-solid fa-user"></i></span>';
        const subHtml = subtitle ? `<span class="h-s2-sub">${subtitle}</span>` : '';

        return $(`
          <span class="h-s2-option">
            ${imgHtml}
            <span class="h-s2-copy">
              <span class="h-s2-text">${label}</span>
              ${subHtml}
            </span>
          </span>
        `);
      };

      const renderSelection = (item) => {
        if (!item.id) return item.text || placeholder;
        const image = item[imageField] || (item.element ? item.element.dataset.image : '');
        const label = item[textField] || item.text || '';
        if (!image) return label;
        return $(`
          <span class="h-s2-selected">
            <img src="${image}" alt="" class="h-s2-selected-img">
            <span>${label}</span>
          </span>
        `);
      };

      $select.select2({
        width: '100%',
        placeholder,
        allowClear: !$select.prop('multiple'),
        dropdownParent,
        dropdownCssClass: 'h-s2-dropdown',
        minimumInputLength: minInput,
        escapeMarkup(markup) {
          return markup;
        },
        ajax: {
          url: endpoint,
          dataType: 'json',
          delay: 250,
          data(params) {
            return {
              q: params.term || '',
              page: params.page || 1,
            };
          },
          processResults(data) {
            const rows = Array.isArray(data.results) ? data.results : [];
            const mapped = rows.map((row) => ({
              id: row[valueField] ?? row.id,
              text: row[textField] ?? row.text,
              image: row[imageField] ?? row.image,
              subtitle: row[subtitleField] ?? row.subtitle,
            }));

            return {
              results: mapped,
              pagination: {
                more: Boolean(data.pagination && data.pagination.more),
              },
            };
          },
          cache: true,
        },
        templateResult: renderOption,
        templateSelection: renderSelection,
      });

      $select.data('hSelect2Ready', true);
    },
  };

  /* --------------------------
     HDataTable: DataTables bridge
  ---------------------------*/
  const HDataTable = {
    selector: 'table[data-h-datatable]',

    init(root) {
      if (typeof $.fn.DataTable !== 'function') return;

      const $root = root && root.querySelectorAll ? $(root) : $(document);
      const $targets = $root.is(this.selector) ? $root : $root.find(this.selector);
      $targets.each((_, table) => this.setup($(table)));
    },

    setup($table) {
      if (!$table || !$table.length) return;

      const tableEl = $table[0];
      const locale = this._locale();
      const isNepali = locale === 'ne';
      const endpoint = String($table.data('endpoint') || '').trim();
      const columns = this._columns($table);
      const pageLength = Number($table.data('pageLength') || 10);
      const orderCol = Number($table.data('orderCol') || 0);
      const orderDir = String($table.data('orderDir') || 'desc').toLowerCase() === 'asc' ? 'asc' : 'desc';
      const lengthValues = this._parseLengthMenu($table.data('lengthMenu'));
      const lengthMenu = [lengthValues, lengthValues.map((value) => String(value))];
      const emptyText = String($table.data('emptyText') || 'Empty').trim() || 'Empty';
      const normalizedPageLength = Number.isFinite(pageLength) && pageLength > 0
        ? Math.max(1, Math.min(pageLength, 100))
        : (lengthValues[0] || 10);
      const defaultPageLength = lengthValues.includes(normalizedPageLength)
        ? normalizedPageLength
        : (lengthValues[0] || 10);
      const self = this;

      if ($.fn.DataTable.isDataTable(tableEl)) {
        const api = $table.DataTable();
        if (endpoint && api.ajax) {
          api.ajax.url(endpoint).load(null, false);
        }
        if (api.columns && typeof api.columns.adjust === 'function') {
          api.columns.adjust();
        }
        this._styleControls($(api.table().container()), lengthValues);
        return;
      }

      const options = {
        processing: Boolean(endpoint),
        serverSide: Boolean(endpoint),
        searching: true,
        ordering: true,
        lengthChange: true,
        autoWidth: false,
        responsive: typeof $.fn.dataTable.Responsive === 'function',
        stateSave: true,
        deferRender: true,
        searchDelay: 280,
        pageLength: defaultPageLength,
        lengthMenu,
        dom: "<'row align-items-center mb-2'<'col-md-6'l><'col-md-6'f>>" +
          "<'row'<'col-12'tr>>" +
          "<'row mt-2'<'col-md-5'i><'col-md-7'p>>",
        order: [[Number.isFinite(orderCol) ? Math.max(0, orderCol) : 0, orderDir]],
        language: {
          search: '',
          searchPlaceholder: isNepali ? 'खोज्नुहोस्...' : 'Search...',
          lengthMenu: isNepali ? '_MENU_ पङ्क्ति देखाउनुहोस्' : 'Show _MENU_ rows',
          emptyTable: emptyText,
          zeroRecords: emptyText,
          loadingRecords: isNepali ? 'लोड हुँदैछ...' : 'Loading...',
          processing: isNepali ? 'लोड हुँदैछ...' : 'Loading...',
          info: isNepali ? '_TOTAL_ मध्ये _START_ देखि _END_ प्रविष्टि' : 'Showing _START_ to _END_ of _TOTAL_ entries',
          infoEmpty: isNepali ? '० प्रविष्टि' : '0 entries',
          paginate: {
            previous: isNepali ? 'अघिल्लो' : 'Previous',
            next: isNepali ? 'अर्को' : 'Next',
          },
        },
        initComplete: function () {
          const $container = $(this.api().table().container());
          self._styleControls($container, lengthValues);
        },
        drawCallback: function () {
          const $container = $(this.api().table().container());
          self._styleControls($container, lengthValues);
        },
      };

      if (endpoint) {
        options.ajax = {
          url: endpoint,
          type: 'GET',
        };

        if (columns.length) {
          options.columns = columns;
        }
      }

      $table.DataTable(options);
      $table.data('hDatatableReady', true);
    },

    _styleControls($container, lengthMenu) {
      const $length = $container.find('div.dataTables_length select');
      const $search = $container.find('div.dataTables_filter input');
      $length.addClass('form-select form-select-sm').attr('aria-label', 'Rows per page');
      $search.addClass('form-control form-control-sm').attr('aria-label', 'Search table');
      this._normalizeLengthOptions($length, lengthMenu);
    },

    _normalizeLengthOptions($select, lengthMenu) {
      if (!$select || !$select.length) return;
      const expectedValues = Array.isArray(lengthMenu) && lengthMenu.length
        ? lengthMenu.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
        : [10, 20, 50, 100];

      if (!expectedValues.length) return;

      const currentLabels = $select.find('option').map((_, option) => String(option.text || '').trim()).get();
      const expectedLabels = expectedValues.map((value) => String(value));
      const mismatch = currentLabels.length !== expectedLabels.length
        || currentLabels.some((label, index) => label !== expectedLabels[index]);

      if (!mismatch) return;

      const currentValue = Number($select.val());
      $select.empty();
      expectedValues.forEach((value) => {
        $select.append($('<option/>', {
          value,
          text: String(value),
        }));
      });

      const fallback = expectedValues.includes(currentValue) ? currentValue : expectedValues[0];
      $select.val(String(fallback));
    },

    _columns($table) {
      const columns = [];
      const self = this;

      $table.find('thead th[data-col]').each((_, th) => {
        const key = String($(th).data('col') || '').trim();
        if (!key) return;
        const className = String(th.className || '').trim();
        const rawOrderable = String($(th).data('orderable') ?? '').trim().toLowerCase();
        const rawSearchable = String($(th).data('searchable') ?? '').trim().toLowerCase();
        const rawDate = String($(th).data('date') ?? '').trim().toLowerCase();
        const orderable = rawOrderable === '' ? true : !['false', '0', 'no'].includes(rawOrderable);
        const searchable = rawSearchable === '' ? true : !['false', '0', 'no'].includes(rawSearchable);
        const isDateOnly = rawDate === 'date'
          || (rawDate === '' && String(key).toLowerCase().endsWith('_date'));
        const isDateColumn = rawDate === ''
          ? self._looksLikeDateColumn(key)
          : ['1', 'true', 'yes', 'date', 'datetime'].includes(rawDate);

        const column = {
          data: key,
          name: key,
          className,
          orderable,
          searchable,
          defaultContent: '<span class="h-cell-empty">Empty</span>',
        };

        if (isDateColumn) {
          column.render = function (value, type) {
            return self._renderDateCell(value, type, !isDateOnly);
          };
        }

        columns.push(column);
      });

      return columns;
    },

    _locale() {
      return String(document.body?.dataset?.uiLocale || 'en').toLowerCase() === 'ne' ? 'ne' : 'en';
    },

    _looksLikeDateColumn(key) {
      const normalized = String(key || '').toLowerCase();
      if (!normalized) return false;
      return normalized.endsWith('_at')
        || normalized.includes('datetime')
        || normalized.includes('date')
        || normalized.includes('time');
    },

    _renderDateCell(value, type, withTime = true) {
      const raw = String(value ?? '').trim();
      if (!raw) {
        return type === 'display' ? '<span class="h-cell-empty">Empty</span>' : '';
      }

      if (type === 'sort' || type === 'type') {
        const ts = Date.parse(raw);
        return Number.isNaN(ts) ? raw : String(ts);
      }

      const locale = this._locale();
      if (window.HNepaliDate && typeof window.HNepaliDate.dual === 'function') {
        return this._escape(window.HNepaliDate.dual(raw, { locale, withTime }));
      }

      const parsed = new Date(raw);
      if (!Number.isNaN(parsed.getTime())) {
        if (!withTime) {
          return this._escape(parsed.toLocaleDateString(locale === 'ne' ? 'ne-NP' : 'en-US', {
            timeZone: 'Asia/Kathmandu',
          }));
        }

        return this._escape(parsed.toLocaleString(locale === 'ne' ? 'ne-NP' : 'en-US', {
          timeZone: 'Asia/Kathmandu',
        }));
      }

      return this._escape(raw);
    },

    _escape(input) {
      return String(input ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },

    _parseLengthMenu(raw) {
      const fallback = [10, 20, 50, 100];
      let values = [];

      if (Array.isArray(raw)) {
        values = raw.map((item) => Number(item)).filter((item) => Number.isFinite(item) && item > 0);
      } else if (typeof raw === 'number') {
        values = [raw];
      } else {
        const text = String(raw || '').trim();
        const matches = text.match(/\d+/g) || [];
        values = matches.map((item) => Number(item)).filter((item) => Number.isFinite(item) && item > 0);
      }

      if (!values.length) return fallback;
      return Array.from(new Set(values));
    },
  };

  /* --------------------------
     HEditor: rich text editor
  ---------------------------*/
  const HEditor = {
    selector: '[data-editor], .h-editor',
    dialogId: 'h-editor-tool-modal',
    _dialogReady: false,
    _activeDialogCleanup: null,
    toolbarGroups: [
      [
        {
          type: 'select',
          cmd: 'formatBlock',
          title: 'Text style',
          options: [
            { value: 'p', label: 'Paragraph' },
            { value: 'h2', label: 'Heading 2' },
            { value: 'h3', label: 'Heading 3' },
            { value: 'h4', label: 'Heading 4' },
            { value: 'blockquote', label: 'Blockquote' },
            { value: 'pre', label: 'Code block' },
          ],
        },
      ],
      [
        { cmd: 'bold', label: '<i class="fa-solid fa-bold"></i>', title: 'Bold' },
        { cmd: 'italic', label: '<i class="fa-solid fa-italic"></i>', title: 'Italic' },
        { cmd: 'underline', label: '<i class="fa-solid fa-underline"></i>', title: 'Underline' },
        { cmd: 'strikeThrough', label: '<i class="fa-solid fa-strikethrough"></i>', title: 'Strikethrough' },
        { cmd: 'inlineCode', label: '<i class="fa-solid fa-code"></i>', title: 'Inline code' },
      ],
      [
        { cmd: 'insertUnorderedList', label: '<i class="fa-solid fa-list-ul"></i>', title: 'Bulleted list' },
        { cmd: 'insertOrderedList', label: '<i class="fa-solid fa-list-ol"></i>', title: 'Numbered list' },
        { cmd: 'outdent', label: '<i class="fa-solid fa-outdent"></i>', title: 'Outdent' },
        { cmd: 'indent', label: '<i class="fa-solid fa-indent"></i>', title: 'Indent' },
      ],
      [
        { cmd: 'justifyLeft', label: '<i class="fa-solid fa-align-left"></i>', title: 'Align left' },
        { cmd: 'justifyCenter', label: '<i class="fa-solid fa-align-center"></i>', title: 'Align center' },
        { cmd: 'justifyRight', label: '<i class="fa-solid fa-align-right"></i>', title: 'Align right' },
      ],
      [
        { cmd: 'createLink', label: '<i class="fa-solid fa-link"></i>', title: 'Insert link' },
        { cmd: 'unlink', label: '<i class="fa-solid fa-link-slash"></i>', title: 'Remove link' },
        { cmd: 'insertImage', label: '<i class="fa-solid fa-image"></i>', title: 'Insert image URL' },
        { cmd: 'insertTable', label: '<i class="fa-solid fa-table"></i>', title: 'Insert table' },
        { cmd: 'insertCanvas', label: '<i class="fa-solid fa-vector-square"></i>', title: 'Insert canvas block' },
        { cmd: 'insertHorizontalRule', label: '<i class="fa-solid fa-grip-lines"></i>', title: 'Horizontal line' },
      ],
      [
        { cmd: 'undo', label: '<i class="fa-solid fa-rotate-left"></i>', title: 'Undo' },
        { cmd: 'redo', label: '<i class="fa-solid fa-rotate-right"></i>', title: 'Redo' },
        { cmd: 'removeFormat', label: '<i class="fa-solid fa-eraser"></i>', title: 'Clear formatting' },
      ],
    ],
    stateCommands: ['bold', 'italic', 'underline', 'strikeThrough', 'insertUnorderedList', 'insertOrderedList', 'justifyLeft', 'justifyCenter', 'justifyRight'],

    init(root) {
      this._ensureDialog();
      const ctx = root && root.querySelectorAll ? root : document;
      ctx.querySelectorAll(this.selector).forEach((el) => {
        if (typeof window.Quill === 'function') {
          this.setupQuill(el);
          return;
        }
        this.setup(el);
      });
    },

    setup(el) {
      if (el.dataset.hEditorReady === '1') return;

      el.dataset.hEditorReady = '1';
      el.classList.add('h-editor');
      el.setAttribute('contenteditable', 'true');
      el.setAttribute('spellcheck', 'true');

      const isBare = el.dataset.editor === 'bare' || el.classList.contains('h-editor--bare');
      const editorName = el.dataset.editorName || el.getAttribute('name') || el.getAttribute('id') || '';
      const placeholder = el.dataset.placeholder || 'Start typing...';

      if (!el.textContent.trim()) {
        el.setAttribute('data-placeholder', placeholder);
      }

      if (!isBare) this._insertToolbar(el);
      this._bindEditorEvents(el, editorName);
      this._syncHiddenField(el, editorName);
      this._decorateCanvases(el);
    },

    setupQuill(el) {
      if (!el || el.dataset.hEditorReady === '1') return;

      el.dataset.hEditorReady = '1';
      const editorName = el.dataset.editorName || el.getAttribute('name') || el.getAttribute('id') || '';
      const placeholder = el.dataset.placeholder || 'Start typing...';
      const initialHtml = String(el.innerHTML || '');

      el.classList.add('h-editor-quill');
      el.removeAttribute('contenteditable');
      el.removeAttribute('spellcheck');
      el.removeAttribute('data-placeholder');

      const modules = {
        toolbar: [
          [{ header: [2, 3, 4, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          [{ align: [] }],
          ['blockquote', 'code-block'],
          ['link', 'image'],
          ['clean'],
        ],
      };

      const quill = new window.Quill(el, {
        theme: 'snow',
        placeholder,
        modules,
      });

      quill.clipboard.dangerouslyPasteHTML(initialHtml);

      const toolbar = quill.getModule('toolbar');
      if (toolbar && typeof toolbar.addHandler === 'function') {
        toolbar.addHandler('image', () => {
          const range = quill.getSelection(true);
          this._openToolModal('image').then((payload) => {
            if (!payload || !payload.src) return;
            if (!this._isSafeUrl(payload.src)) return;
            const index = range && Number.isFinite(range.index) ? range.index : quill.getLength();
            quill.insertEmbed(index, 'image', payload.src, 'user');
            quill.setSelection(index + 1, 0, 'user');
            this._syncHiddenFieldQuill(quill, editorName, el);
          });
        });
      }

      quill.on('text-change', () => {
        this._syncHiddenFieldQuill(quill, editorName, el);
      });

      this._syncHiddenFieldQuill(quill, editorName, el);

      const form = el.closest('form');
      if (form && editorName) {
        form.addEventListener('submit', () => {
          this._syncHiddenFieldQuill(quill, editorName, el);
        });
      }
    },

    _syncHiddenFieldQuill(quill, editorName, editorEl) {
      if (!quill || !editorName || !editorEl) return;

      const form = editorEl.closest('form');
      if (!form) return;

      let hidden = form.querySelector('textarea[data-editor-hidden="' + editorName + '"]');
      if (!hidden) {
        hidden = document.createElement('textarea');
        hidden.name = editorName;
        hidden.setAttribute('data-editor-hidden', editorName);
        hidden.style.display = 'none';
        form.appendChild(hidden);
      }

      const html = String(quill.root.innerHTML || '').trim();
      hidden.value = html === '<p><br></p>' ? '' : html;
    },

    _insertToolbar(editorEl) {
      if (editorEl.previousElementSibling && editorEl.previousElementSibling.classList.contains('h-editor-toolbar')) {
        return;
      }

      const toolbar = document.createElement('div');
      toolbar.className = 'h-editor-toolbar';

      toolbar.innerHTML = this.toolbarGroups
        .map((group) => {
          const controls = group
            .map((item) => {
              if (item.type === 'select') {
                const options = (item.options || [])
                  .map((option) => `<option value="${option.value}">${option.label}</option>`)
                  .join('');
                return `
                  <select class="h-editor-select" data-cmd="${item.cmd}" title="${item.title}">
                    ${options}
                  </select>
                `;
              }

              return `
                <button type="button" class="h-editor-btn" data-cmd="${item.cmd}" data-arg="${item.arg || ''}" title="${item.title}" aria-label="${item.title}">
                  ${item.label}
                </button>
              `;
            })
            .join('');

          return `<div class="h-editor-group">${controls}</div>`;
        })
        .join('');

      editorEl.parentNode.insertBefore(toolbar, editorEl);
      editorEl.dataset.editorToolbarId = this._editorId(editorEl);
      toolbar.dataset.editorToolbar = editorEl.dataset.editorToolbarId;

      toolbar.addEventListener('mousedown', (event) => {
        if (event.target.closest('button[data-cmd]')) {
          event.preventDefault();
        }
      });

      toolbar.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cmd]');
        if (!button) return;
        event.preventDefault();
        event.stopPropagation();

        const cmd = button.dataset.cmd;
        const arg = button.dataset.arg || null;

        editorEl.focus();
        this._execCommand(editorEl, cmd, arg);
      });

      toolbar.addEventListener('change', (event) => {
        const select = event.target.closest('select[data-cmd]');
        if (!select) return;
        event.preventDefault();
        event.stopPropagation();
        editorEl.focus();
        this._execCommand(editorEl, String(select.dataset.cmd || ''), String(select.value || 'p'));
      });
    },

    _bindEditorEvents(editorEl, editorName) {
      editorEl.addEventListener('input', () => {
        this._refreshToolbarState(editorEl);
        this._syncHiddenField(editorEl, editorName);
      });
      editorEl.addEventListener('blur', () => {
        this._sanitize(editorEl);
        this._refreshToolbarState(editorEl);
        this._syncHiddenField(editorEl, editorName);
      });
      editorEl.addEventListener('focus', () => {
        editorEl.classList.add('is-focused');
        this._refreshToolbarState(editorEl);
      });
      editorEl.addEventListener('blur', () => editorEl.classList.remove('is-focused'));
      editorEl.addEventListener('paste', (event) => {
        if (editorEl.dataset.paste === 'rich') return;
        event.preventDefault();
        const text = (event.clipboardData || window.clipboardData).getData('text/plain');
        this._insertPlainText(text);
      });

      editorEl.addEventListener('keydown', (event) => {
        if (!(event.metaKey || event.ctrlKey)) return;

        const key = String(event.key || '').toLowerCase();
        if (!['b', 'i', 'u', 'k', 'z', 'y', '`'].includes(key)) return;
        event.preventDefault();

        if (key === 'b') this._execCommand(editorEl, 'bold');
        if (key === 'i') this._execCommand(editorEl, 'italic');
        if (key === 'u') this._execCommand(editorEl, 'underline');
        if (key === 'k') this._execCommand(editorEl, 'createLink');
        if (key === 'z') this._execCommand(editorEl, 'undo');
        if (key === 'y') this._execCommand(editorEl, 'redo');
        if (key === '`') this._execCommand(editorEl, 'inlineCode');
      });

      document.addEventListener('selectionchange', () => {
        if (!editorEl.contains(document.activeElement)) return;
        this._refreshToolbarState(editorEl);
      });
    },

    _execCommand(editorEl, cmd, arg = null) {
      if (!editorEl || !cmd) return;

      if (cmd === 'createLink') {
        const savedRange = this._saveSelection(editorEl);
        this._openToolModal('link').then((payload) => {
          if (!payload || !payload.url) return;
          if (!this._isSafeUrl(payload.url)) return;

          editorEl.focus();
          this._restoreSelection(editorEl, savedRange);

          const selection = window.getSelection();
          const hasSelection = selection && !selection.isCollapsed;
          if (hasSelection) {
            document.execCommand('createLink', false, payload.url);
          } else {
            const label = String(payload.text || payload.url);
            this._insertHtml(editorEl, `<a href="${this._escapeAttribute(payload.url)}" target="_blank" rel="noopener noreferrer">${this._escapeHtml(label)}</a>`);
          }

          this._finalize(editorEl);
        });
        return;
      } else if (cmd === 'insertImage') {
        const savedRange = this._saveSelection(editorEl);
        this._openToolModal('image').then((payload) => {
          if (!payload || !payload.src) return;
          if (!this._isSafeUrl(payload.src)) return;

          editorEl.focus();
          this._restoreSelection(editorEl, savedRange);
          this._insertHtml(
            editorEl,
            `<img src="${this._escapeAttribute(payload.src)}" alt="${this._escapeAttribute(payload.alt || '')}">`
          );
          this._finalize(editorEl);
        });
        return;
      } else if (cmd === 'insertTable') {
        const savedRange = this._saveSelection(editorEl);
        this._openToolModal('table').then((payload) => {
          if (!payload) return;
          const rows = Number(payload.rows || 2);
          const cols = Number(payload.cols || 2);
          const safeRows = Number.isFinite(rows) ? Math.max(1, Math.min(10, Math.floor(rows))) : 2;
          const safeCols = Number.isFinite(cols) ? Math.max(1, Math.min(8, Math.floor(cols))) : 2;
          const bodyRows = Array.from({ length: safeRows })
            .map(() => `<tr>${Array.from({ length: safeCols }).map(() => '<td><br></td>').join('')}</tr>`)
            .join('');

          editorEl.focus();
          this._restoreSelection(editorEl, savedRange);
          this._insertHtml(editorEl, `<table><tbody>${bodyRows}</tbody></table><p><br></p>`);
          this._finalize(editorEl);
        });
        return;
      } else if (cmd === 'insertCanvas') {
        const savedRange = this._saveSelection(editorEl);
        this._openToolModal('canvas').then((payload) => {
          if (!payload) return;
          const width = Number(payload.width || 640);
          const height = Number(payload.height || 280);
          const safeWidth = Number.isFinite(width) ? Math.max(160, Math.min(1440, Math.floor(width))) : 640;
          const safeHeight = Number.isFinite(height) ? Math.max(120, Math.min(900, Math.floor(height))) : 280;
          const bg = String(payload.background || '#0f172a').trim();
          const isSafeHex = /^#[0-9a-fA-F]{6}$/.test(bg);

          editorEl.focus();
          this._restoreSelection(editorEl, savedRange);
          this._insertHtml(
            editorEl,
            `<canvas width="${safeWidth}" height="${safeHeight}" data-bg="${this._escapeAttribute(isSafeHex ? bg : '#0f172a')}"></canvas><p><br></p>`
          );
          this._finalize(editorEl);
        });
        return;
      } else if (cmd === 'inlineCode') {
        this._toggleInlineCode(editorEl);
      } else if (cmd === 'formatBlock') {
        document.execCommand('formatBlock', false, arg || 'p');
      } else {
        document.execCommand(cmd, false, arg || null);
      }

      this._finalize(editorEl);
    },

    _finalize(editorEl) {
      this._sanitize(editorEl);
      this._decorateCanvases(editorEl);
      editorEl.dispatchEvent(new Event('input', { bubbles: true }));
      this._syncHiddenField(editorEl, editorEl.dataset.editorName || editorEl.getAttribute('name') || '');
      this._refreshToolbarState(editorEl);
    },

    _syncHiddenField(editorEl, editorName) {
      const form = editorEl.closest('form');
      if (!form || !editorName) return;

      let hidden = form.querySelector('textarea[data-editor-hidden="' + editorName + '"]');
      if (!hidden) {
        hidden = document.createElement('textarea');
        hidden.name = editorName;
        hidden.setAttribute('data-editor-hidden', editorName);
        hidden.style.display = 'none';
        form.appendChild(hidden);
      }

      hidden.value = editorEl.innerHTML.trim();
    },

    _sanitize(editorEl) {
      const doc = new DOMParser().parseFromString('<div>' + editorEl.innerHTML + '</div>', 'text/html');
      const root = doc.body.firstElementChild;
      if (!root) return;

      const allowedTags = new Set([
        'P', 'BR', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
        'B', 'STRONG', 'I', 'EM', 'U', 'S', 'STRIKE',
        'A', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'PRE', 'CODE',
        'HR', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD',
        'IMG', 'SPAN', 'CANVAS',
      ]);

      root.querySelectorAll('script,style,iframe,object,embed,form,input,button,textarea,select').forEach((node) => node.remove());
      root.querySelectorAll('*').forEach((node) => {
        if (!allowedTags.has(node.tagName)) {
          const text = doc.createTextNode(node.textContent || '');
          node.replaceWith(text);
          return;
        }

        Array.from(node.attributes).forEach((attr) => {
          const name = attr.name.toLowerCase();
          const value = String(attr.value || '');

          if (name.startsWith('on') || name === 'style' || name === 'class' || name === 'id') {
            node.removeAttribute(attr.name);
            return;
          }

          if (node.tagName === 'A') {
            if (!['href', 'target', 'rel'].includes(name)) {
              node.removeAttribute(attr.name);
              return;
            }
            if (name === 'href' && !this._isSafeUrl(value)) {
              node.removeAttribute('href');
            }
            if (name === 'target' && value !== '_blank') {
              node.removeAttribute('target');
            }
            if (name === 'rel') {
              node.setAttribute('rel', 'noopener noreferrer');
            }
            return;
          }

          if (node.tagName === 'IMG') {
            if (!['src', 'alt'].includes(name)) {
              node.removeAttribute(attr.name);
              return;
            }
            if (name === 'src' && !this._isSafeUrl(value)) {
              node.remove();
            }
            return;
          }

          if ((node.tagName === 'TH' || node.tagName === 'TD') && ['colspan', 'rowspan'].includes(name)) {
            return;
          }

          if (node.tagName === 'CANVAS') {
            if (['width', 'height', 'data-bg'].includes(name)) {
              return;
            }

            node.removeAttribute(attr.name);
            return;
          }

          node.removeAttribute(attr.name);
        });
      });

      editorEl.innerHTML = root.innerHTML;
    },

    _decorateCanvases(editorEl) {
      if (!editorEl) return;
      editorEl.querySelectorAll('canvas').forEach((canvas) => {
        const bg = String(canvas.getAttribute('data-bg') || '').trim();
        if (/^#[0-9a-fA-F]{6}$/.test(bg)) {
          canvas.style.backgroundColor = bg;
        } else {
          canvas.style.backgroundColor = '#0f172a';
        }
        canvas.style.display = 'block';
        canvas.style.maxWidth = '100%';
        canvas.style.width = '100%';
        canvas.style.border = '1px dashed rgba(148, 163, 184, 0.45)';
        canvas.style.borderRadius = '8px';
      });
    },

    _refreshToolbarState(editorEl) {
      if (!editorEl) return;
      const toolbarId = editorEl.dataset.editorToolbarId;
      if (!toolbarId) return;
      const toolbar = document.querySelector('.h-editor-toolbar[data-editor-toolbar="' + toolbarId + '"]');
      if (!toolbar) return;

      this.stateCommands.forEach((cmd) => {
        const active = Boolean(document.queryCommandState && document.queryCommandState(cmd));
        toolbar.querySelectorAll('[data-cmd="' + cmd + '"]').forEach((button) => {
          button.classList.toggle('is-active', active);
        });
      });

      const formatSelect = toolbar.querySelector('select[data-cmd="formatBlock"]');
      if (formatSelect && document.queryCommandValue) {
        const value = String(document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, '');
        if (value) formatSelect.value = value;
      }
    },

    _toggleInlineCode(editorEl) {
      const selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) return;

      const range = selection.getRangeAt(0);
      const anchor = selection.anchorNode && selection.anchorNode.parentElement ? selection.anchorNode.parentElement.closest('code') : null;
      if (anchor && editorEl.contains(anchor)) {
        const text = document.createTextNode(anchor.textContent || '');
        anchor.replaceWith(text);
        return;
      }

      if (selection.isCollapsed) {
        this._insertHtml(editorEl, '<code>code</code>');
        return;
      }

      const fragment = range.extractContents();
      const code = document.createElement('code');
      code.appendChild(fragment);
      range.insertNode(code);
      selection.removeAllRanges();
      const next = document.createRange();
      next.selectNodeContents(code);
      selection.addRange(next);
    },

    _insertHtml(editorEl, html) {
      editorEl.focus();
      if (document.execCommand) {
        document.execCommand('insertHTML', false, html);
        return;
      }

      const selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) return;
      const range = selection.getRangeAt(0);
      range.deleteContents();
      const wrapper = document.createElement('div');
      wrapper.innerHTML = html;
      const fragment = document.createDocumentFragment();
      while (wrapper.firstChild) fragment.appendChild(wrapper.firstChild);
      range.insertNode(fragment);
    },

    _insertPlainText(text) {
      if (!text) return;
      if (document.execCommand) {
        document.execCommand('insertText', false, text);
        return;
      }

      const selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) return;
      const range = selection.getRangeAt(0);
      range.deleteContents();
      range.insertNode(document.createTextNode(text));
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    },

    _saveSelection(editorEl) {
      const selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) return null;

      const range = selection.getRangeAt(0);
      if (!editorEl.contains(range.commonAncestorContainer)) return null;
      return range.cloneRange();
    },

    _restoreSelection(editorEl, range) {
      if (!range) return;
      const selection = window.getSelection();
      if (!selection) return;
      editorEl.focus();
      selection.removeAllRanges();
      selection.addRange(range);
    },

    _ensureDialog() {
      if (this._dialogReady) return;

      let modal = document.getElementById(this.dialogId);
      if (!modal) {
        modal = document.createElement('div');
        modal.id = this.dialogId;
        modal.className = 'h-modal-overlay h-editor-modal';
        modal.innerHTML = `
          <div class="h-modal h-editor-modal-shell">
            <div class="h-modal-head">
              <div class="h-modal-title" data-editor-modal-title>Editor Tool</div>
              <button type="button" class="h-modal-close" data-editor-modal-close>×</button>
            </div>
            <form class="h-modal-body" data-editor-modal-form></form>
          </div>
        `;
        document.body.appendChild(modal);
      }

      this._dialogReady = true;
    },

    _openToolModal(type) {
      this._ensureDialog();

      const modal = document.getElementById(this.dialogId);
      if (!modal) {
        return Promise.resolve(null);
      }

      const titleEl = modal.querySelector('[data-editor-modal-title]');
      const formEl = modal.querySelector('[data-editor-modal-form]');
      if (!titleEl || !formEl) {
        return Promise.resolve(null);
      }

      const config = {
        link: {
          title: 'Insert Link',
          html: `
            <div class="h-editor-modal-grid">
              <div>
                <label class="h-label" style="display:block;">URL</label>
                <input type="text" class="form-control" name="url" placeholder="https://example.com" required>
              </div>
              <div>
                <label class="h-label" style="display:block;">Text</label>
                <input type="text" class="form-control" name="text" placeholder="Link text (optional)">
              </div>
            </div>
          `,
        },
        image: {
          title: 'Insert Image',
          html: `
            <div class="h-editor-modal-grid">
              <div>
                <label class="h-label" style="display:block;">Image URL</label>
                <input type="text" class="form-control" name="src" placeholder="https://example.com/image.png" required>
              </div>
              <div>
                <label class="h-label" style="display:block;">Alt text</label>
                <input type="text" class="form-control" name="alt" placeholder="Describe image">
              </div>
            </div>
            <div class="h-editor-media-tools">
              <button type="button" class="btn btn-outline-secondary btn-sm" data-editor-media-pick>
                <i class="fa-solid fa-photo-film me-1"></i>
                Choose From Media Library
              </button>
              <label class="btn btn-outline-secondary btn-sm mb-0 h-editor-media-upload">
                <i class="fa-solid fa-upload me-1"></i>
                Upload From Device
                <input type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.ico,image/*" data-editor-media-upload="1">
              </label>
            </div>
            <div class="h-editor-media-preview" data-editor-media-preview hidden>
              <img src="" alt="" data-editor-media-preview-img>
              <div class="h-editor-media-preview-copy">
                <div class="h-editor-media-preview-title">Selected media</div>
                <div class="h-editor-media-preview-url" data-editor-media-preview-url>URL will appear here.</div>
              </div>
            </div>
            <div class="h-note h-editor-modal-note">Tip: paste URL manually, upload directly, or choose from Media Library.</div>
          `,
        },
        table: {
          title: 'Insert Table',
          html: `
            <div class="h-editor-modal-grid">
              <div>
                <label class="h-label" style="display:block;">Rows</label>
                <input type="number" class="form-control" name="rows" min="1" max="10" value="2" required>
              </div>
              <div>
                <label class="h-label" style="display:block;">Columns</label>
                <input type="number" class="form-control" name="cols" min="1" max="8" value="2" required>
              </div>
            </div>
          `,
        },
        canvas: {
          title: 'Insert Canvas',
          html: `
            <div class="h-editor-modal-grid">
              <div>
                <label class="h-label" style="display:block;">Width</label>
                <input type="number" class="form-control" name="width" min="160" max="1440" value="640" required>
              </div>
              <div>
                <label class="h-label" style="display:block;">Height</label>
                <input type="number" class="form-control" name="height" min="120" max="900" value="280" required>
              </div>
              <div>
                <label class="h-label" style="display:block;">Background (hex)</label>
                <input type="text" class="form-control" name="background" value="#0f172a" pattern="^#[0-9a-fA-F]{6}$">
              </div>
            </div>
            <div class="h-note" style="margin-top:10px;">Canvas blocks are stored as editable HTML canvas nodes.</div>
          `,
        },
      }[type];

      if (!config) return Promise.resolve(null);

      titleEl.textContent = config.title;
      formEl.innerHTML = `
        ${config.html}
        <div class="h-editor-modal-actions">
          <button type="button" class="btn btn-outline-secondary" data-editor-modal-close>Cancel</button>
          <button type="submit" class="btn btn-primary">Apply</button>
        </div>
      `;

      if (type === 'image') {
        this._bindImageTool(formEl);
      }

      if (window.HModal) {
        window.HModal.open(this.dialogId);
      } else {
        modal.classList.add('show');
      }

      return new Promise((resolve) => {
        if (typeof this._activeDialogCleanup === 'function') {
          this._activeDialogCleanup();
          this._activeDialogCleanup = null;
        }

        let finished = false;
        const finish = (payload) => {
          if (finished) return;
          finished = true;
          if (typeof this._activeDialogCleanup === 'function') {
            this._activeDialogCleanup();
            this._activeDialogCleanup = null;
          }
          if (window.HModal) {
            window.HModal.close(this.dialogId);
          } else {
            modal.classList.remove('show');
          }
          resolve(payload);
        };

        const submitHandler = (event) => {
          event.preventDefault();
          const formData = new FormData(formEl);
          const payload = {};
          formData.forEach((value, key) => {
            payload[key] = String(value || '').trim();
          });
          finish(payload);
        };

        const closeHandlers = [];
        const registerClose = (button) => {
          const handler = () => finish(null);
          button.addEventListener('click', handler);
          closeHandlers.push([button, handler]);
        };

        const escapeHandler = (event) => {
          if (event.key !== 'Escape') return;
          finish(null);
        };
        const overlayHandler = (event) => {
          if (event.target === modal) finish(null);
        };

        formEl.addEventListener('submit', submitHandler);
        modal.querySelectorAll('[data-editor-modal-close]').forEach((button) => registerClose(button));
        document.addEventListener('keydown', escapeHandler);
        modal.addEventListener('click', overlayHandler);

        this._activeDialogCleanup = () => {
          formEl.removeEventListener('submit', submitHandler);
          document.removeEventListener('keydown', escapeHandler);
          modal.removeEventListener('click', overlayHandler);
          closeHandlers.forEach(([button, handler]) => {
            button.removeEventListener('click', handler);
          });
        };
      });
    },

    _bindImageTool(formEl) {
      const srcInput = formEl.querySelector('input[name="src"]');
      const pickButton = formEl.querySelector('[data-editor-media-pick]');
      const uploadInput = formEl.querySelector('input[data-editor-media-upload]');
      const preview = formEl.querySelector('[data-editor-media-preview]');
      const previewImg = formEl.querySelector('[data-editor-media-preview-img]');
      const previewUrl = formEl.querySelector('[data-editor-media-preview-url]');

      if (!srcInput) return;

      const setPreview = (url) => {
        const value = String(url || '').trim();
        if (!preview || !previewImg || !previewUrl) return;
        if (!value || !this._isSafeUrl(value)) {
          preview.setAttribute('hidden', 'hidden');
          previewImg.setAttribute('src', '');
          previewUrl.textContent = 'URL will appear here.';
          return;
        }
        preview.removeAttribute('hidden');
        previewImg.setAttribute('src', value);
        previewUrl.textContent = value;
      };

      srcInput.addEventListener('input', () => {
        setPreview(srcInput.value);
      });
      setPreview(srcInput.value);

      if (pickButton) {
        pickButton.addEventListener('click', () => {
          if (!window.HMediaManager || typeof window.HMediaManager.open !== 'function') {
            if (window.HToast) window.HToast.warning('Media manager is not available for this user.');
            return;
          }
          if (!document.getElementById('h-media-manager-modal')) {
            if (window.HToast) window.HToast.warning('Media manager modal is not available on this page.');
            return;
          }

          window.HMediaManager.open({
            targetInputId: '',
            onSelect: (url) => {
              const value = String(url || '').trim();
              if (!value || !this._isSafeUrl(value)) {
                if (window.HToast) window.HToast.warning('Selected media URL is not allowed.');
                return;
              }
              srcInput.value = value;
              srcInput.dispatchEvent(new Event('input', { bubbles: true }));
              srcInput.dispatchEvent(new Event('change', { bubbles: true }));
            },
          });
        });
      }

      if (uploadInput) {
        uploadInput.addEventListener('change', () => {
          const endpoint = String(document.body.dataset.fileManagerUploadUrl || '').trim();
          const file = uploadInput.files && uploadInput.files[0] ? uploadInput.files[0] : null;
          if (!file) return;
          if (!endpoint) {
            if (window.HToast) window.HToast.error('File manager upload endpoint is missing.');
            uploadInput.value = '';
            return;
          }

          const token = String((document.querySelector('meta[name="csrf-token"]') || {}).content || '');
          const data = new FormData();
          data.append('file', file);
          data.append('folder', 'editor');

          $.ajax({
            url: endpoint,
            method: 'POST',
            data,
            processData: false,
            contentType: false,
            headers: token ? { 'X-CSRF-TOKEN': token } : {},
          }).done((payload) => {
            const item = payload && payload.item ? payload.item : null;
            if (item && item.url) {
              srcInput.value = String(item.url);
              srcInput.dispatchEvent(new Event('input', { bubbles: true }));
              srcInput.dispatchEvent(new Event('change', { bubbles: true }));
              if (window.HToast) window.HToast.success('Image uploaded.');
            } else if (window.HToast) {
              window.HToast.warning('Upload completed but URL is missing.');
            }
          }).fail((xhr) => {
            const message = xhr && xhr.responseJSON && xhr.responseJSON.message
              ? xhr.responseJSON.message
              : 'Upload failed.';
            if (window.HToast) window.HToast.error(message);
          }).always(() => {
            uploadInput.value = '';
          });
        });
      }
    },

    _promptUrl(label = 'Enter URL') {
      const value = (window.prompt(label, 'https://') || '').trim();
      if (!value) return '';
      if (!this._isSafeUrl(value)) {
        window.alert('Only http(s), mailto, tel and relative URLs are allowed.');
        return '';
      }
      return value;
    },

    _isSafeUrl(url) {
      const value = String(url || '').trim();
      if (!value) return false;
      if (value.startsWith('/') || value.startsWith('#')) return true;
      return /^(https?:\/\/|mailto:|tel:)/i.test(value);
    },

    _editorId(editorEl) {
      if (editorEl.dataset.editorUid) return editorEl.dataset.editorUid;
      editorEl.dataset.editorUid = 'editor-' + Math.random().toString(36).slice(2, 10);
      return editorEl.dataset.editorUid;
    },

    _escapeHtml(text) {
      return String(text || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },

    _escapeAttribute(text) {
      return this._escapeHtml(text).replace(/`/g, '&#096;');
    },
  };

  /* --------------------------
     HIcons
  ---------------------------*/
  const HIcons = {
    init(root) {
      const ctx = root && root.querySelectorAll ? root : document;
      const spriteUrl = this._spriteUrl();

      ctx.querySelectorAll('[data-icon]').forEach((node) => {
        if (node.dataset.iconReady === '1') return;

        const name = node.dataset.icon;
        const size = Number(node.dataset.iconSize || node.dataset.w || 16);
        const classes = node.className || 'h-icon';

        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', classes);
        svg.setAttribute('width', String(size));
        svg.setAttribute('height', String(size));
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');

        const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', spriteUrl + '#' + name);
        use.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', spriteUrl + '#' + name);
        svg.appendChild(use);

        node.dataset.iconReady = '1';
        node.replaceWith(svg);
      });
    },

    svg(name, className, size) {
      const cls = className || 'h-icon';
      const sz = size || 16;
      const spriteUrl = this._spriteUrl();
      return `<svg class="${cls}" width="${sz}" height="${sz}" viewBox="0 0 24 24" aria-hidden="true"><use href="${spriteUrl}#${name}" xlink:href="${spriteUrl}#${name}"></use></svg>`;
    },

    _spriteUrl() {
      const body = document.body;
      const fromData = body && body.dataset ? body.dataset.iconSpriteUrl : '';
      return String(fromData || '/icons/icons.svg');
    },
  };

  /* --------------------------
     HSvgPie
  ---------------------------*/
  const HSvgPie = {
    init(root) {
      const ctx = root && root.querySelectorAll ? root : document;
      ctx.querySelectorAll('.h-svg-pie[data-pie]').forEach((el) => this.render(el));
    },

    render(container) {
      let data;
      try {
        data = JSON.parse(container.getAttribute('data-pie') || '[]');
      } catch (error) {
        return;
      }

      if (!Array.isArray(data) || !data.length) return;

      const total = data.reduce((sum, row) => sum + (Number(row.value) || 0), 0) || 1;
      const NS = 'http://www.w3.org/2000/svg';
      const svg = document.createElementNS(NS, 'svg');
      svg.setAttribute('viewBox', '0 0 240 120');
      svg.setAttribute('preserveAspectRatio', 'xMinYMid meet');

      const cx = 58;
      const cy = 60;
      const radius = 42;
      let angle = -Math.PI / 2;

      data.forEach((row, index) => {
        const value = Number(row.value) || 0;
        const arc = (value / total) * Math.PI * 2;
        const end = angle + arc;

        const x1 = cx + radius * Math.cos(angle);
        const y1 = cy + radius * Math.sin(angle);
        const x2 = cx + radius * Math.cos(end);
        const y2 = cy + radius * Math.sin(end);

        const largeArc = arc > Math.PI ? 1 : 0;
        const path = document.createElementNS(NS, 'path');
        const color = row.color || this._color(index);

        path.setAttribute('d', `M ${cx} ${cy} L ${x1} ${y1} A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2} Z`);
        path.setAttribute('fill', color);

        svg.appendChild(path);

        const y = 22 + index * 18;
        const swatch = document.createElementNS(NS, 'rect');
        swatch.setAttribute('x', '128');
        swatch.setAttribute('y', String(y));
        swatch.setAttribute('width', '10');
        swatch.setAttribute('height', '10');
        swatch.setAttribute('rx', '2');
        swatch.setAttribute('fill', color);
        svg.appendChild(swatch);

        const label = document.createElementNS(NS, 'text');
        label.setAttribute('x', '144');
        label.setAttribute('y', String(y + 9));
        label.setAttribute('font-size', '10');
        label.setAttribute('fill', getComputedStyle(document.documentElement).getPropertyValue('--t1').trim() || '#f0f0f0');
        label.textContent = `${row.label || 'Item'} (${value})`;
        svg.appendChild(label);

        angle = end;
      });

      container.innerHTML = '';
      container.appendChild(svg);
    },

    _color(index) {
      const colors = ['#2f7df6', '#2dd4bf', '#60a5fa', '#f87171', '#a78bfa', '#34d399'];
      return colors[index % colors.length];
    },
  };

  $(function () {
    HPlugins.init(document);

    window.HConfirm = HConfirm;
    window.HTabs = HTabs;
    window.HSelect = HSelect;
    window.HSelectRemote = HSelectRemote;
    window.HDataTable = HDataTable;
    window.HEditor = HEditor;
    window.HIcons = HIcons;
    window.HSvgPie = HSvgPie;
  });

  document.addEventListener('hspa:afterSwap', function (event) {
    const root = event.detail && event.detail.container ? event.detail.container : document;
    HPlugins.init(root);
  });
})(window, document, window.jQuery);
