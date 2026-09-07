<?php

use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\CardPackController;
use App\Http\Controllers\Admin\DataDeletionRequestController;
use App\Http\Controllers\Admin\EphemerisController;
use App\Http\Controllers\Admin\FeatureAccessController;
use App\Http\Controllers\Admin\GameController;
use App\Http\Controllers\Admin\LegalController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PromptController;
use App\Http\Controllers\Admin\ReadingLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SlotAllocationController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TestModeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LegalAcceptController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogLikeController;
use App\Http\Controllers\CreditPackageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PackPreferenceController;
use App\Http\Controllers\PromoShareController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\ReadingExportController;
use App\Http\Controllers\ReadingHistoryController;
use App\Support\FeatureCatalog;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/promo', PromoShareController::class)->name('promo.share');

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::post('/reading', ReadingController::class)
    ->middleware(['auth', 'throttle:8,1'])
    ->name('reading');

Route::get('/aszf', [LegalPageController::class, 'terms'])->name('legal.terms');
Route::get('/adatkezeles', [LegalPageController::class, 'privacy'])->name('legal.privacy');
Route::get('/kapcsolat', [LegalPageController::class, 'contact'])->name('legal.contact');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post}', [BlogController::class, 'show'])->name('blog.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::post('/register', [RegisterController::class, 'store'])
        ->name('register');
    Route::get('/register/legal', [LegalAcceptController::class, 'create'])->name('register.legal');
    Route::post('/register/legal', [LegalAcceptController::class, 'store'])->name('register.legal.store');

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
    Route::get('/credits', [CreditPackageController::class, 'index'])
        ->middleware('feature:'.FeatureCatalog::CREDITS_PAGE)
        ->name('credits.packages');
    Route::post('/credits/request', [CreditPackageController::class, 'request'])
        ->middleware('feature:'.FeatureCatalog::CREDITS_REQUEST)
        ->name('credits.request');
    Route::post('/credits/promo', [CreditPackageController::class, 'activatePromo'])
        ->middleware('feature:'.FeatureCatalog::CREDITS_PROMO)
        ->name('credits.promo');
    Route::get('/account', [AccountController::class, 'show'])
        ->middleware('feature:'.FeatureCatalog::ACCOUNT)
        ->name('account.show');
    Route::get('/account/readings/{reading}', [AccountController::class, 'showReading'])
        ->middleware('feature:'.FeatureCatalog::ACCOUNT)
        ->name('account.readings.show');
    Route::delete('/account/readings/{reading}', [AccountController::class, 'destroyReading'])
        ->middleware('feature:'.FeatureCatalog::ACCOUNT)
        ->name('account.readings.destroy');
    Route::delete('/account/readings', [AccountController::class, 'destroyAllReadings'])
        ->middleware('feature:'.FeatureCatalog::ACCOUNT)
        ->name('account.readings.destroy-all');
    Route::post('/account/deletion-request', [AccountController::class, 'storeDeletionRequest'])
        ->middleware(['feature:'.FeatureCatalog::ACCOUNT, 'throttle:5,60'])
        ->name('account.deletion-request.store');
    Route::post('/account/packs', [PackPreferenceController::class, 'update'])
        ->middleware('feature:'.FeatureCatalog::ACCOUNT)
        ->name('account.packs.update');
    Route::get('/my-readings', [ReadingHistoryController::class, 'index'])
        ->middleware('feature:'.FeatureCatalog::HISTORY)
        ->name('readings.history');
    Route::get('/my-readings/{reading}', [ReadingHistoryController::class, 'show'])
        ->middleware('feature:'.FeatureCatalog::HISTORY)
        ->name('readings.history.show');
    Route::post('/reading/export/pdf', [ReadingExportController::class, 'pdf'])
        ->middleware('throttle:12,1')
        ->name('reading.export.pdf');
    Route::post('/reading/export/email', [ReadingExportController::class, 'email'])
        ->middleware('throttle:6,1')
        ->name('reading.export.email');
    Route::post('/reading/share', [ReadingExportController::class, 'share'])
        ->middleware('throttle:12,1')
        ->name('reading.share.create');

    Route::post('/blog/{post}/like', BlogLikeController::class)
        ->middleware('throttle:30,1')
        ->name('blog.like');
    Route::post('/blog/{post}/comments', [BlogCommentController::class, 'store'])
        ->middleware('throttle:8,1')
        ->name('blog.comments.store');
    Route::delete('/blog/{post}/comments/{comment}', [BlogCommentController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('blog.comments.destroy');
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

        Route::get('/legal', [LegalController::class, 'edit'])->name('legal.edit');
        Route::put('/legal', [LegalController::class, 'update'])->name('legal.update');

        Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
        Route::get('/packages/create', [PackageController::class, 'create'])->name('packages.create');
        Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
        Route::get('/packages/{package}/edit', [PackageController::class, 'edit'])->name('packages.edit');
        Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::post('/packages/{package}/toggle', [PackageController::class, 'toggle'])->name('packages.toggle');

        Route::get('/games', [GameController::class, 'index'])->name('games.index');
        Route::get('/games/create', [GameController::class, 'create'])->name('games.create');
        Route::post('/games', [GameController::class, 'store'])->name('games.store');
        Route::get('/games/{game}/edit', [GameController::class, 'edit'])->name('games.edit');
        Route::put('/games/{game}', [GameController::class, 'update'])->name('games.update');
        Route::post('/games/{game}/toggle', [GameController::class, 'toggle'])->name('games.toggle');
        Route::post('/games/{game}/default', [GameController::class, 'makeDefault'])->name('games.default');
        Route::delete('/games/{game}', [GameController::class, 'destroy'])->name('games.destroy');

        Route::get('/packs', [CardPackController::class, 'index'])->name('packs.index');
        Route::get('/packs/create', [CardPackController::class, 'create'])->name('packs.create');
        Route::post('/packs', [CardPackController::class, 'store'])->name('packs.store');
        Route::get('/packs/{pack}/edit', [CardPackController::class, 'edit'])->name('packs.edit');
        Route::put('/packs/{pack}', [CardPackController::class, 'update'])->name('packs.update');
        Route::post('/packs/{pack}/toggle', [CardPackController::class, 'toggle'])->name('packs.toggle');
        Route::post('/packs/{pack}/default', [CardPackController::class, 'makeDefault'])->name('packs.default');
        Route::delete('/packs/{pack}', [CardPackController::class, 'destroy'])->name('packs.destroy');

        Route::get('/blog', [BlogPostController::class, 'index'])->name('blog.index');
        Route::get('/blog/create', [BlogPostController::class, 'create'])->name('blog.create');
        Route::post('/blog', [BlogPostController::class, 'store'])->name('blog.store');
        Route::get('/blog/{post}/edit', [BlogPostController::class, 'edit'])->name('blog.edit');
        Route::put('/blog/{post}', [BlogPostController::class, 'update'])->name('blog.update');
        Route::delete('/blog/{post}', [BlogPostController::class, 'destroy'])->name('blog.destroy');

        Route::get('/access', [FeatureAccessController::class, 'edit'])->name('feature-access.edit');
        Route::put('/access', [FeatureAccessController::class, 'update'])->name('feature-access.update');

        Route::get('/readings', [ReadingLogController::class, 'index'])->name('readings.index');
        Route::get('/readings/{reading}', [ReadingLogController::class, 'show'])->name('readings.show');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('/deletion-requests', [DataDeletionRequestController::class, 'index'])->name('deletion-requests.index');
        Route::post('/deletion-requests/{deletionRequest}/process', [DataDeletionRequestController::class, 'process'])->name('deletion-requests.process');
        Route::post('/deletion-requests/{deletionRequest}/reject', [DataDeletionRequestController::class, 'reject'])->name('deletion-requests.reject');

        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::post('/templates/{template}/activate', [TemplateController::class, 'activate'])->name('templates.activate');
    });
