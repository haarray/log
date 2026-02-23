@extends('layouts.app')

@section('title', 'Suggestions')
@section('page_title', 'Suggestions')

@section('content')
  <div class="h-page-header">
    <div>
      <div class="h-page-title">AI Suggestions</div>
      <div class="h-page-sub">Rule + PHP-ML insights from your own finance data.</div>
    </div>
    <div class="d-flex gap-2">
      <form method="POST" action="{{ route('suggestions.refresh') }}" data-spa>
        @csrf
        <button class="h-btn primary" type="submit"><i class="fa-solid fa-rotate"></i> Refresh</button>
      </form>
      <form method="POST" action="{{ route('suggestions.clear-read') }}" data-spa>
        @csrf
        @method('DELETE')
        <button
          class="h-btn ghost"
          type="submit"
          data-confirm="true"
          data-confirm-title="Clear read suggestions?"
          data-confirm-text="Unread items will remain."
        >
          <i class="fa-solid fa-broom"></i>
          Clear Read
        </button>
      </form>
    </div>
  </div>

  <div class="h-grid-2">
    @forelse($suggestions as $suggestion)
      <div class="h-sug-card {{ $suggestion->priority === 'high' ? 'high' : '' }}">
        <div class="h-sug-icon" style="background:{{ $suggestion->priority === 'high' ? 'rgba(47,125,246,.10)' : 'rgba(45,212,191,.10)' }}">
          {{ $suggestion->icon ?: '💡' }}
        </div>
        <div style="flex:1;min-width:0;">
          <div class="h-sug-title">{{ $suggestion->title }}</div>
          <div class="h-sug-body">{{ $suggestion->message }}</div>
          <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
            <span class="h-badge {{ $suggestion->priority === 'high' ? 'gold' : 'muted' }}">{{ strtoupper($suggestion->priority) }}</span>
            @if(!$suggestion->is_read)
              <form method="POST" action="{{ route('suggestions.read', $suggestion) }}" data-spa>
                @csrf
                <button class="h-btn ghost" type="submit"><i class="fa-solid fa-check"></i> Mark Read</button>
              </form>
            @else
              <span class="h-badge green">READ</span>
            @endif
          </div>
        </div>
      </div>
    @empty
      <div class="h-alert info">No suggestions yet. Add transactions and click Refresh.</div>
    @endforelse
  </div>

  <div style="margin-top:16px;">
    {{ $suggestions->links() }}
  </div>
@endsection
