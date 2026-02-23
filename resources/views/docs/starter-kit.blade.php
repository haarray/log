@extends('layouts.app')

@section('title', 'Starter Kit Docs')
@section('page_title', 'Starter Kit')
@section('topbar_extra')
  <span class="h-live-badge">
    <i class="fa-solid fa-book-open"></i>
    Docs
  </span>
@endsection

@section('content')
@php
  $currencies = [
    'NPR' => 'Nepalese Rupee',
    'USD' => 'US Dollar',
    'EUR' => 'Euro',
    'GBP' => 'Pound Sterling',
    'JPY' => 'Japanese Yen',
    'AUD' => 'Australian Dollar',
    'CAD' => 'Canadian Dollar',
    'INR' => 'Indian Rupee',
    'SGD' => 'Singapore Dollar',
    'CHF' => 'Swiss Franc',
  ];
  $leadAvatar = 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=2f7df6&color=ffffff&size=64';
@endphp

<div class="hl-docs">
  <div class="doc-head">
    <div>
      <div class="doc-title">Haarray Starter Kit</div>
      <div class="doc-sub">
        Production-ready Laravel scaffold with custom icon pack, select component, rich text editor,
        progressive SPA navigation, and reusable UI primitives.
      </div>
    </div>
    <div class="h-stack" style="align-items:flex-end;">
      <span class="h-pill gold">v0.1</span>
      <span class="h-pill teal">Server-first + Progressive UI</span>
    </div>
  </div>

  <div class="h-tab-shell h-doc-tabs" data-ui-tabs data-default-tab="docs-foundation">
    <div class="h-tab-nav" role="tablist" aria-label="Starter docs sections">
      <button type="button" class="h-tab-btn" data-tab-btn="docs-foundation">
        <i class="fa-solid fa-compass"></i>
        Foundation
      </button>
      <button type="button" class="h-tab-btn" data-tab-btn="docs-ui">
        <i class="fa-solid fa-palette"></i>
        UI Kit
      </button>
      <button type="button" class="h-tab-btn" data-tab-btn="docs-forms">
        <i class="fa-solid fa-rectangle-list"></i>
        Form Tools
      </button>
      <button type="button" class="h-tab-btn" data-tab-btn="docs-visuals">
        <i class="fa-solid fa-chart-pie"></i>
        Visuals
      </button>
      <button type="button" class="h-tab-btn" data-tab-btn="docs-tutorials">
        <i class="fa-solid fa-graduation-cap"></i>
        Tutorials
      </button>
    </div>

    <div class="h-tab-panel" data-tab-panel="docs-foundation">
      <section class="doc-block">
        <h3>Overview</h3>
        <p>
          This starter keeps Laravel controllers and Blade views first, while adding modern frontend enhancements as optional layers.
          If JavaScript fails, routes and forms still work with server rendering.
        </p>
        <div class="h-note" style="margin-top:10px;">
          Key modules: <code>HSPA</code>, <code>HSelect</code>, <code>HEditor</code>, <code>HConfirm</code>, <code>HIcons</code>, <code>HSvgPie</code>.
        </div>
      </section>

      <section class="doc-block">
        <h3>SPA Lifecycle Hooks</h3>
        <p>
          For page-specific scripts after partial swap, listen to lifecycle events emitted by <code>HSPA</code>.
        </p>

        <pre><code>document.addEventListener('hspa:beforeLoad', (event) =&gt; {
  console.log('Loading', event.detail.url);
});

