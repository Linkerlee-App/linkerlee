<?php

use App\Http\Controllers\BulkEditingController;
use App\Http\Controllers\CsrfController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeleteUserDataController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\Internal\MailgunInboundController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Http\Middleware\VerifyMailgunWebhook;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return to_route('links.index');
    }

    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';

Route::middleware(['auth', 'verified'])->group(function () {

    Route::resource('links', LinkController::class);

    Route::get('/links-trashed', [LinkController::class, 'trashed'])->name('links.trashed');
    Route::patch('/links/{link}/archive', [LinkController::class, 'archive'])->name('links.archive');
    Route::patch('/links/{link}/restore', [LinkController::class, 'restore'])->name('links.restore')->withTrashed();
    Route::patch('/links/{link}/toggle-favorite', [LinkController::class, 'toggleFavorite'])->name('links.toggle-favorite');
    Route::patch('/links/{link}/rate', [LinkController::class, 'rate'])->name('links.rate');

    Route::resource('tags', TagController::class)->except([
        'create', 'edit',
    ]);

    Route::resource('groups', GroupController::class)->except([
        'create', 'edit',
    ]);

    Route::resource('publicLinks', PublicLinkController::class)->except([
        'create', 'edit', 'update',
    ]);

    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');

    Route::get('/all-tags', [TagController::class, 'getAllTags']);
    Route::get('/all-groups', [GroupController::class, 'getAllGroups']);

    Route::post('/bulk-edit-links', [BulkEditingController::class, 'editLinks']);

    Route::post('/search', [SearchController::class, 'search'])
        ->name('search');

    Route::post('/export', [ExportController::class, 'export'])
        ->name('export');

    Route::post('/import', [ImportController::class, 'import'])
        ->name('import');

    Route::post('/delete-user-data', [DeleteUserDataController::class, 'delete'])
        ->name('delete-user-data');

    Route::get('/csrf-token', [CsrfController::class, 'getCsrfToken'])
        ->name('csrf-token');
});

Route::get('/share/{shareId}', [PublicLinkController::class, 'show'])->name('share');

Route::post('/webhooks/mailgun/inbound', MailgunInboundController::class)
    ->middleware(VerifyMailgunWebhook::class)
    ->name('webhooks.mailgun.inbound');
