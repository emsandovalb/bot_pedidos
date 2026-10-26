<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChannelOnboardingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DailyOrderClosureController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Developer\WebhookSimulatorController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IncomingMessageController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderReviewController;
use App\Http\Controllers\OriaSettingsController;
use App\Http\Controllers\ProductAliasController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetupRequestController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('throttle:webhooks')->group(function () {
    Route::get('/webhooks/{provider}', [WebhookController::class, 'show'])->name('webhooks.show');
    Route::post('/webhooks/{provider}', [WebhookController::class, 'store'])->name('webhooks.store');
});

// Readiness: BD + métricas de ingesta. /up sigue siendo el liveness simple.
Route::get('/health', HealthController::class)->name('health');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::get('/channels/whatsapp/status', [ChannelController::class, 'status'])->name('channels.whatsapp.status');
    Route::post('/channels/{provider}/health-check', [ChannelController::class, 'healthCheck'])->name('channels.health-check');
    Route::get('/setup-requests', [SetupRequestController::class, 'index'])->name('setup-requests.index');
    Route::get('/setup-requests/{setupRequest}', [SetupRequestController::class, 'show'])->name('setup-requests.show');

    // Canales, credenciales del proveedor y notificaciones: solo owner/admin.
    Route::middleware('can:manage-settings')->group(function () {
        Route::get('/channels/whatsapp', [ChannelController::class, 'whatsapp'])->name('channels.whatsapp');
        Route::get('/channels/whatsapp/configuration', [ChannelController::class, 'whatsappConfiguration'])->name('channels.whatsapp.configuration');
        Route::post('/channels/whatsapp/configuration', [ChannelController::class, 'saveWhatsappConfiguration'])->name('channels.whatsapp.configuration.save');
        Route::post('/channels/whatsapp/verify-endpoint', [ChannelController::class, 'verifyEndpoint'])->name('channels.whatsapp.verify-endpoint');
        Route::post('/channels/whatsapp/onboarding', [ChannelOnboardingController::class, 'update'])->name('channels.whatsapp.onboarding.update');
        Route::post('/channels/{provider}/connect', [ChannelController::class, 'connect'])->name('channels.connect');
        Route::post('/channels/{provider}/disconnect', [ChannelController::class, 'disconnect'])->name('channels.disconnect');
        Route::post('/setup-requests', [SetupRequestController::class, 'store'])->name('setup-requests.store');
        Route::patch('/setup-requests/{setupRequest}', [SetupRequestController::class, 'update'])->name('setup-requests.update');

        Route::prefix('/settings/notifications')->name('settings.notifications.')->group(function () {
            Route::get('/', [NotificationSettingsController::class, 'index'])->name('index');
            Route::post('/', [NotificationSettingsController::class, 'update'])->name('update');
        });

        // Oria: conectar el negocio y, con plan Plus, abrir "Pagar servicios".
        Route::prefix('/settings/oria')->name('settings.oria.')->group(function () {
            Route::get('/', [OriaSettingsController::class, 'index'])->name('index');
            Route::post('/connect', [OriaSettingsController::class, 'connect'])->name('connect');
            Route::get('/callback', [OriaSettingsController::class, 'callback'])->name('callback');
            Route::delete('/', [OriaSettingsController::class, 'disconnect'])->name('disconnect');
            Route::get('/pay-services', [OriaSettingsController::class, 'payServices'])->name('pay-services');
        });
    });

    Route::get('/channels/{provider}', [ChannelController::class, 'show'])->name('channels.show');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('/daily-order-closures', [DailyOrderClosureController::class, 'index'])->name('daily-order-closures.index');
    Route::get('/daily-order-closures/create', [DailyOrderClosureController::class, 'create'])->name('daily-order-closures.create');
    Route::post('/daily-order-closures', [DailyOrderClosureController::class, 'store'])->name('daily-order-closures.store');
    Route::get('/daily-order-closures/{dailyOrderClosure}', [DailyOrderClosureController::class, 'show'])->name('daily-order-closures.show');
    Route::get('/daily-order-closures/{dailyOrderClosure}/export', [DailyOrderClosureController::class, 'export'])->name('daily-order-closures.export');
    Route::get('/incoming-messages', [IncomingMessageController::class, 'index'])->name('incoming-messages.index');
    Route::get('/operations', [OperationsController::class, 'index'])->name('operations.index');
    Route::get('/operations/focus', [OperationsController::class, 'focus'])->name('operations.focus');
    Route::get('/operations/feed', [OperationsController::class, 'feed'])->name('operations.feed');
    Route::get('/operations/orders/{order}/snapshot', [OperationsController::class, 'snapshot'])->name('operations.orders.snapshot');

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('/developer/webhook-simulator', [WebhookSimulatorController::class, 'index'])->name('developer.webhook-simulator');
        Route::post('/developer/webhook-simulator/send', [WebhookSimulatorController::class, 'send'])->name('developer.webhook-simulator.send');
        Route::post('/developer/webhook-simulator/generate', [WebhookSimulatorController::class, 'generate'])->name('developer.webhook-simulator.generate');
        Route::post('/developer/webhook-simulator/reset', [WebhookSimulatorController::class, 'reset'])->name('developer.webhook-simulator.reset');
        Route::post('/developer/toolkit/scenarios/small-hardware-store', [WebhookSimulatorController::class, 'generateSmallHardwareStoreScenario'])->name('developer.toolkit.scenarios.small-hardware-store');
        Route::post('/developer/toolkit/reset-demo-data', [WebhookSimulatorController::class, 'resetDemoData'])->name('developer.toolkit.reset-demo-data');
    });

    Route::prefix('/orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');
        Route::get('/{order}/edit', [OrderController::class, 'edit'])->name('edit');
        Route::patch('/{order}', [OrderController::class, 'update'])->name('update');
        Route::post('/{order}/confirm', [OrderController::class, 'confirm'])->name('confirm');
        Route::post('/{order}/prepare', [OrderController::class, 'prepare'])->name('prepare');
        Route::post('/{order}/ready-for-dispatch', [OrderController::class, 'readyForDispatch'])->name('ready-for-dispatch');
        Route::post('/{order}/dispatch', [OrderController::class, 'dispatch'])->name('dispatch');
        Route::post('/{order}/reject', [OrderController::class, 'reject'])->name('reject');
        Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
    });

    Route::get('/order-reviews', [OrderReviewController::class, 'index'])->name('order-reviews.index');

    Route::prefix('/products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::get('/create', [ProductController::class, 'create'])->name('create');
        Route::get('/import', [ProductImportController::class, 'index'])->name('import');
        Route::post('/import', [ProductImportController::class, 'store'])->name('import.store');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
        Route::patch('/{product}', [ProductController::class, 'update'])->name('update');
        Route::post('/{product}/toggle', [ProductController::class, 'toggle'])->name('toggle');
    });

    Route::post('/products/{product}/aliases', [ProductAliasController::class, 'store'])->name('product-aliases.store');
    Route::delete('/product-aliases/{productAlias}', [ProductAliasController::class, 'destroy'])->name('product-aliases.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
