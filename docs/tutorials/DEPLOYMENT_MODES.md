# Deployment Modes Tutorial

This starter supports multiple local/shared-hosting modes without breaking CSS/JS/assets.

## 1) Artisan mode

```bash
php artisan serve
```

Open: `http://127.0.0.1:8000`

## 2) Built-in PHP server mode

```bash
php -S 127.0.0.1:8000 server.php
```

`server.php` handles:

- static files from `/public`
- base-path aliases like `/harray-core`, `/haaray-core`, `/haaray`

## 3) XAMPP/shared hosting folder mode

Use app in project folder path:

- `http://localhost/harray-core`
- `http://localhost/haaray-core`

Requirements:

- root `.htaccess` active
- `mod_rewrite` enabled
- writable runtime folders

```bash
chmod -R 0777 storage bootstrap/cache public/uploads
chmod 0666 .env
```

## 4) If docs route is blocked by Apache alias

Keep root `.htaccess` with:

```apache
RewriteRule ^docs(?:/.*)?$ index.php [L]
```

This forces Laravel route handling for `/docs` instead of Apache static-directory resolution.
