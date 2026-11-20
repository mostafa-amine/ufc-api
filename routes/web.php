<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home']);
Route::get('/docs', [PageController::class, 'docs']);
Route::get('/terms', [PageController::class, 'terms']);
Route::get('/docs/openapi.yaml', [PageController::class, 'openapi']);
