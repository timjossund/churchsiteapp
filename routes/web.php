<?php

use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [SiteController::class, 'index'])->name('dashboard');
    Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('sites/{site}', [SiteController::class, 'show'])->whereNumber('site')->name('sites.show');
    Route::patch('sites/{site}', [SiteController::class, 'update'])->whereNumber('site')->name('sites.update');
});

require __DIR__.'/settings.php';
