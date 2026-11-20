<?php

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\LoginController;
use App\Http\Controllers\Account\RegisterController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home']);
Route::get('/docs', [PageController::class, 'docs']);
Route::get('/terms', [PageController::class, 'terms']);
Route::get('/docs/openapi.yaml', [PageController::class, 'openapi']);

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::post('/dashboard/keys', [DashboardController::class, 'store'])->name('keys.store');
    Route::delete('/dashboard/keys/{key}', [DashboardController::class, 'destroy'])->whereNumber('key')->name('keys.destroy');
});