document.addEventListener('hspa:afterSwap', (event) =&gt; {
  console.log('Container swapped', event.detail.container);
});</code></pre>
      </section>

      <section class="doc-block">
        <h3>Integration Checklist</h3>
        <ol>
          <li>Load <code>bootstrap.css</code> and <code>font-awesome.css</code>, then the merged bundle <code>/public/css/haarray.app.css</code>.</li>
          <li>Load jQuery, then the merged bundle <code>/public/js/haarray.app.js</code>.</li>
          <li>For remote datasets, use <code>data-select2-remote</code> + <code>data-endpoint</code> on a native <code>&lt;select&gt;</code>.</li>
          <li>Keep page content inside <code>#h-spa-content</code> for partial navigation.</li>
          <li>Include <code>&lt;x-confirm-modal /&gt;</code> once in the layout.</li>
          <li>Add sidebar links for <code>Docs</code> and <code>Settings</code>, with dedicated pages for users and RBAC management.</li>
          <li>For Settings navigation, use query-aware URLs like <code>/settings?tab=settings-activity</code> and dedicated management pages <code>/settings/users</code> / <code>/settings/rbac</code>.</li>
          <li>Use Blade components (<code>&lt;x-icon&gt;</code>, <code>&lt;x-select&gt;</code>, <code>&lt;x-editor&gt;</code>) for consistent scaffolding.</li>
        </ol>
      </section>

      <section class="doc-block">
        <h3>Build a CRUD Page (Starter Pattern)</h3>
        <ol>
          <li>Create route pair: list page + DataTable JSON endpoint.</li>
          <li>Render list first with <code>table[data-h-datatable]</code> and sticky action column.</li>
          <li>Use one reusable modal form for create/edit and bind row actions with icon buttons.</li>
          <li>Add <code>data-confirm="true"</code> for delete forms so deletion only runs after confirmation.</li>
          <li>Rehydrate plugins automatically after SPA navigation via <code>hspa:afterSwap</code>.</li>
        </ol>
      </section>

      <section class="doc-block">
        <h3>Haarray Log Finance Stack</h3>
        <p>
          The <code>log</code> product extends this starter with production finance modules:
          <code>/accounts</code>, <code>/transactions</code>, <code>/portfolio</code>, and <code>/suggestions</code>.
        </p>
        <ol>
          <li>Track account balances per bank/wallet/cash account.</li>
          <li>Log debit/credit transactions with category + account linkage.</li>
          <li>Track IPO opportunities and your IPO/gold positions.</li>
          <li>Run suggestion generation via <code>php artisan log:suggestions:run --notify</code>.</li>
          <li>Use Telegram webhook flow for bot logging with <code>/link</code>, <code>/log</code>, <code>/income</code>, and <code>/balance</code>.</li>
          <li>Mirror core updates using <code>php artisan log:core:sync</code> (microservice-style workflow).</li>
        </ol>
      </section>

      <section class="doc-block">
        <h3>Tutorial Packs</h3>
        <p>Use these docs as implementation blueprints when building your own modules.</p>
        <ul>
          <li><code>docs/tutorials/CRUD_WORKFLOW.md</code> for list + create/edit/delete flows.</li>
          <li><code>docs/tutorials/NOTIFIER_HELPER.md</code> for controller/service notification dispatch.</li>
          <li><code>docs/tutorials/INTEGRATIONS_FACEBOOK_TELEGRAM.md</code> for login + bot delivery setup.</li>
        </ul>
      </section>
    </div>

    <div class="h-tab-panel" data-tab-panel="docs-ui">
      <section class="doc-block">
        <h3>Icon System</h3>
        <p>
          Use either Haarray SVG icons or Font Awesome class-based icons (<code>&lt;i class=&quot;...&quot;&gt;</code>) for faster domain UIs
          like CRM, lead management, social channels, etc.
        </p>

        <pre><code>&lt;x-icon name="dashboard" class="h-icon h-icon--lg" label="Dashboard" /&gt;
&lt;span data-icon="wallet" data-icon-size="20" class="h-icon"&gt;&lt;/span&gt;
&lt;i class="fa-brands fa-facebook-f"&gt;&lt;/i&gt;</code></pre>

        <div class="demo">
          <div class="h-row" style="flex-wrap:wrap;">
            <x-icon name="dashboard" class="h-icon h-icon--lg" label="Dashboard" />
            <x-icon name="wallet" class="h-icon h-icon--lg" label="Wallet" />
            <x-icon name="chart-line" class="h-icon h-icon--lg" label="Chart" />
            <x-icon name="sparkles" class="h-icon h-icon--lg" label="Sparkles" />
            <x-icon name="settings" class="h-icon h-icon--lg" label="Settings" />
            <span data-icon="message" data-icon-size="20" class="h-icon"></span>
            <i class="fa-brands fa-facebook-f"></i>
            <i class="fa-brands fa-instagram"></i>
            <i class="fa-brands fa-linkedin-in"></i>
          </div>
        </div>
      </section>

      <section class="doc-block">
        <h3>Bootstrap + Font Awesome</h3>
        <p>
          Bootstrap and Font Awesome are bundled globally. Use those classes directly and Haarray theme variables
          will skin them through <code>haarray.app.css</code> (includes bridge + starter styles).
        </p>

        <pre><code>&lt;button class="btn btn-primary"&gt;
  &lt;i class="fa-solid fa-floppy-disk me-2"&gt;&lt;/i&gt;Save
