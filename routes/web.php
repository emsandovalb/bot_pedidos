<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchDailyClosureController;
use App\Http\Controllers\DailyOrderClosureController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChannelOnboardingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\IncomingMessageController;
use App\Http\Controllers\IntakeRequestController;
use App\Http\Controllers\NumberBoardController;
use App\Http\Controllers\NumberLimitController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderReviewController;
use App\Http\Controllers\SetupRequestController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductAliasController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PilotPageController;
use App\Http\Controllers\SimulatorController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::get('/channels/whatsapp', [ChannelController::class, 'whatsapp'])->name('channels.whatsapp');
    Route::post('/channels/whatsapp/onboarding', [ChannelOnboardingController::class, 'update'])->name('channels.whatsapp.onboarding.update');
    Route::get('/channels/whatsapp/status', [ChannelController::class, 'status'])->name('channels.whatsapp.status');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/setup-requests', [SetupRequestController::class, 'index'])->name('setup-requests.index');
    Route::post('/setup-requests', [SetupRequestController::class, 'store'])->name('setup-requests.store');
    Route::get('/setup-requests/{setupRequest}', [SetupRequestController::class, 'show'])->name('setup-requests.show');
    Route::patch('/setup-requests/{setupRequest}', [SetupRequestController::class, 'update'])->name('setup-requests.update');
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('/daily-order-closures', [DailyOrderClosureController::class, 'index'])->name('daily-order-closures.index');
    Route::get('/daily-order-closures/create', [DailyOrderClosureController::class, 'create'])->name('daily-order-closures.create');
    Route::post('/daily-order-closures', [DailyOrderClosureController::class, 'store'])->name('daily-order-closures.store');
    Route::get('/daily-order-closures/{dailyOrderClosure}', [DailyOrderClosureController::class, 'show'])->name('daily-order-closures.show');
    Route::get('/daily-order-closures/{dailyOrderClosure}/export', [DailyOrderClosureController::class, 'export'])->name('daily-order-closures.export');
    Route::get('/closures', [BranchDailyClosureController::class, 'index'])->name('closures.index');
    Route::post('/closures', [BranchDailyClosureController::class, 'store'])->name('closures.store');
    Route::get('/closures/{closure}', [BranchDailyClosureController::class, 'show'])->name('closures.show');
    Route::get('/closures/{closure}/export', [BranchDailyClosureController::class, 'export'])->name('closures.export');
    Route::get('/incoming-messages', [IncomingMessageController::class, 'index'])->name('incoming-messages.index');

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

    if (config('features.legacy_lottery_routes_enabled', false)) {
        Route::get('/pilot-checklist', [PilotPageController::class, 'checklist'])->name('pilot.checklist');
        Route::get('/pilot-script', [PilotPageController::class, 'script'])->name('pilot.script');
        Route::get('/operator-guide', [PilotPageController::class, 'guide'])->name('pilot.guide');

        Route::get('/simulator', [SimulatorController::class, 'index'])->name('simulator.index');
        Route::post('/simulator', [SimulatorController::class, 'store'])->name('simulator.store');
        Route::get('/numbers', [NumberBoardController::class, 'index'])->name('numbers.index');
        Route::post('/numbers', [NumberBoardController::class, 'store'])->name('numbers.store');

        Route::prefix('/limits')->name('limits.')->group(function () {
            Route::get('/', [NumberLimitController::class, 'index'])->name('index');
            Route::get('/create', [NumberLimitController::class, 'create'])->name('create');
            Route::post('/', [NumberLimitController::class, 'store'])->name('store');
            Route::get('/{limit}/edit', [NumberLimitController::class, 'edit'])->name('edit');
            Route::put('/{limit}', [NumberLimitController::class, 'update'])->name('update');
            Route::delete('/{limit}', [NumberLimitController::class, 'destroy'])->name('delete');
        });

        Route::prefix('/requests')->name('intake-requests.')->group(function () {
            Route::get('/', [IntakeRequestController::class, 'index'])->name('index');
            Route::get('/{intakeRequest}', [IntakeRequestController::class, 'show'])->name('show');
            Route::get('/{intakeRequest}/edit', [IntakeRequestController::class, 'edit'])->name('edit');
            Route::patch('/{intakeRequest}', [IntakeRequestController::class, 'update'])->name('update');
            Route::post('/{intakeRequest}/confirm', [IntakeRequestController::class, 'confirm'])->name('confirm');
            Route::post('/{intakeRequest}/reject', [IntakeRequestController::class, 'reject'])->name('reject');
        });
    }
});

require __DIR__.'/auth.php';
