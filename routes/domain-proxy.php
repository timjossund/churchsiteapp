<?php

use App\Http\Controllers\DomainProxyController;
use Illuminate\Support\Facades\Route;

// Deliberately outside the web group: no sessions, cookies, or CSRF state.
Route::any('/_domain/request', DomainProxyController::class);
