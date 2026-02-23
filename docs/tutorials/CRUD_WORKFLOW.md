# CRUD Workflow Tutorial

This starter kit is optimized for server-first CRUD with progressive SPA UX.

## 1) Routes

Add page + DataTable endpoint + CRUD actions:

```php
Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
Route::get('/ui/datatables/customers', [CustomerController::class, 'table'])->name('ui.datatables.customers');
Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
```

## 2) List Page

Use server-side DataTables:

```blade
<table data-h-datatable data-endpoint="{{ route('ui.datatables.customers') }}">
  <thead>
    <tr>
      <th data-col="id">ID</th>
      <th data-col="name">Name</th>
      <th data-col="email">Email</th>
      <th data-col="actions" class="h-col-actions" data-orderable="false" data-searchable="false">Actions</th>
    </tr>
  </thead>
</table>
```

## 3) Actions

- `Edit`: open modal or full page.
- `Delete`: always use `data-confirm="true"` so it waits for explicit confirmation.

```blade
<form method="POST" action="{{ route('customers.destroy', $customer) }}" data-spa data-confirm="true" data-confirm-title="Delete customer?" data-confirm-text="This action cannot be undone.">
  @csrf
  @method('DELETE')
  <button type="submit" class="btn btn-outline-danger btn-sm h-action-icon"><i class="fa-solid fa-trash"></i></button>
</form>
```

## 4) UX Rules

- Keep route/controller validation as source of truth.
- Use `data-spa` for fast partial transitions.
- Use `HToast` messages for result feedback.
- Keep forms reusable (create + edit), and move large forms to full pages.

