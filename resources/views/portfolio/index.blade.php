@extends('layouts.app')

@section('title', 'Portfolio')
@section('page_title', 'Portfolio')

@section('content')
  <div class="h-page-header">
    <div>
      <div class="h-page-title">Portfolio Manager</div>
      <div class="h-page-sub">Track IPO applications, allotment gain, and gold holdings.</div>
    </div>
    @can('manage portfolio')
      <form method="POST" action="{{ route('portfolio.sync-market') }}" data-spa>
        @csrf
        <button class="h-btn ghost" type="submit">
          <i class="fa-solid fa-arrows-rotate"></i> Sync Live Market
        </button>
      </form>
    @endcan
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

  <div class="h-grid-main" style="grid-template-columns: 1fr 1fr;">
    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Add IPO Position</div>
      </div>
      <div class="h-card-body">
        <form method="POST" action="{{ route('portfolio.positions.store') }}" class="row g-3" data-spa>
          @csrf
          <div class="col-12">
            <label class="h-label">IPO</label>
            <select class="h-input" name="ipo_id" required>
              <option value="">Select IPO</option>
              @foreach($ipos as $ipo)
                <option value="{{ $ipo->id }}">{{ $ipo->company_name }} ({{ strtoupper($ipo->status) }})</option>
              @endforeach
            </select>
          </div>
          <div class="col-4">
            <label class="h-label">Applied</label>
            <input class="h-input" name="units_applied" type="number" min="0" value="0" required>
          </div>
          <div class="col-4">
            <label class="h-label">Allotted</label>
            <input class="h-input" name="units_allotted" type="number" min="0" value="0">
          </div>
          <div class="col-4">
            <label class="h-label">Status</label>
            <select class="h-input" name="status" required>
              <option value="applied">Applied</option>
              <option value="allotted">Allotted</option>
              <option value="sold">Sold</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="col-6">
            <label class="h-label">Invested Amount</label>
            <input class="h-input" name="invested_amount" type="number" step="0.01" value="0" required>
          </div>
          <div class="col-6">
            <label class="h-label">Current Price</label>
            <input class="h-input" name="current_price" type="number" step="0.01">
          </div>
          <div class="col-6">
            <label class="h-label">Applied Date</label>
            <input class="h-input" name="applied_at" type="date" value="{{ now()->toDateString() }}">
          </div>
          <div class="col-6">
            <label class="h-label">Sold Date</label>
            <input class="h-input" name="sold_at" type="date">
          </div>
          <div class="col-12">
            <label class="h-label">Notes</label>
            <input class="h-input" name="notes">
          </div>
          <div class="col-12">
            <button class="h-btn primary" type="submit"><i class="fa-solid fa-plus"></i> Save IPO Position</button>
          </div>
        </form>
      </div>
    </div>

    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Add Gold Position</div>
      </div>
      <div class="h-card-body">
        <form method="POST" action="{{ route('portfolio.gold.store') }}" class="row g-3" data-spa>
          @csrf
          <div class="col-6">
            <label class="h-label">Label</label>
            <input class="h-input" name="label" placeholder="24k bullion">
          </div>
          <div class="col-6">
            <label class="h-label">Source</label>
            <input class="h-input" name="source" placeholder="NMB Jewellers">
          </div>
          <div class="col-4">
            <label class="h-label">Grams</label>
            <input class="h-input" name="grams" type="number" step="0.001" min="0.001" required>
          </div>
          <div class="col-4">
            <label class="h-label">Buy / gram</label>
            <input class="h-input" name="buy_price_per_gram" type="number" step="0.01" min="0.01" required>
          </div>
          <div class="col-4">
            <label class="h-label">Current / gram</label>
            <input class="h-input" name="current_price_per_gram" type="number" step="0.01" min="0.01">
          </div>
          <div class="col-12">
            <label class="h-label">Bought At</label>
            <input class="h-input" name="bought_at" type="date" value="{{ now()->toDateString() }}">
          </div>
          <div class="col-12">
            <label class="h-label">Notes</label>
            <input class="h-input" name="notes">
          </div>
          <div class="col-12">
            <button class="h-btn primary" type="submit"><i class="fa-solid fa-plus"></i> Save Gold Position</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @can('manage portfolio')
    <div class="h-card" style="margin-top:16px;">
      <div class="h-card-head">
        <div class="h-card-title">IPO Master (NEPSE)</div>
      </div>
      <div class="h-card-body">
        <form method="POST" action="{{ route('portfolio.ipos.store') }}" class="row g-3" data-spa>
          @csrf
          <div class="col-lg-3"><label class="h-label">Company</label><input class="h-input" name="company_name" required></div>
          <div class="col-lg-2"><label class="h-label">Symbol</label><input class="h-input" name="symbol"></div>
          <div class="col-lg-2"><label class="h-label">Status</label><select class="h-input" name="status" required><option value="open">Open</option><option value="upcoming">Upcoming</option><option value="closed">Closed</option></select></div>
          <div class="col-lg-1"><label class="h-label">Price</label><input class="h-input" name="price_per_unit" type="number" step="0.01" value="100" required></div>
          <div class="col-lg-1"><label class="h-label">Min</label><input class="h-input" name="min_units" type="number" value="10" required></div>
          <div class="col-lg-1"><label class="h-label">Open</label><input class="h-input" name="open_date" type="date"></div>
          <div class="col-lg-1"><label class="h-label">Close</label><input class="h-input" name="close_date" type="date"></div>
          <div class="col-lg-1 d-flex align-items-end"><button class="h-btn ghost" type="submit"><i class="fa-solid fa-floppy-disk"></i></button></div>
        </form>
      </div>
    </div>
  @endcan

  <div class="h-grid-main" style="grid-template-columns: 1fr 1fr;margin-top:16px;">
    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">IPO Positions</div>
      </div>
      <div class="h-card-body" style="display:grid;gap:8px;">
        @forelse($ipoPositions as $position)
          <form id="ipo-position-update-{{ $position->id }}" method="POST" action="{{ route('portfolio.positions.update', $position) }}" class="h-card" style="background:rgba(15,23,42,.35);" data-spa>
            @csrf
            @method('PUT')
            <div class="h-card-body">
              <div class="row g-2 align-items-end">
                <div class="col-lg-2"><label class="h-label">IPO</label><input class="h-input" value="{{ optional($position->ipo)->company_name }}" disabled></div>
                <div class="col-lg-2"><label class="h-label">Status</label><select class="h-input" name="status"><option value="applied" @selected($position->status==='applied')>Applied</option><option value="allotted" @selected($position->status==='allotted')>Allotted</option><option value="sold" @selected($position->status==='sold')>Sold</option><option value="cancelled" @selected($position->status==='cancelled')>Cancelled</option></select></div>
                <div class="col-lg-1"><label class="h-label">Applied</label><input class="h-input" name="units_applied" type="number" value="{{ $position->units_applied }}"></div>
                <div class="col-lg-1"><label class="h-label">Allotted</label><input class="h-input" name="units_allotted" type="number" value="{{ $position->units_allotted }}"></div>
                <div class="col-lg-2"><label class="h-label">Invested</label><input class="h-input" name="invested_amount" type="number" step="0.01" value="{{ $position->invested_amount }}"></div>
                <div class="col-lg-1"><label class="h-label">Live LTP</label><input class="h-input" value="{{ number_format((float) (optional($position->ipo)->market_price ?? 0), 2) }}" disabled></div>
                <div class="col-lg-2"><label class="h-label">Current Price</label><input class="h-input" name="current_price" type="number" step="0.01" value="{{ $position->current_price }}"></div>
                <div class="col-lg-1 d-flex gap-2">
                  <button class="h-btn ghost" type="submit"><i class="fa-solid fa-floppy-disk"></i></button>
                  <button class="h-btn danger" type="submit" form="ipo-position-delete-{{ $position->id }}" data-confirm="true" data-confirm-title="Delete position?" data-confirm-text="This cannot be undone."><i class="fa-solid fa-trash"></i></button>
                </div>
              </div>
            </div>
          </form>
          <form id="ipo-position-delete-{{ $position->id }}" method="POST" action="{{ route('portfolio.positions.delete', $position) }}" data-spa style="display:none;">
            @csrf
            @method('DELETE')
          </form>
        @empty
          <div class="h-alert info">No IPO positions yet.</div>
        @endforelse
      </div>
    </div>

    <div class="h-card">
      <div class="h-card-head">
        <div class="h-card-title">Gold Positions</div>
      </div>
      <div class="h-card-body" style="display:grid;gap:8px;">
        @forelse($goldPositions as $gold)
          <form id="gold-update-{{ $gold->id }}" method="POST" action="{{ route('portfolio.gold.update', $gold) }}" class="h-card" style="background:rgba(15,23,42,.35);" data-spa>
            @csrf
            @method('PUT')
            <div class="h-card-body">
              <div class="row g-2 align-items-end">
                <div class="col-lg-2"><label class="h-label">Label</label><input class="h-input" name="label" value="{{ $gold->label }}"></div>
                <div class="col-lg-2"><label class="h-label">Source</label><input class="h-input" name="source" value="{{ $gold->source }}"></div>
                <div class="col-lg-1"><label class="h-label">Grams</label><input class="h-input" name="grams" type="number" step="0.001" value="{{ $gold->grams }}"></div>
                <div class="col-lg-2"><label class="h-label">Buy/gram</label><input class="h-input" name="buy_price_per_gram" type="number" step="0.01" value="{{ $gold->buy_price_per_gram }}"></div>
                <div class="col-lg-2"><label class="h-label">Current/gram</label><input class="h-input" name="current_price_per_gram" type="number" step="0.01" value="{{ $gold->current_price_per_gram }}"></div>
                <div class="col-lg-2"><label class="h-label">Bought</label><input class="h-input" name="bought_at" type="date" value="{{ optional($gold->bought_at)->format('Y-m-d') }}"></div>
                <div class="col-lg-1 d-flex gap-2">
                  <button class="h-btn ghost" type="submit"><i class="fa-solid fa-floppy-disk"></i></button>
                  <button class="h-btn danger" type="submit" form="gold-delete-{{ $gold->id }}" data-confirm="true" data-confirm-title="Delete gold position?" data-confirm-text="This cannot be undone."><i class="fa-solid fa-trash"></i></button>
                </div>
              </div>
            </div>
          </form>
          <form id="gold-delete-{{ $gold->id }}" method="POST" action="{{ route('portfolio.gold.delete', $gold) }}" data-spa style="display:none;">
            @csrf
            @method('DELETE')
          </form>
        @empty
          <div class="h-alert info">No gold positions yet.</div>
        @endforelse
      </div>
    </div>
  </div>
@endsection
