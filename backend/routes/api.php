<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Wallet\WalletController;
use App\Services\Fraud\AntiFraudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

Route::prefix('v1')->group(function () {
    // Healthcheck endpoint
    Route::get('/health', function (): JsonResponse {
        $dbStatus = 'disconnected';
        $redisStatus = 'disconnected';

        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Throwable $e) {
            $dbStatus = 'error: ' . $e->getMessage();
        }

        try {
            Redis::connection()->ping();
            $redisStatus = 'connected';
        } catch (\Throwable $e) {
            $redisStatus = 'error: ' . $e->getMessage();
        }

        return response()->json([
            'success' => true,
            'message' => 'API System Status',
            'data' => [
                'environment' => config('app.env'),
                'database' => [
                    'default' => config('database.default'),
                    'status' => $dbStatus,
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                    'redis_status' => $redisStatus,
                ],
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    });

    // Public Authentication Endpoints
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware(['throttle:10,1', 'anti_fraud:registration']);
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware(['throttle:10,1', 'anti_fraud:login']);

        // Email Verification route named 'verification.verify' for signed URL matching
        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->name('verification.verify');

        // Protected Auth Endpoints
        Route::middleware(['auth:sanctum', 'anti_fraud:authenticated'])->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');
        });
    });

    // Wallet Endpoints
    Route::middleware(['auth:sanctum', 'anti_fraud:wallet'])->prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'balance']);
    });

    // Fraud Diagnostics & IP Reputation Endpoint
    Route::get('/fraud/inspect-ip', function (Request $request, AntiFraudService $antiFraud): JsonResponse {
        $ip = $request->ip() ?? '127.0.0.1';
        $result = $antiFraud->inspectIp($ip);

        return response()->json([
            'success' => true,
            'message' => 'IP Risk Inspection Result',
            'data' => $result->toArray(),
        ]);
    });
});
