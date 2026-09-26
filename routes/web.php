<?php

use App\Http\Controllers\PublishedSiteController;
use App\Http\Controllers\SiteBlockController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteMediaController;
use App\Http\Controllers\SitePublishingController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('s/{slug}/media/{mediaAsset}', [PublishedSiteController::class, 'media'])
    ->where(['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'mediaAsset' => '[0-9]+'])
    ->name('sites.published.media.show');
Route::get('s/{slug}', [PublishedSiteController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('sites.published.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [SiteController::class, 'index'])->name('dashboard');
    Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('sites/{site}', [SiteController::class, 'show'])->whereNumber('site')->name('sites.show');
    Route::patch('sites/{site}', [SiteController::class, 'update'])->whereNumber('site')->name('sites.update');
    Route::post('sites/{site}/publish', [SitePublishingController::class, 'publish'])->whereNumber('site')->name('sites.publish');
    Route::post('sites/{site}/blocks/{block}/image', [SiteMediaController::class, 'uploadBlockImage'])->whereNumber(['site', 'block'])->name('sites.blocks.image.store');
    Route::post('sites/{site}/logo', [SiteMediaController::class, 'uploadLogo'])->whereNumber('site')->name('sites.logo.store');
    Route::delete('sites/{site}/logo', [SiteMediaController::class, 'clearLogo'])->whereNumber('site')->name('sites.logo.destroy');
    Route::post('sites/{site}/social-image', [SiteMediaController::class, 'uploadSocialImage'])->whereNumber('site')->name('sites.social-image.store');
    Route::delete('sites/{site}/social-image', [SiteMediaController::class, 'clearSocialImage'])->whereNumber('site')->name('sites.social-image.destroy');
    Route::patch('sites/{site}/media/{mediaAsset}', [SiteMediaController::class, 'updateAltText'])->whereNumber(['site', 'mediaAsset'])->name('sites.media.update');
    Route::get('sites/{site}/media/{mediaAsset}', [SiteMediaController::class, 'show'])->whereNumber(['site', 'mediaAsset'])->name('sites.media.show');
    Route::post('sites/{site}/blocks', [SiteBlockController::class, 'store'])->whereNumber('site')->name('sites.blocks.store');
    Route::patch('sites/{site}/blocks/order', [SiteBlockController::class, 'order'])->whereNumber('site')->name('sites.blocks.order');
    Route::patch('sites/{site}/blocks/{block}', [SiteBlockController::class, 'update'])->whereNumber(['site', 'block'])->name('sites.blocks.update');
    Route::delete('sites/{site}/blocks/{block}', [SiteBlockController::class, 'destroy'])->whereNumber(['site', 'block'])->name('sites.blocks.destroy');
});

require __DIR__.'/settings.php';
