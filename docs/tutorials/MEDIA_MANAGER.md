# Media Manager Tutorial

This tutorial shows how to use the starter's folder-based media manager for assets and content.

## 1) Open points

- Settings page: `/settings/media`
- Global topbar modal (`Media Library` icon)
- Rich editor image insert modal

All of them use the same backend API.

## 2) API endpoints

- `GET /ui/file-manager`
- `POST /ui/file-manager/upload`
- `POST /ui/file-manager/delete`
- `POST /ui/file-manager/folder`
- `POST /ui/file-manager/resize`
- `GET /ui/file-manager/export-csv`

## 3) Folder navigation

Use query params:

- `folder=branding`
- `folder=branding/icons`

`GET /ui/file-manager` returns:

- `items`: files in current folder
- `folders`: direct subfolders
- `current_folder`
- `storage` metadata (`local` or `s3`)

## 4) Inject URL into form fields

Use a text input plus media picker button:

```blade
<div class="input-group">
  <input type="text" id="ui-favicon-url" name="ui_favicon_url" class="form-control">
  <button type="button" class="btn btn-outline-secondary" data-media-manager-open data-media-target="ui-favicon-url">
    Inject
  </button>
</div>
```

Selecting `Use` in media modal writes URL into target input and triggers `input` + `change` events.

## 5) Local vs S3

- `FILESYSTEM_DISK=s3` + S3 bucket configured: media operations use S3.
- Any other case: fallback to local `public/uploads`.

## 6) Resize behavior

`POST /ui/file-manager/resize` is enabled only for local mode.

- Supports: `jpg`, `jpeg`, `png`, `gif`, `webp`
- Requires GD extension
- Can create a new resized copy or replace original
