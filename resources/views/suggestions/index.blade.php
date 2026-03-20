@extends('layouts.app')

@section('title', 'Suggestions')
@section('page_title', 'Suggestions')

@section('content')
  <div class="h-page-header">
    <div>
      <div class="h-page-title">AI Suggestions</div>
      <div class="h-page-sub">Rule + PHP-ML insights from your own finance data.</div>
    </div>
    <div class="h-page-actions">
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

  <div class="h-ai-chat-card" style="margin-bottom:16px;">
    <div class="h-card-head">
      <div>
        <div class="h-card-title">Hari AI Assistant</div>
        <div class="h-card-meta">Chat with your live finance context (budget, IPO, gold, accounts)</div>
      </div>
      <span class="h-badge gold">BETA</span>
    </div>
    <div class="h-ai-chat-body">
      <div class="h-ai-chat-log" id="h-ai-chat-log">
        <div class="h-ai-msg bot">
          <span class="h-ai-msg-meta">Hari AI</span>
          Ask about spending trends, IPO readiness, gold exposure, or account health.
        </div>
      </div>
      <form id="h-ai-chat-form" class="h-ai-chat-form" data-endpoint="{{ route('suggestions.chat') }}">
        <input type="text" class="form-control" id="h-ai-chat-input" maxlength="500" placeholder="Example: Can I apply for open IPOs this week with my current balance?" required>
        <button type="submit" class="h-btn primary" id="h-ai-chat-send">
          <i class="ri-send-plane-2-line"></i>
          Send
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

@section('scripts')
  <script>
    (function () {
      const form = document.getElementById('h-ai-chat-form');
      const input = document.getElementById('h-ai-chat-input');
      const log = document.getElementById('h-ai-chat-log');
      const sendButton = document.getElementById('h-ai-chat-send');

      if (!form || !input || !log || !sendButton) {
        return;
      }

      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      const addMessage = (role, text) => {
        const wrap = document.createElement('div');
        wrap.className = 'h-ai-msg ' + role;

        const meta = document.createElement('span');
        meta.className = 'h-ai-msg-meta';
        meta.textContent = role === 'user' ? 'You' : 'Hari AI';

        const body = document.createElement('span');
        body.textContent = String(text || '');

        wrap.appendChild(meta);
        wrap.appendChild(body);
        log.appendChild(wrap);
        log.scrollTop = log.scrollHeight;
      };

      form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const message = String(input.value || '').trim();
        if (!message) return;

        addMessage('user', message);
        input.value = '';
        sendButton.disabled = true;

        try {
          const response = await fetch(String(form.dataset.endpoint || ''), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ message }),
          });

          if (!response.ok) {
            throw new Error('AI endpoint request failed');
          }

          const payload = await response.json();
          addMessage('bot', payload && payload.reply ? payload.reply : 'No response from assistant.');
        } catch (error) {
          addMessage('bot', 'I could not process that right now. Please try again.');
        } finally {
          sendButton.disabled = false;
          input.focus();
        }
      });
    })();
  </script>
@endsection