&lt;/button&gt;</code></pre>

        <div class="demo">
          <div class="card p-3">
            <h5 class="mb-3">
              <i class="fa-solid fa-bolt text-warning me-2"></i>
              Bootstrap + Haarray theme
            </h5>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-primary"><i class="fa-solid fa-check me-2"></i>Primary</button>
              <button class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-2"></i>Secondary</button>
              <button class="btn btn-success"><i class="fa-solid fa-circle-check me-2"></i>Success</button>
            </div>
          </div>
        </div>
      </section>

      <section class="doc-block">
        <h3>Class Reference</h3>
        <p>Use these classes directly across pages. The list below covers both Haarray and Bootstrap utility usage.</p>

        <div class="table-responsive mt-2">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Class</th>
                <th>Type</th>
                <th>Purpose</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><code>.h-btn</code></td>
                <td>Haarray</td>
                <td>Primary button system with variants like <code>.primary</code>, <code>.ghost</code>, <code>.danger</code>.</td>
              </tr>
              <tr>
                <td><code>.h-card</code> / <code>.h-card-soft</code></td>
                <td>Haarray</td>
                <td>Reusable card surfaces for app panels and docs blocks.</td>
              </tr>
              <tr>
                <td><code>.h-select2-wrap</code></td>
                <td>Haarray</td>
                <td>Auto-generated wrapper for custom select component.</td>
              </tr>
              <tr>
                <td><code>.h-editor</code></td>
                <td>Haarray</td>
                <td>Rich editor area; supports toolbar and hidden textarea sync.</td>
              </tr>
              <tr>
                <td><code>.btn</code>, <code>.btn-primary</code></td>
                <td>Bootstrap</td>
                <td>Bootstrap buttons skinned to Haarray color tokens.</td>
              </tr>
              <tr>
                <td><code>.form-control</code>, <code>.form-select</code></td>
                <td>Bootstrap</td>
                <td>Inputs/selects with Bootstrap API but Haarray theme appearance.</td>
              </tr>
              <tr>
                <td><code>.row</code>, <code>.col-md-6</code>, <code>.g-3</code></td>
                <td>Bootstrap</td>
                <td>Grid layout utilities for responsive forms and panels.</td>
              </tr>
              <tr>
                <td><code>.fa-solid</code> + icon name</td>
                <td>Font Awesome</td>
                <td>Open-source icon classes for utility and action icons.</td>
              </tr>
              <tr>
                <td><code>select[data-select2-remote]</code></td>
                <td>Select2</td>
                <td>Remote server search with avatar/image rendering in results.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div class="h-tab-panel" data-tab-panel="docs-forms">
      <section class="doc-block">
        <h3>Remote Select2 (Server + Image)</h3>
        <p>
          For large datasets (leads, users, products), use Select2 AJAX mode. Results are fetched from server and support avatars/images.
          This starter ships a demo endpoint: <code>{{ route('ui.options.leads') }}</code>.
        </p>

        <pre><code>&lt;select
  data-select2-remote
  data-endpoint="{{ route('ui.options.leads') }}"
  data-placeholder="Search leads..."
  data-dropdown-parent=".hl-docs"
  data-min-input="1"&gt;
  &lt;option value="{{ auth()->id() }}" selected data-image="{{ $leadAvatar }}"&gt;{{ auth()->user()->name }}&lt;/option&gt;
