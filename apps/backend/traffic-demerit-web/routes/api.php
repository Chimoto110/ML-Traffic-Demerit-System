<?php

use App\Http\Controllers\AuthApiController;
use App\Http\Controllers\DriverPortalController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ViolationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('https.only')->group(function () {
    Route::post('/login', [AuthApiController::class, 'login']);
    Route::post('/register', [AuthApiController::class, 'register']);

    Route::middleware('auth.jwt')->group(function () {
        Route::get('/me', [AuthApiController::class, 'me']);
        Route::post('/logout', [AuthApiController::class, 'logout']);
    });
});

Route::middleware(['https.only', 'auth.jwt'])->group(function () {
    Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])
        ->middleware('role:officer,admin,motorist');
});

Route::prefix('integration')->middleware(['https.only', 'auth.jwt'])->group(function () {
    Route::get('/entities/{entity}', [IntegrationController::class, 'listEntity'])
        ->middleware('role:officer,admin,motorist');
    Route::get('/entities/{entity}/filter', [IntegrationController::class, 'filterEntity'])
        ->middleware('role:officer,admin,motorist');
    Route::post('/entities/{entity}', [IntegrationController::class, 'createEntity'])
        ->middleware('role:officer,admin,motorist');
    Route::patch('/entities/{entity}/{id}', [IntegrationController::class, 'updateEntity'])
        ->middleware('role:officer,admin,motorist');
    Route::post('/functions/{name}', [IntegrationController::class, 'invokeFunction'])
        ->middleware('role:officer,admin,motorist');
});

// --- Auth handled by Laravel Breeze/Sanctum (php artisan breeze:install api) ---
// This file assumes 'auth:sanctum' issues a token on login and every
// request below carries it.

Route::middleware(['https.only', 'auth.jwt'])->group(function () {

    // Motorist self-service portal (eCitizen-style dashboard)
    Route::get('/portal/dashboard', [DriverPortalController::class, 'dashboard'])
        ->middleware('role:motorist');

    // Officer: log a new violation -> triggers ledger + ML risk + sanction pipeline
    Route::post('/violations', [ViolationController::class, 'store'])
        ->middleware('role:officer,admin');

    // Payment (mock M-Pesa/card, mirrors eCitizen checkout)
    Route::post('/violations/{violation}/pay', [PaymentController::class, 'initiate'])
        ->middleware('role:motorist');

});
