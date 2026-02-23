<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaManagerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UiOptionsController;

// ── Public routes ─────────────────────────────────────────
Route::get('/',       fn() => redirect('/login'));
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/register',  [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::get('/terms', fn() => view('auth.terms'))->name('terms');
Route::get('/auth/facebook/redirect', [AuthController::class, 'facebookRedirect'])->name('facebook.redirect');
Route::get('/auth/facebook/callback', [AuthController::class, 'facebookCallback'])->name('facebook.callback');
Route::get('/2fa', [AuthController::class, 'showTwoFactor'])->name('2fa.form');
Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor'])->name('2fa.verify');
Route::post('/2fa/resend', [AuthController::class, 'resendTwoFactor'])->name('2fa.resend');
Route::post('/ui/locale', [UiOptionsController::class, 'setLocale'])->name('ui.locale.set');
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('telegram.webhook');

// ── Protected routes ──────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:view dashboard')
        ->name('dashboard');

    Route::get('/accounts', [AccountController::class, 'index'])
        ->middleware('permission:view accounts')
        ->name('accounts.index');

    Route::post('/accounts', [AccountController::class, 'store'])
        ->middleware('permission:manage accounts')
        ->name('accounts.store');

    Route::put('/accounts/{account}', [AccountController::class, 'update'])
        ->middleware('permission:manage accounts')
        ->name('accounts.update');

    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])
        ->middleware('permission:manage accounts')
        ->name('accounts.delete');

    Route::get('/transactions', [TransactionController::class, 'index'])
        ->middleware('permission:view transactions')
        ->name('transactions.index');

    Route::post('/transactions', [TransactionController::class, 'store'])
        ->middleware('permission:manage transactions')
        ->name('transactions.store');

    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])
        ->middleware('permission:manage transactions')
        ->name('transactions.delete');

    Route::get('/portfolio', [PortfolioController::class, 'index'])
        ->middleware('permission:view portfolio')
        ->name('portfolio.index');

    Route::post('/portfolio/ipos', [PortfolioController::class, 'storeIpo'])
        ->middleware('permission:manage portfolio')
        ->name('portfolio.ipos.store');

    Route::post('/portfolio/sync-market', [PortfolioController::class, 'syncMarket'])
        ->middleware('permission:manage portfolio')
        ->name('portfolio.sync-market');

    Route::post('/portfolio/positions', [PortfolioController::class, 'storeIpoPosition'])
        ->middleware('permission:manage portfolio')
        ->name('portfolio.positions.store');

    Route::put('/portfolio/positions/{position}', [PortfolioController::class, 'updateIpoPosition'])
        ->middleware('permission:manage portfolio')
        ->name('portfolio.positions.update');

    Route::delete('/portfolio/positions/{position}', [PortfolioController::class, 'deleteIpoPosition'])
        ->middleware('permission:manage portfolio')
        ->name('portfolio.positions.delete');

    Route::post('/portfolio/gold', [PortfolioController::class, 'storeGold'])
        ->middleware('permission:manage portfolio')
        ->name('portfolio.gold.store');

    Route::put('/portfolio/gold/{gold}', [PortfolioController::class, 'updateGold'])
        ->middleware('permission:manage portfolio')
        ->name('portfolio.gold.update');

    Route::delete('/portfolio/gold/{gold}', [PortfolioController::class, 'deleteGold'])
        ->middleware('permission:manage portfolio')
        ->name('portfolio.gold.delete');

    Route::get('/suggestions', [SuggestionController::class, 'index'])
        ->middleware('permission:view suggestions')
        ->name('suggestions.index');

    Route::post('/suggestions/refresh', [SuggestionController::class, 'refresh'])
        ->middleware('permission:view suggestions')
        ->name('suggestions.refresh');

    Route::post('/suggestions/{suggestion}/read', [SuggestionController::class, 'markRead'])
        ->middleware('permission:view suggestions')
        ->name('suggestions.read');

    Route::delete('/suggestions/read', [SuggestionController::class, 'clearRead'])
        ->middleware('permission:view suggestions')
        ->name('suggestions.clear-read');

    Route::get('/docs', fn() => view('docs.starter-kit'))
        ->middleware('permission:view docs')
        ->name('docs.index');

    Route::get('/docs/starter-kit', fn() => redirect()->route('docs.index'))
        ->middleware('permission:view docs')
        ->name('docs.starter');

    Route::get('/settings', [SettingsController::class, 'index'])
        ->middleware('permission:view settings')
        ->name('settings.index');

    Route::get('/settings/users', [SettingsController::class, 'users'])
        ->middleware('permission:view users')
        ->name('settings.users.index');

    Route::get('/settings/media', [SettingsController::class, 'media'])
        ->middleware('permission:view settings')
        ->name('settings.media.index');

    Route::match(['GET', 'POST'], '/settings/media/connector', [MediaManagerController::class, 'connector'])
        ->middleware('permission:view settings')
        ->name('settings.media.connector');

    Route::get('/settings/rbac', [SettingsController::class, 'rbac'])
        ->middleware('permission:manage settings')
        ->name('settings.rbac');

    Route::get('/settings/rbac/create', [SettingsController::class, 'rbacCreate'])
        ->middleware('permission:manage settings')
        ->name('settings.rbac.create');

    Route::get('/settings/rbac/{role}/edit', [SettingsController::class, 'rbacEdit'])
        ->middleware('permission:manage settings')
        ->name('settings.rbac.edit');

    Route::get('/settings/search', [SettingsController::class, 'searchIndex'])
        ->middleware('permission:manage settings')
        ->name('settings.search.index');

    Route::get('/settings/search/create', [SettingsController::class, 'searchCreate'])
        ->middleware('permission:manage settings')
        ->name('settings.search.create');

    Route::get('/settings/search/{searchKey}/edit', [SettingsController::class, 'searchEdit'])
        ->where('searchKey', '[A-Za-z0-9_-]+')
        ->middleware('permission:manage settings')
        ->name('settings.search.edit');

    Route::post('/settings', [SettingsController::class, 'update'])
        ->middleware('permission:manage settings')
        ->name('settings.update');

    Route::post('/settings/search', [SettingsController::class, 'storeSearch'])
        ->middleware('permission:manage settings')
        ->name('settings.search.store');

    Route::put('/settings/search/{searchKey}', [SettingsController::class, 'updateSearch'])
        ->where('searchKey', '[A-Za-z0-9_-]+')
        ->middleware('permission:manage settings')
        ->name('settings.search.update');

    Route::delete('/settings/search/{searchKey}', [SettingsController::class, 'deleteSearch'])
        ->where('searchKey', '[A-Za-z0-9_-]+')
        ->middleware('permission:manage settings')
        ->name('settings.search.delete');

    Route::post('/settings/search/debounce', [SettingsController::class, 'updateSearchDebounce'])
        ->middleware('permission:manage settings')
        ->name('settings.search.debounce');

    Route::post('/settings/search/registry', [SettingsController::class, 'updateSearchRegistry'])
        ->middleware('permission:manage settings')
        ->name('settings.search.registry');

    Route::post('/settings/branding', [SettingsController::class, 'updateBranding'])
        ->middleware('permission:manage settings')
        ->name('settings.branding');

    Route::delete('/settings/branding/media', [SettingsController::class, 'deleteBrandAsset'])
        ->middleware('permission:manage settings')
        ->name('settings.branding.media.delete');

    Route::post('/settings/security', [SettingsController::class, 'updateMySecurity'])
        ->middleware('permission:view settings')
        ->name('settings.security');

    Route::post('/profile', [SettingsController::class, 'updateProfile'])
        ->name('profile.update');

    Route::post('/settings/users/{user}/access', [SettingsController::class, 'updateUserAccess'])
        ->middleware('permission:manage users')
        ->name('settings.users.access');

    Route::get('/settings/users/{user}/access', function ($user) {
        return redirect()->route('settings.users.index', ['access_user' => (string) $user]);
    })
        ->middleware('permission:view users');

    Route::get('/settings/users/export', [SettingsController::class, 'exportUsers'])
        ->middleware('permission:manage users')
        ->name('settings.users.export');

    Route::post('/settings/users/import', [SettingsController::class, 'importUsers'])
        ->middleware('permission:manage users')
        ->name('settings.users.import');

    Route::get('/settings/activity/export', [SettingsController::class, 'exportActivity'])
        ->middleware('permission:view settings')
        ->name('settings.activity.export');

    Route::post('/settings/users', [SettingsController::class, 'storeUser'])
        ->middleware('permission:manage users')
        ->name('settings.users.store');

    Route::put('/settings/users/{user}', [SettingsController::class, 'updateUser'])
        ->middleware('permission:manage users')
        ->name('settings.users.update');

    Route::put('/settings/users/{user}/profile', [SettingsController::class, 'updateUserProfile'])
        ->middleware('permission:manage users')
        ->name('settings.users.profile');

    Route::post('/settings/users/{user}/notifications', [SettingsController::class, 'updateUserNotifications'])
        ->middleware('permission:manage users')
        ->name('settings.users.notifications');

    Route::delete('/settings/users/{user}', [SettingsController::class, 'deleteUser'])
        ->middleware('permission:manage users')
        ->name('settings.users.delete');

    Route::post('/settings/roles/matrix', [SettingsController::class, 'updateRoleMatrix'])
        ->middleware('permission:manage settings')
        ->name('settings.roles.matrix');

    Route::post('/settings/roles', [SettingsController::class, 'storeRole'])
        ->middleware('permission:manage settings')
        ->name('settings.roles.store');

    Route::put('/settings/roles/{role}', [SettingsController::class, 'updateRole'])
        ->middleware('permission:manage settings')
        ->name('settings.roles.update');

    Route::delete('/settings/roles/{role}', [SettingsController::class, 'deleteRole'])
        ->middleware('permission:manage settings')
        ->name('settings.roles.delete');

    Route::get('/ui/options/leads', [UiOptionsController::class, 'leads'])
        ->middleware('permission:view users')
        ->name('ui.options.leads');

    Route::get('/ui/file-manager', [UiOptionsController::class, 'fileManagerList'])
        ->middleware('permission:view settings')
        ->name('ui.filemanager.index');

    Route::post('/ui/file-manager/upload', [UiOptionsController::class, 'fileManagerUpload'])
        ->middleware('permission:manage settings')
        ->name('ui.filemanager.upload');

    Route::post('/ui/file-manager/delete', [UiOptionsController::class, 'fileManagerDelete'])
        ->middleware('permission:manage settings')
        ->name('ui.filemanager.delete');

    Route::post('/ui/file-manager/folder', [UiOptionsController::class, 'fileManagerCreateFolder'])
        ->middleware('permission:manage settings')
        ->name('ui.filemanager.folder');

    Route::post('/ui/file-manager/resize', [UiOptionsController::class, 'fileManagerResize'])
        ->middleware('permission:manage settings')
        ->name('ui.filemanager.resize');

    Route::get('/ui/file-manager/export-csv', [UiOptionsController::class, 'fileManagerExportCsv'])
        ->middleware('permission:view settings')
        ->name('ui.filemanager.export');

    Route::get('/ui/search/global', [UiOptionsController::class, 'globalSearch'])
        ->name('ui.search.global');

    Route::get('/ui/health/report', [UiOptionsController::class, 'healthReport'])
        ->middleware('permission:view settings')
        ->name('ui.health.report');

    Route::get('/ui/hot-reload/signature', [UiOptionsController::class, 'hotReloadSignature'])
        ->name('ui.hot_reload.signature');
    Route::get('/ui/hot-reload/stream', [UiOptionsController::class, 'hotReloadStream'])
        ->name('ui.hot_reload.stream');

    Route::get('/ui/datatables/users', [UiOptionsController::class, 'usersTable'])
        ->middleware('permission:view users')
        ->name('ui.datatables.users');

    Route::get('/ui/datatables/activities', [UiOptionsController::class, 'activityTable'])
        ->middleware('permission:view settings')
        ->name('ui.datatables.activities');

    Route::get('/ui/datatables/roles', [UiOptionsController::class, 'rolesTable'])
        ->middleware('permission:manage settings')
        ->name('ui.datatables.roles');

    Route::get('/notifications/feed', [NotificationController::class, 'feed'])
        ->middleware('permission:view notifications')
        ->name('notifications.feed');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
        ->middleware('permission:view notifications')
        ->name('notifications.read');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->middleware('permission:view notifications')
        ->name('notifications.read_all');

    Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast'])
        ->middleware('permission:manage notifications')
        ->name('notifications.broadcast');

    Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');
});
