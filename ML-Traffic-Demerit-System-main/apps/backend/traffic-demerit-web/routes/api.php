<?php

use App\Http\Controllers\Api\AppealController as ApiAppealController;
use App\Http\Controllers\Api\AssignmentController as ApiAssignmentController;
use App\Http\Controllers\Api\ChartController;
use App\Http\Controllers\Api\DriverController as ApiDriverController;
use App\Http\Controllers\Api\PaymentController as ApiPaymentController;
use App\Http\Controllers\Api\StationController as ApiStationController;
use App\Http\Controllers\Api\UserController as ApiUserController;
use App\Http\Controllers\Api\VehicleController as ApiVehicleController;
use App\Http\Controllers\Api\ViolationController as ApiViolationController;
use App\Http\Controllers\AuthApiController;
use App\Http\Controllers\DriverPortalController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\MpesaCallbackController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\ViolationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
| Portal-specific logins: /api/auth/{driver|officer|admin}/login.
| A role mismatch on a portal returns the generic
| "Invalid credentials for this portal". 5 failed attempts lock the
| identity for 15 minutes. Every attempt lands in login_audits.
| Only drivers self-register.
*/

Route::prefix('auth')->middleware('https.only')->group(function () {
    // Portal-scoped logins (the frontend uses these).
    Route::post('/{portal}/login', [AuthApiController::class, 'portalLogin'])
        ->whereIn('portal', ['driver', 'officer', 'admin']);

    // Legacy portal-agnostic login (kept for API compatibility/tests).
    Route::post('/login', [AuthApiController::class, 'login']);

    // Self-registration is DRIVER ONLY. Officers/admins are provisioned
    // by the admin portal.
    Route::post('/register', [AuthApiController::class, 'register']);

    Route::middleware('auth.jwt')->group(function () {
        Route::get('/me', [AuthApiController::class, 'me']);
        Route::post('/logout', [AuthApiController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| System status (admin footer)
|--------------------------------------------------------------------------
*/

Route::get('/system/status', SystemStatusController::class)
    ->middleware(['https.only', 'auth.jwt']);

/*
|--------------------------------------------------------------------------
| Chart endpoints - every series is a MySQL aggregate and works even
| when the Python ML service is down.
|--------------------------------------------------------------------------
*/

Route::prefix('charts')->middleware(['https.only', 'auth.jwt'])->group(function () {
    Route::get('/admin', [ChartController::class, 'admin'])->middleware('role:admin,officer');
    Route::get('/ml-insights', [ChartController::class, 'mlInsights'])->middleware('role:admin');
    Route::get('/driver', [ChartController::class, 'driver'])->middleware('role:motorist');
    Route::get('/officer', [ChartController::class, 'officer'])->middleware('role:officer,admin');
});

/*
|--------------------------------------------------------------------------
| REST CRUD (pagination + search + sort + filters, policies, transactions)
|--------------------------------------------------------------------------
*/

Route::middleware(['https.only', 'auth.jwt'])->group(function () {
    Route::apiResource('users', ApiUserController::class)->except(['edit', 'create']);
    Route::apiResource('drivers', ApiDriverController::class)->except(['edit', 'create']);
    Route::apiResource('vehicles', ApiVehicleController::class)->except(['edit', 'create']);
    Route::apiResource('stations', ApiStationController::class)->except(['edit', 'create']);
    Route::apiResource('assignments', ApiAssignmentController::class)->except(['edit', 'create']);
    Route::apiResource('violations', ApiViolationController::class)->except(['edit', 'create']);
    Route::apiResource('appeals', ApiAppealController::class)->except(['edit', 'create']);

    // Payments: create + void only (no PUT/PATCH/DELETE).
    Route::get('payments', [ApiPaymentController::class, 'index']);
    Route::get('payments/{payment}', [ApiPaymentController::class, 'show']);
    Route::post('payments', [ApiPaymentController::class, 'store']);
    Route::post('payments/{payment}/void', [ApiPaymentController::class, 'void']);
    Route::get('payments/methods', [ApiPaymentController::class, 'methods']);

    // Payment verification (legacy flow kept).
    Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])
        ->middleware('role:officer,admin,motorist');
});

/*
|--------------------------------------------------------------------------
| Legacy integration endpoints (kept: existing dashboards + tests)
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Portal flows
|--------------------------------------------------------------------------
*/

Route::middleware(['https.only', 'auth.jwt'])->group(function () {
    // Motorist self-service portal (eCitizen-style dashboard)
    Route::get('/portal/dashboard', [DriverPortalController::class, 'dashboard'])
        ->middleware('role:motorist');

    // NOTE: POST /api/violations is served by the CRUD pipeline
    // (Api\ViolationController::store via apiResource) which performs the
    // ledger -> balance -> risk score -> notification closed loop.

    // Payment (mock M-Pesa/card, mirrors eCitizen checkout)
    Route::post('/violations/{violation}/pay', [PaymentController::class, 'initiate'])
        ->middleware('role:motorist');

    // M-Pesa STK Push (legacy)
    Route::post('/violations/{violation}/pay/mpesa', [PaymentController::class, 'payMpesa'])
        ->middleware('role:motorist');
    Route::get('/payments/{payment}/status', [PaymentController::class, 'status'])
        ->middleware('auth.jwt');
});

/*
|--------------------------------------------------------------------------
| Public payment callback endpoints (called by payment gateways)
| These MUST NOT be behind auth.jwt — gateways cannot authenticate.
|--------------------------------------------------------------------------
*/
Route::prefix('payments')->group(function () {
    Route::post('/mpesa/callback/{token}', [MpesaCallbackController::class, 'handle']);
    Route::post('/callback/{provider}', [PaymentCallbackController::class, 'handle']);
});
