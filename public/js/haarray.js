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
      const pollSeconds = Number($('body').data('notificationsPollSeconds') || 20);
      const safePollMs = Math.max(10, Math.min(pollSeconds, 300)) * 1000;
      this._pollTimer = window.setInterval(() => this.refresh(true), safePollMs);
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

    init() {
      if (!$('#h-global-search-modal').length) return;

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
        this._timer = window.setTimeout(() => this.search(query), 180);
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
    _query: '',
    _searchTimer: null,

    init() {
      if (!$('#h-media-manager-modal').length) return;

      $(document).on('click', '[data-media-manager-open]', (event) => {
        event.preventDefault();
        this._targetInputId = String($(event.currentTarget).data('mediaTarget') || '').trim();
        this.open();
      });

      $(document).on('input', '#h-media-manager-search', (event) => {
        this._query = String(event.currentTarget.value || '').trim();
        window.clearTimeout(this._searchTimer);
        this._searchTimer = window.setTimeout(() => this.load(), 170);
      });

      $(document).on('click', '#h-media-manager-upload', (event) => {
        event.preventDefault();
        this.upload();
      });

      $(document).on('click', '[data-media-pick-url]', (event) => {
        event.preventDefault();
        const url = String($(event.currentTarget).data('mediaPickUrl') || '').trim();
        if (!url) return;
        this.applySelection(url);
      });
    },

    open() {
      this._syncTargetNote();
      HModal.open('h-media-manager-modal');
      this.load();
    },

    load() {
      const endpoint = String(document.body.dataset.fileManagerListUrl || '').trim();
      const grid = document.getElementById('h-media-manager-grid');
      if (!endpoint || !grid || !window.HApi) return;

      grid.innerHTML = '<div class="h-notif-empty"><i class="fa-solid fa-spinner fa-spin"></i><span>Loading media...</span></div>';
      HApi.get(endpoint, { q: this._query, limit: 160 })
        .done((payload) => {
          const items = Array.isArray(payload.items) ? payload.items : [];
          this.render(items);
        })
        .fail(() => {
          grid.innerHTML = '<div class="h-notif-empty"><i class="fa-regular fa-circle-xmark"></i><span>Unable to load media.</span></div>';
        });
    },

    render(items) {
      const grid = document.getElementById('h-media-manager-grid');
      if (!grid) return;

      if (!Array.isArray(items) || items.length === 0) {
        grid.innerHTML = '<div class="h-notif-empty"><i class="fa-regular fa-folder-open"></i><span>No files found.</span></div>';
        return;
      }

      const rows = items.map((item) => {
        const url = this._escape(item.url || '');
        const name = this._escape(item.name || 'file');
        const type = String(item.type || 'file');
        const extension = this._escape(item.extension || '');
        const size = this._escape(item.size_kb || '');
        const modified = this._escape(item.modified_at || '');

        let preview = '<div class="h-media-file-icon"><i class="fa-regular fa-file"></i></div>';
        if (type === 'image') {
          preview = `<img src="${url}" alt="${name}" loading="lazy">`;
        } else if (type === 'audio') {
          preview = `
            <div class="h-media-audio-preview">
              <i class="fa-solid fa-wave-square"></i>
              <audio controls preload="none" src="${url}"></audio>
            </div>
          `;
        }

        return `
          <article class="h-media-browser-card" data-type="${this._escape(type)}">
            <div class="h-media-browser-preview">${preview}</div>
            <div class="h-media-browser-meta">
              <div class="h-media-browser-name" title="${name}">${name}</div>
              <div class="h-media-browser-sub">${extension.toUpperCase()} • ${size} KB • ${modified}</div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-media-pick-url="${url}">
              <i class="fa-solid fa-check me-1"></i>Use
            </button>
          </article>
        `;
      }).join('');

      grid.innerHTML = rows;
    },

    upload() {
      const endpoint = String(document.body.dataset.fileManagerUploadUrl || '').trim();
      const fileInput = document.getElementById('h-media-manager-file');
      if (!endpoint || !fileInput || !(fileInput instanceof HTMLInputElement)) return;

      const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      if (!file) {
        HToast.warning('Choose a file first.');
        return;
      }

      const token = String((document.querySelector('meta[name="csrf-token"]') || {}).content || '');
      const data = new FormData();
      data.append('file', file);
      data.append('folder', 'library');

      $.ajax({
        url: endpoint,
        method: 'POST',
        data,
        processData: false,
        contentType: false,
        headers: token ? { 'X-CSRF-TOKEN': token } : {},
      }).done((payload) => {
        const item = payload && payload.item ? payload.item : null;
        fileInput.value = '';
        this.load();
        if (item && item.url && this._targetInputId) {
          this.applySelection(String(item.url));
        } else {
          HToast.success('Media uploaded.');
        }
      }).fail((xhr) => {
        const message = xhr && xhr.responseJSON && xhr.responseJSON.message
          ? xhr.responseJSON.message
          : 'Upload failed.';
        HToast.error(message);
      });
    },

    applySelection(url) {
      if (!this._targetInputId) {
        if (navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          navigator.clipboard.writeText(url)
            .then(() => HToast.success('Media URL copied to clipboard.'))
            .catch(() => HToast.info('Media selected.'));
        } else {
          HToast.info('Media selected.');
        }
        return;
      }

      const input = document.getElementById(this._targetInputId);
      if (!input || !('value' in input)) {
        HToast.warning('Target input not found for selected media.');
        return;
      }

      input.value = url;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      HToast.success('Media selected.');
      HModal.close('h-media-manager-modal');
    },

    _syncTargetNote() {
      const note = document.getElementById('h-media-manager-target-note');
      if (!note) return;
      if (!this._targetInputId) {
        note.hidden = true;
        note.textContent = '';
        return;
      }
      note.hidden = false;
      note.textContent = 'Selecting file for #' + this._targetInputId + '. Click "Use" to insert.';
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
        'browserNotifyEnabled',
        'pwaEnabled',
        'swUrl',
        'iconSpriteUrl',
        'fileManagerListUrl',
        'fileManagerUploadUrl',
        'globalSearchUrl',
        'faviconUrl',
        'themeColor',
        'notificationReadAllUrl',
        'notificationSoundUrl',
        'uiLocale',
        'hotReloadStreamUrl',
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
