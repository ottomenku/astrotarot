<?php

use App\Http\Controllers\Admin\EphemerisController;
use App\Http\Controllers\Admin\PromptController;
use App\Http\Controllers\Admin\ReadingLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SlotAllocationController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TestModeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CreditPackageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PromoShareController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\ReadingExportController;
use App\Http\Controllers\ReadingHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/promo', PromoShareController::class)->name('promo.share');

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::post('/reading', ReadingController::class)
    ->middleware(['auth', 'throttle:8,1'])
    ->name('reading');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::post('/register', [RegisterController::class, 'store'])
        ->name('register');

    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->whereIn('provider', ['google', 'facebook'])
        ->name('auth.social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->whereIn('provider', ['google', 'facebook'])
        ->name('auth.social.callback');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/credits', [CreditPackageController::class, 'index'])->name('credits.packages');
    Route::post('/credits/request', [CreditPackageController::class, 'request'])->name('credits.request');
    Route::post('/credits/promo', [CreditPackageController::class, 'activatePromo'])->name('credits.promo');
    Route::get('/my-readings', [ReadingHistoryController::class, 'index'])->name('readings.history');
    Route::get('/my-readings/{reading}', [ReadingHistoryController::class, 'show'])->name('readings.history.show');
    Route::post('/reading/export/pdf', [ReadingExportController::class, 'pdf'])
        ->middleware('throttle:12,1')
        ->name('reading.export.pdf');
    Route::post('/reading/export/email', [ReadingExportController::class, 'email'])
        ->middleware('throttle:6,1')
        ->name('reading.export.email');
    Route::post('/reading/share', [ReadingExportController::class, 'share'])
        ->middleware('throttle:12,1')
        ->name('reading.share.create');
});

Route::get('/share/{token}', [ReadingExportController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{20,64}')
    ->name('reading.share.show');
Route::get('/share/{token}/og.jpg', [ReadingExportController::class, 'ogImage'])
    ->where('token', '[A-Za-z0-9]{20,64}')
    ->name('reading.share.og');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function () {
        Route::redirect('/', '/admin/ephemeris')->name('home');

        Route::get('/ephemeris', [EphemerisController::class, 'index'])->name('ephemeris.index');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('/test-mode', [TestModeController::class, 'edit'])->name('test-mode.edit');
        Route::put('/test-mode', [TestModeController::class, 'update'])->name('test-mode.update');

        Route::get('/slots', [SlotAllocationController::class, 'edit'])->name('slots.edit');
        Route::put('/slots', [SlotAllocationController::class, 'update'])->name('slots.update');

        Route::get('/prompts', [PromptController::class, 'edit'])->name('prompts.edit');
        Route::put('/prompts', [PromptController::class, 'update'])->name('prompts.update');
        Route::post('/prompts/reset', [PromptController::class, 'reset'])->name('prompts.reset');

        Route::get('/readings', [ReadingLogController::class, 'index'])->name('readings.index');
        Route::get('/readings/{reading}', [ReadingLogController::class, 'show'])->name('readings.show');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::post('/templates/{template}/activate', [TemplateController::class, 'activate'])->name('templates.activate');
    });