&lt;/select&gt;</code></pre>

        <div class="demo">
          <label class="h-label" style="display:block;margin-bottom:8px;">Assign Lead Owner</label>
          <select
            class="form-select"
            data-select2-remote
            data-endpoint="{{ route('ui.options.leads') }}"
            data-placeholder="Search leads..."
            data-dropdown-parent=".hl-docs"
            data-min-input="1"
          >
            <option value="{{ auth()->id() }}" selected data-image="{{ $leadAvatar }}">{{ auth()->user()->name }}</option>
          </select>
        </div>
      </section>

      <section class="doc-block">
        <h3>Custom Select (HSelect)</h3>
        <p>
          <code>HSelect</code> provides searchable single/multi select UI while preserving native <code>&lt;select&gt;</code> submission for forms.
        </p>

        <pre><code>&lt;x-select
  name="currency"
  label="Primary Currency"
  :options="$currencies"
  placeholder="Choose currency"
/&gt;</code></pre>

        <div class="demo">
          <form>
            <div class="h-grid cols-2">
              <x-select
                name="currency"
                label="Primary Currency"
                :options="$currencies"
                value="NPR"
                placeholder="Choose currency"
              />

              <x-select
                name="watchlist"
                label="Watchlist"
                :options="$currencies"
                :value="['USD','EUR','INR']"
                :multiple="true"
                placeholder="Select currencies"
              />
            </div>
          </form>
        </div>
      </section>

      <section class="doc-block">
        <h3>Server-side DataTables (Yajra)</h3>
        <p>
          Use <code>data-h-datatable</code> for SPA-safe table initialization. This starter includes a sample endpoint:
          <code>{{ route('ui.datatables.users') }}</code>.
        </p>

        <pre><code>&lt;table
  data-h-datatable
  data-endpoint="{{ route('ui.datatables.users') }}"
  data-page-length="10"&gt;
  &lt;thead&gt;
    &lt;tr&gt;
      &lt;th data-col="id"&gt;ID&lt;/th&gt;
      &lt;th data-col="name"&gt;Name&lt;/th&gt;
      &lt;th data-col="email"&gt;Email&lt;/th&gt;
    &lt;/tr&gt;
  &lt;/thead&gt;
&lt;/table&gt;</code></pre>

        <pre><code>// Controller
return DataTables::eloquent(User::query())
  ->addColumn('actions', fn ($row) =&gt; '&lt;button class="btn btn-outline-secondary btn-sm h-action-icon"&gt;...&lt;/button&gt;')
  ->rawColumns(['actions'])
  -&gt;toJson();</code></pre>
      </section>

      <section class="doc-block">
        <h3>Rich Editor (HEditor)</h3>
        <p>
          <code>&lt;x-editor&gt;</code> now uses the open-source <code>Quill</code> engine (with Haarray styling),
          giving a stable toolbar for headings, lists, links, images, code blocks, and reliable hidden-field syncing.
        </p>
        <p>
          The image tool is wired to the same global Media Library modal, with inline upload support, so editor inserts and normal file inputs follow one workflow.
        </p>

        <pre><code>&lt;x-editor
  name="announcement"
  label="Announcement"
  value="&lt;p&gt;Welcome to Haarray Core&lt;/p&gt;"
  placeholder="Write update..."
/&gt;</code></pre>

        <div class="demo">
          <x-editor
            name="announcement_demo"
            label="Announcement"
            value="<p><strong>Starter tip:</strong> keep controllers server-first and layer JS progressively.</p>"
            placeholder="Write update..."
          />
        </div>
      </section>

      <section class="doc-block">
        <h3>Confirm Modal</h3>
        <p>
          Add <code>data-confirm="true"</code> to forms or links that require confirmation.
          For links, you can pass HTTP method via <code>data-confirm-method</code>.
        </p>

        <pre><code>&lt;a href="/posts/1"
   data-confirm="true"
   data-confirm-method="DELETE"
   data-confirm-title="Delete this post?"
   data-confirm-text="This action cannot be undone."&gt;
   Delete
