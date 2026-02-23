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
      const endpoint = String($table.data('endpoint') || '').trim();
      const columns = this._columns($table);
      const pageLength = Number($table.data('pageLength') || 10);
      const orderCol = Number($table.data('orderCol') || 0);
      const orderDir = String($table.data('orderDir') || 'desc').toLowerCase() === 'asc' ? 'asc' : 'desc';
      const lengthMenu = this._parseLengthMenu($table.data('lengthMenu'));
      const emptyText = String($table.data('emptyText') || 'Empty').trim() || 'Empty';
      const normalizedPageLength = Number.isFinite(pageLength) && pageLength > 0
        ? Math.max(1, Math.min(pageLength, 100))
        : (lengthMenu[0] || 10);
      const defaultPageLength = lengthMenu.includes(normalizedPageLength)
        ? normalizedPageLength
        : (lengthMenu[0] || 10);
      const self = this;

      if ($.fn.DataTable.isDataTable(tableEl)) {
        const api = $table.DataTable();
        if (endpoint && api.ajax) {
          api.ajax.url(endpoint).load(null, false);
        }
        if (api.columns && typeof api.columns.adjust === 'function') {
          api.columns.adjust();
        }
        this._styleControls($(api.table().container()), lengthMenu);
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
          searchPlaceholder: 'Search...',
          lengthMenu: 'Show _MENU_ rows',
          emptyTable: emptyText,
          zeroRecords: emptyText,
          loadingRecords: 'Loading...',
          processing: 'Loading...',
        },
        initComplete: function () {
          const $container = $(this.api().table().container());
          self._styleControls($container, lengthMenu);
        },
        drawCallback: function () {
          const $container = $(this.api().table().container());
          self._styleControls($container, lengthMenu);
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

      $table.find('thead th[data-col]').each((_, th) => {
        const key = String($(th).data('col') || '').trim();
        if (!key) return;
        const className = String(th.className || '').trim();
        const rawOrderable = String($(th).data('orderable') ?? '').trim().toLowerCase();
        const rawSearchable = String($(th).data('searchable') ?? '').trim().toLowerCase();
        const orderable = rawOrderable === '' ? true : !['false', '0', 'no'].includes(rawOrderable);
        const searchable = rawSearchable === '' ? true : !['false', '0', 'no'].includes(rawSearchable);
        columns.push({
          data: key,
          name: key,
          className,
          orderable,
          searchable,
          defaultContent: '<span class="h-cell-empty">Empty</span>',
        });
      });

      return columns;
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
