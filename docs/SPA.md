# Haarray SPA Engine (`HSPA`)

`HSPA` is a progressive enhancement layer in `public/js/haarray.js`.

It intercepts links/forms marked with `data-spa`, fetches HTML via AJAX, and swaps only the `#h-spa-content` container when possible.

## Core behavior

1. Intercepts links with `a[data-spa]`
2. Intercepts forms with `form[data-spa]`
3. Fetches server response normally (no controller rewrite required)
4. Swaps `#h-spa-content` if both current and response pages contain the container
5. Falls back to full document replacement when partial swap is not possible

## Required layout contract

```blade
<div id="h-spa-content">
  @yield('content')
</div>
```

## Compatible server responses

- Blade views (`return view(...)`)
- Redirects (`return redirect(...)`)
- Validation redirects with session errors
- Optional JSON responses with `message` or `redirect`

## Lifecycle events

`HSPA` emits these document events:

- `hspa:beforeLoad`
- `hspa:afterSwap`
- `hspa:afterLoad`
- `hspa:error`

### Example

```js
document.addEventListener('hspa:beforeLoad', (event) => {
  console.log('Loading', event.detail.url);
});


document.addEventListener('hspa:afterSwap', (event) => {
  console.log('Container swapped', event.detail.container);
});
```

## API summary

```js
HSPA.navigate('/docs/starter-kit');
HSPA.load(location.pathname + location.search);
```

## Design goal

Keep Laravel server-rendered routing as the source of truth while adding app-like UX only where it helps.