&lt;/a&gt;</code></pre>

        <div class="demo h-row" style="justify-content:flex-start;">
          <a
            href="{{ route('dashboard') }}"
            class="h-btn danger"
            data-confirm="true"
            data-confirm-title="Leave docs page?"
            data-confirm-text="You will return to dashboard."
            data-confirm-ok="Go"
          >
            <x-icon name="arrow-right" class="h-icon" />
            Demo Confirm Link
          </a>
        </div>
      </section>

      <section class="doc-block">
        <h3>Global Search + Media Manager</h3>
        <p>
          The topbar ships with a global search modal (<code>⌘K / Ctrl+K</code>) and a full media manager.
          Search reads from <code>config('haarray.global_search')</code> and can be overridden from Settings JSON registry.
        </p>
        <pre><code>// Example dynamic source (Settings → App & Branding JSON)
[
  {
    "key": "invoice",
    "model": "App\\\\Models\\\\Invoice",
    "id": "id",
    "title": "invoice_no",
    "subtitle": "client_name",
    "search": ["invoice_no", "client_name"],
    "route": "invoices.show",
    "route_params": {"invoice": "{id}"},
    "permission": "view invoices",
    "icon": "fa-solid fa-file-invoice"
  }
]</code></pre>
      </section>
    </div>

    <div class="h-tab-panel" data-tab-panel="docs-visuals">
      <section class="doc-block">
        <h3>SVG Pie Charts</h3>
        <p>
          Render tiny analytics blocks without chart libraries.
          Set <code>data-pie</code> with JSON payload.
        </p>

        <pre><code>&lt;div class="h-svg-pie"
  data-pie='[{"label":"Savings","value":45},{"label":"Expense","value":35},{"label":"Investments","value":20}]'&gt;
&lt;/div&gt;</code></pre>

        <div class="demo">
          <div
            class="h-svg-pie"
            data-pie='[{"label":"Savings","value":45,"color":"#34d399"},{"label":"Expense","value":35,"color":"#2f7df6"},{"label":"Investments","value":20,"color":"#60a5fa"}]'
          ></div>
        </div>
      </section>
    </div>

    <div class="h-tab-panel" data-tab-panel="docs-tutorials">
      <section class="doc-block">
        <h3>Starter Tutorials</h3>
        <p>Use these guided docs to build production modules quickly with the same conventions used in this starter.</p>
        <ul>
          <li><code>docs/tutorials/CRUD_WORKFLOW.md</code> for list + create/edit/delete flow with DataTables and confirm modal.</li>
          <li><code>docs/tutorials/NOTIFIER_HELPER.md</code> for in-app and Telegram notification dispatch from controllers/services/jobs.</li>
          <li><code>docs/tutorials/MEDIA_MANAGER.md</code> for folder-based media management, URL injection, and S3/local behavior.</li>
          <li><code>docs/tutorials/INTEGRATIONS_FACEBOOK_TELEGRAM.md</code> for auth and messaging integration setup.</li>
          <li><code>docs/tutorials/DEPLOYMENT_MODES.md</code> for artisan server, PHP built-in server, and XAMPP subfolder deployment.</li>
        </ul>
      </section>

      <section class="doc-block">
        <h3>Media Manager API</h3>
        <p>Dedicated media page route: <code>{{ route('settings.media.index') }}</code>. Global modal and editor image picker both use the same API.</p>
        <pre><code>GET  /ui/file-manager            // list files + folders
POST /ui/file-manager/upload     // upload image/audio
POST /ui/file-manager/delete     // delete file
POST /ui/file-manager/folder     // create folder
POST /ui/file-manager/resize     // resize image (local storage mode)
GET  /ui/file-manager/export-csv // export media index</code></pre>
        <div class="h-note">
          Storage behavior: when <code>FILESYSTEM_DISK=s3</code> and bucket config is present, file-manager operations use S3.
          Otherwise it falls back to local <code>/public/uploads</code>.
        </div>
      </section>

      <section class="doc-block">
        <h3>Custom Error Views</h3>
        <p>Starter ships custom pages for <code>403</code>, <code>404</code>, <code>419</code>, <code>429</code>, <code>500</code>, and <code>503</code>.</p>
        <pre><code>resources/views/errors/layout.blade.php
resources/views/errors/403.blade.php
resources/views/errors/404.blade.php
resources/views/errors/419.blade.php
resources/views/errors/429.blade.php
resources/views/errors/500.blade.php
resources/views/errors/503.blade.php</code></pre>
      </section>
    </div>
  </div>
</div>
@endsection
