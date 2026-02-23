# Haarray Starter Kit (v0.1)

This starter kit is a Laravel-first admin scaffold with progressive SPA behavior and reusable frontend modules.

## 1) What is included

- Server-first Laravel routes/controllers + Blade views
- Progressive SPA navigation (`HSPA`) with browser URL updates
- Bootstrap 5 + Font Awesome 6 integration
- Custom icon system (`<x-icon>` + SVG sprite)
- Custom select and remote Select2 mode
- Rich text editor with modal-based tools
- Yajra DataTables integration for server-side tables
- Dedicated Settings pages: App & Branding, Users, Media Library, Roles & Access
- Notification tray (in-app) with mark-read and mark-all
- Global search modal and debug tray

## 2) Core routes

- `/dashboard`
- `/docs`
- `/settings`
- `/settings/users`
- `/settings/media`
- `/settings/rbac`
- `/settings/rbac/create`
- `/settings/rbac/{role}/edit`

## 3) Media manager

Media manager is available in:

- Topbar global modal
- Settings sidebar page: `/settings/media`
- Editor image insert modal

### API endpoints

- `GET /ui/file-manager` list files and folders
- `POST /ui/file-manager/upload` upload file
- `POST /ui/file-manager/delete` delete file
- `POST /ui/file-manager/folder` create folder
- `POST /ui/file-manager/resize` resize image (local storage mode)
- `GET /ui/file-manager/export-csv` export media index as CSV

### Storage behavior

- If `FILESYSTEM_DISK=s3` and S3 bucket is configured, media APIs run on S3.
- Otherwise, storage falls back to local `public/uploads`.

## 4) File picker pattern (URL based)

Use URL fields with media picker buttons for branding/assets:

```blade
<div class="input-group">
  <input type="text" id="ui-logo-url" name="ui_logo_url" class="form-control">
  <button type="button" class="btn btn-outline-secondary" data-media-manager-open data-media-target="ui-logo-url">
    <i class="fa-solid fa-photo-film me-1"></i>Inject
  </button>
</div>
```

This keeps forms SPA-safe and avoids browser-native file input UX for asset URLs.

## 5) Error pages

Custom error views are included for:

- `403`, `404`, `419`, `429`, `500`, `503`

Files:

- `resources/views/errors/layout.blade.php`
- `resources/views/errors/403.blade.php`
- `resources/views/errors/404.blade.php`
- `resources/views/errors/419.blade.php`
- `resources/views/errors/429.blade.php`
- `resources/views/errors/500.blade.php`
- `resources/views/errors/503.blade.php`

## 6) Datatables

Use `data-h-datatable` and backend JSON endpoints.

```blade
<table
  data-h-datatable
  data-endpoint="{{ route('ui.datatables.users') }}"
  data-page-length="10"
  data-length-menu="10,20,50,100"
  data-empty-text="Empty">
  <thead>
    <tr>
      <th data-col="id">ID</th>
      <th data-col="name">Name</th>
      <th data-col="email">Email</th>
      <th data-col="actions" data-orderable="false" data-searchable="false">Actions</th>
    </tr>
  </thead>
</table>
```

## 7) Deployment modes

### Artisan

```bash
php artisan serve
```

### PHP built-in server

```bash
php -S 127.0.0.1:8000 server.php
```

### XAMPP / shared hosting folder mode

- Root `.htaccess` rewrites to `/public`
- Root `index.php` forwards to `/public/index.php`
- `server.php` supports base paths like `/harray-core` and `/haaray-core`

## 8) Tutorials

- `docs/tutorials/CRUD_WORKFLOW.md`
- `docs/tutorials/NOTIFIER_HELPER.md`
- `docs/tutorials/MEDIA_MANAGER.md`
- `docs/tutorials/INTEGRATIONS_FACEBOOK_TELEGRAM.md`
- `docs/tutorials/DEPLOYMENT_MODES.md`

## 9) Useful commands

```bash
php artisan migrate
php artisan db:seed
php artisan haarray:permissions:sync --seed-admins
php artisan haarray:health:check
```
