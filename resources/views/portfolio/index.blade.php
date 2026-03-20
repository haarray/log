@extends('layouts.app')

@section('title', 'Portfolio')
@section('page_title', 'Portfolio')

@section('content')
  @php
    $defaultTab = (string) request()->query('tab', 'portfolio-positions');
    $allowedTabs = ['portfolio-positions', 'portfolio-gold', 'portfolio-master'];
    if (!in_array($defaultTab, $allowedTabs, true)) {
      $defaultTab = 'portfolio-positions';
    }
  @endphp

  <div class="h-page-header">
    <div>
      <div class="h-page-title">Portfolio Manager</div>
      <div class="h-page-sub">Track IPO applications, allotment gain, and gold holdings with a clean list-first workflow.</div>
    </div>
    <div class="h-page-actions">
      @can('manage portfolio')
        <form method="POST" action="{{ route('portfolio.sync-market') }}" data-spa>
          @csrf
          <button class="h-btn ghost" type="submit">
            <i class="fa-solid fa-arrows-rotate"></i>
            Sync Live Market
          </button>
        </form>
        <button type="button" class="h-btn primary" data-modal-open="portfolio-ipo-create-modal">
          <i class="ri-briefcase-4-line"></i>
          Add IPO Position
        </button>
        <button type="button" class="h-btn ghost" data-modal-open="portfolio-gold-create-modal">
          <i class="ri-coins-line"></i>
          Add Gold Position
        </button>
        <button type="button" class="h-btn ghost" data-modal-open="portfolio-master-create-modal">
          <i class="ri-database-2-line"></i>
          Add IPO Feed
        </button>
      @endcan
    </div>
  </div>

  <div class="h-grid-4" style="margin-bottom:16px;">
    <div class="h-stat-card">
      <div class="h-stat-label">IPO Invested</div>
      <div class="h-stat-val">NPR {{ number_format($summary['ipo_invested'], 2) }}</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-label">IPO Current</div>
      <div class="h-stat-val teal">NPR {{ number_format($summary['ipo_current'], 2) }}</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-label">Gold Invested</div>
      <div class="h-stat-val">NPR {{ number_format($summary['gold_invested'], 2) }}</div>
    </div>
    <div class="h-stat-card">
      <div class="h-stat-label">Gold Current</div>
      <div class="h-stat-val gold">NPR {{ number_format($summary['gold_current'], 2) }}</div>
    </div>
  </div>

  <div class="h-card">
    <div class="h-card-body p-0">
      <div class="h-tab-shell" id="portfolio-tabs" data-ui-tabs data-default-tab="{{ $defaultTab }}">
        <div class="h-tab-nav">
          <button type="button" class="h-tab-btn" data-tab-btn="portfolio-positions"><i class="fa-solid fa-chart-column"></i> IPO Positions</button>
          <button type="button" class="h-tab-btn" data-tab-btn="portfolio-gold"><i class="fa-solid fa-coins"></i> Gold Holdings</button>
          <button type="button" class="h-tab-btn" data-tab-btn="portfolio-master"><i class="fa-solid fa-wave-square"></i> IPO Feed</button>
        </div>

        <div class="h-tab-panel" data-tab-panel="portfolio-positions">
          <div class="table-responsive">
            <table
              class="table table-sm align-middle h-table-sticky-actions"
              data-h-datatable
              data-endpoint="{{ route('ui.datatables.portfolio.positions') }}"
              data-page-length="10"
              data-length-menu="10,20,50,100"
              data-order-col="0"
              data-order-dir="desc"
              data-empty-text="No IPO positions found"
            >
              <thead>
                <tr>
                  <th data-col="id">ID</th>
                  <th data-col="ipo_company_name">IPO</th>
                  <th data-col="status">Status</th>
                  <th data-col="units_applied">Applied</th>
                  <th data-col="units_allotted">Allotted</th>
                  <th data-col="invested_amount">Invested</th>
                  <th data-col="current_price">Current Price</th>
                  <th data-col="unrealized_gain">Gain / Loss</th>
                  <th data-col="actions" class="h-col-actions" data-orderable="false" data-searchable="false">Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>

        <div class="h-tab-panel" data-tab-panel="portfolio-gold" hidden>
          <div class="table-responsive">
            <table
              class="table table-sm align-middle h-table-sticky-actions"
              data-h-datatable
              data-endpoint="{{ route('ui.datatables.portfolio.gold') }}"
              data-page-length="10"
              data-length-menu="10,20,50,100"
              data-order-col="0"
              data-order-dir="desc"
              data-empty-text="No gold positions found"
            >
              <thead>
                <tr>
                  <th data-col="id">ID</th>
                  <th data-col="label">Label</th>
                  <th data-col="source">Source</th>
                  <th data-col="grams">Grams</th>
                  <th data-col="invested_value">Invested</th>
                  <th data-col="current_value">Current Value</th>
                  <th data-col="bought_at">Bought At</th>
                  <th data-col="actions" class="h-col-actions" data-orderable="false" data-searchable="false">Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>

        <div class="h-tab-panel" data-tab-panel="portfolio-master" hidden>
          <div class="table-responsive">
            <table
              class="table table-sm align-middle"
              data-h-datatable
              data-endpoint="{{ route('ui.datatables.portfolio.ipos') }}"
              data-page-length="10"
              data-length-menu="10,20,50,100"
              data-order-col="0"
              data-order-dir="desc"
              data-empty-text="No IPO feed rows found"
            >
              <thead>
                <tr>
                  <th data-col="id">ID</th>
                  <th data-col="company_name">Company</th>
                  <th data-col="symbol">Symbol</th>
                  <th data-col="status">Status</th>
                  <th data-col="open_date">Open</th>
                  <th data-col="close_date">Close</th>
                  <th data-col="price_per_unit">Issue Price</th>
                  <th data-col="market_price">Market Price</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('modals')
  @can('manage portfolio')
    <div class="h-modal-overlay" id="portfolio-ipo-create-modal">
      <div class="h-modal" style="max-width:860px;">
        <div class="h-modal-head">
          <div class="h-modal-title">Add IPO Position</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="{{ route('portfolio.positions.store') }}" class="row g-3" data-spa>
            @csrf
            <div class="col-lg-4">
              <label class="h-label">IPO</label>
              <select class="form-select" name="ipo_id" data-h-select required>
                <option value="">Select IPO</option>
                @foreach($ipos as $ipo)
                  <option value="{{ $ipo->id }}">{{ $ipo->company_name }} ({{ strtoupper($ipo->status) }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-lg-2">
              <label class="h-label">Status</label>
              <select class="form-select" name="status" data-h-select required>
                <option value="applied">Applied</option>
                <option value="allotted">Allotted</option>
                <option value="sold">Sold</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div class="col-lg-2">
              <label class="h-label">Applied Units</label>
              <input class="form-control" name="units_applied" type="number" min="0" value="0" required>
            </div>
            <div class="col-lg-2">
              <label class="h-label">Allotted Units</label>
              <input class="form-control" name="units_allotted" type="number" min="0" value="0">
            </div>
            <div class="col-lg-2">
              <label class="h-label">Invested Amount</label>
              <input class="form-control" name="invested_amount" type="number" step="0.01" value="0" required>
            </div>
            <div class="col-lg-3">
              <label class="h-label">Current Price</label>
              <input class="form-control" name="current_price" type="number" step="0.01">
            </div>
            <div class="col-lg-3">
              <label class="h-label">Applied Date</label>
              <input class="form-control" name="applied_at" type="date" value="{{ now()->toDateString() }}">
            </div>
            <div class="col-lg-3">
              <label class="h-label">Sold Date</label>
              <input class="form-control" name="sold_at" type="date">
            </div>
            <div class="col-lg-3">
              <label class="h-label">Notes</label>
              <input class="form-control" name="notes" placeholder="Optional">
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus me-2"></i>Save IPO Position</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="h-modal-overlay" id="portfolio-gold-create-modal">
      <div class="h-modal" style="max-width:760px;">
        <div class="h-modal-head">
          <div class="h-modal-title">Add Gold Position</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="{{ route('portfolio.gold.store') }}" class="row g-3" data-spa>
            @csrf
            <div class="col-lg-4">
              <label class="h-label">Label</label>
              <input class="form-control" name="label" placeholder="24k bullion">
            </div>
            <div class="col-lg-4">
              <label class="h-label">Source</label>
              <input class="form-control" name="source" placeholder="NMB Jewellers">
            </div>
            <div class="col-lg-4">
              <label class="h-label">Grams</label>
              <input class="form-control" name="grams" type="number" step="0.001" min="0.001" required>
            </div>
            <div class="col-lg-4">
              <label class="h-label">Buy / gram</label>
              <input class="form-control" name="buy_price_per_gram" type="number" step="0.01" min="0.01" required>
            </div>
            <div class="col-lg-4">
              <label class="h-label">Current / gram</label>
              <input class="form-control" name="current_price_per_gram" type="number" step="0.01" min="0.01">
            </div>
            <div class="col-lg-4">
              <label class="h-label">Bought At</label>
              <input class="form-control" name="bought_at" type="date" value="{{ now()->toDateString() }}">
            </div>
            <div class="col-12">
              <label class="h-label">Notes</label>
              <input class="form-control" name="notes" placeholder="Optional">
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus me-2"></i>Save Gold Position</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="h-modal-overlay" id="portfolio-master-create-modal">
      <div class="h-modal" style="max-width:900px;">
        <div class="h-modal-head">
          <div class="h-modal-title">Add IPO Feed Row</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="{{ route('portfolio.ipos.store') }}" class="row g-3" data-spa>
            @csrf
            <div class="col-lg-3">
              <label class="h-label">Company</label>
              <input class="form-control" name="company_name" required>
            </div>
            <div class="col-lg-2">
              <label class="h-label">Symbol</label>
              <input class="form-control" name="symbol">
            </div>
            <div class="col-lg-2">
              <label class="h-label">Status</label>
              <select class="form-select" name="status" data-h-select required>
                <option value="open">Open</option>
                <option value="upcoming">Upcoming</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <div class="col-lg-2">
              <label class="h-label">Price</label>
              <input class="form-control" name="price_per_unit" type="number" step="0.01" value="100" required>
            </div>
            <div class="col-lg-1">
              <label class="h-label">Min</label>
              <input class="form-control" name="min_units" type="number" value="10" required>
            </div>
            <div class="col-lg-2">
              <label class="h-label">Max</label>
              <input class="form-control" name="max_units" type="number" value="10">
            </div>
            <div class="col-lg-3">
              <label class="h-label">Open Date</label>
              <input class="form-control" name="open_date" type="date">
            </div>
            <div class="col-lg-3">
              <label class="h-label">Close Date</label>
              <input class="form-control" name="close_date" type="date">
            </div>
            <div class="col-lg-3">
              <label class="h-label">Listing Date</label>
              <input class="form-control" name="listing_date" type="date">
            </div>
            <div class="col-lg-3">
              <label class="h-label">Notes</label>
              <input class="form-control" name="notes" placeholder="Optional">
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Save IPO Feed</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endcan

  @can('manage portfolio')
    <div class="h-modal-overlay" id="portfolio-ipo-edit-modal">
      <div class="h-modal" style="max-width:860px;">
        <div class="h-modal-head">
          <div class="h-modal-title" id="h-ipo-position-form-title">Edit IPO Position</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="#" id="h-ipo-position-form" data-spa data-update-template="{{ url('/portfolio/positions/__ID__') }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
              <div class="col-md-3">
                <label class="h-label" style="display:block;">Status</label>
                <select class="form-select" name="status" id="h-ipo-status" data-h-select required>
                  <option value="applied">Applied</option>
                  <option value="allotted">Allotted</option>
                  <option value="sold">Sold</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="h-label" style="display:block;">Applied Units</label>
                <input class="form-control" type="number" name="units_applied" id="h-ipo-applied" min="0" required>
              </div>
              <div class="col-md-3">
                <label class="h-label" style="display:block;">Allotted Units</label>
                <input class="form-control" type="number" name="units_allotted" id="h-ipo-allotted" min="0">
              </div>
              <div class="col-md-3">
                <label class="h-label" style="display:block;">Invested Amount</label>
                <input class="form-control" type="number" name="invested_amount" id="h-ipo-invested" step="0.01" min="0" required>
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Current Price</label>
                <input class="form-control" type="number" name="current_price" id="h-ipo-current-price" step="0.01" min="0">
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Applied Date</label>
                <input class="form-control" type="date" name="applied_at" id="h-ipo-applied-at">
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Sold Date</label>
                <input class="form-control" type="date" name="sold_at" id="h-ipo-sold-at">
              </div>
              <div class="col-12">
                <label class="h-label" style="display:block;">Notes</label>
                <textarea class="form-control" name="notes" id="h-ipo-notes" rows="4"></textarea>
              </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
              <button type="submit" class="btn btn-primary" data-busy-text="Saving...">
                <i class="fa-solid fa-floppy-disk me-2"></i>
                Update IPO Position
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="h-modal-overlay" id="portfolio-gold-edit-modal">
      <div class="h-modal" style="max-width:760px;">
        <div class="h-modal-head">
          <div class="h-modal-title" id="h-gold-position-form-title">Edit Gold Position</div>
          <button class="h-modal-close">×</button>
        </div>
        <div class="h-modal-body">
          <form method="POST" action="#" id="h-gold-position-form" data-spa data-update-template="{{ url('/portfolio/gold/__ID__') }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Label</label>
                <input class="form-control" name="label" id="h-gold-label">
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Source</label>
                <input class="form-control" name="source" id="h-gold-source">
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Grams</label>
                <input class="form-control" type="number" name="grams" id="h-gold-grams" step="0.001" min="0.001" required>
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Buy / gram</label>
                <input class="form-control" type="number" name="buy_price_per_gram" id="h-gold-buy" step="0.01" min="0.01" required>
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Current / gram</label>
                <input class="form-control" type="number" name="current_price_per_gram" id="h-gold-current" step="0.01" min="0.01">
              </div>
              <div class="col-md-4">
                <label class="h-label" style="display:block;">Bought At</label>
                <input class="form-control" type="date" name="bought_at" id="h-gold-bought-at">
              </div>
              <div class="col-12">
                <label class="h-label" style="display:block;">Notes</label>
                <textarea class="form-control" name="notes" id="h-gold-notes" rows="4"></textarea>
              </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
              <button type="submit" class="btn btn-primary" data-busy-text="Saving...">
                <i class="fa-solid fa-floppy-disk me-2"></i>
                Update Gold Position
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endcan
@endsection

@section('scripts')
  @can('manage portfolio')
    <script>
      (function () {
        const decodePayload = (value) => {
          try {
            return JSON.parse(window.atob(String(value || '')));
          } catch (error) {
            return null;
          }
        };

        const syncSelects = (root) => {
          if (window.HSelect && typeof window.HSelect.init === 'function') {
            window.HSelect.init(root || document);
          }
        };

        const ipoForm = document.getElementById('h-ipo-position-form');
        const goldForm = document.getElementById('h-gold-position-form');
        const tabContainer = document.getElementById('portfolio-tabs');

        document.addEventListener('click', (event) => {
          const ipoTrigger = event.target.closest('[data-ipo-position-edit]');
          if (ipoTrigger && ipoForm) {
            const payload = decodePayload(ipoTrigger.getAttribute('data-ipo-position-edit'));
            if (payload && payload.id) {
              ipoForm.action = String(ipoForm.dataset.updateTemplate || '').replace('__ID__', String(payload.id));
              document.getElementById('h-ipo-position-form-title').textContent = 'Edit IPO Position #' + payload.id;
              document.getElementById('h-ipo-status').value = payload.status || 'applied';
              document.getElementById('h-ipo-applied').value = payload.units_applied ?? 0;
              document.getElementById('h-ipo-allotted').value = payload.units_allotted ?? 0;
              document.getElementById('h-ipo-invested').value = payload.invested_amount ?? 0;
              document.getElementById('h-ipo-current-price').value = payload.current_price ?? '';
              document.getElementById('h-ipo-applied-at').value = payload.applied_at || '';
              document.getElementById('h-ipo-sold-at').value = payload.sold_at || '';
              document.getElementById('h-ipo-notes').value = payload.notes || '';
              document.getElementById('h-ipo-status').dispatchEvent(new Event('change', { bubbles: true }));
              syncSelects(ipoForm);
              if (window.HModal) window.HModal.open('portfolio-ipo-edit-modal');
            }
            return;
          }

          const goldTrigger = event.target.closest('[data-gold-position-edit]');
          if (goldTrigger && goldForm) {
            const payload = decodePayload(goldTrigger.getAttribute('data-gold-position-edit'));
            if (payload && payload.id) {
              goldForm.action = String(goldForm.dataset.updateTemplate || '').replace('__ID__', String(payload.id));
              document.getElementById('h-gold-position-form-title').textContent = 'Edit Gold Position #' + payload.id;
              document.getElementById('h-gold-label').value = payload.label || '';
              document.getElementById('h-gold-source').value = payload.source || '';
              document.getElementById('h-gold-grams').value = payload.grams ?? '';
              document.getElementById('h-gold-buy').value = payload.buy_price_per_gram ?? '';
              document.getElementById('h-gold-current').value = payload.current_price_per_gram ?? '';
              document.getElementById('h-gold-bought-at').value = payload.bought_at || '';
              document.getElementById('h-gold-notes').value = payload.notes || '';
              syncSelects(goldForm);
              if (window.HModal) window.HModal.open('portfolio-gold-edit-modal');
            }
          }
        });

        document.addEventListener('h:tabs:changed', function (event) {
          if (!event.detail || event.detail.container !== tabContainer) return;
          const tabId = String(event.detail.tabId || '').trim();
          if (!tabId) return;
          const url = new URL(window.location.href);
          url.searchParams.set('tab', tabId);
          window.history.replaceState({}, '', url.toString());
        });
      })();
    </script>
  @endcan
@endsection
