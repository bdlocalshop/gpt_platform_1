<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Offers\OfferController;
use App\Http\Controllers\Api\V1\Postbacks\PostbackController;
use App\Http\Controllers\Api\V1\Referrals\ReferralController;
use App\Http\Controllers\Api\V1\Wallet\PayoutController;
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
        Route::get('/transactions', [WalletController::class, 'transactions']);
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::post('/payouts', [PayoutController::class, 'store']);
    });

    // Admin Panel Endpoints (Protected by Sanctum + is_admin)
    Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/payouts', [AdminController::class, 'payouts']);
        Route::post('/payouts/{id}/approve', [AdminController::class, 'approvePayout']);
        Route::post('/payouts/{id}/reject', [AdminController::class, 'rejectPayout']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users/{id}/status', [AdminController::class, 'updateUserStatus']);
        Route::get('/fraud-flags', [AdminController::class, 'fraudFlags']);
        Route::post('/fraud-flags/{id}/resolve', [AdminController::class, 'resolveFraudFlag']);
    });

    // Referrals Endpoints
    Route::middleware(['auth:sanctum', 'anti_fraud:referrals'])->prefix('referrals')->group(function () {
        Route::get('/', [ReferralController::class, 'index']);
    });

    // Offers & Offerwall Endpoints
    Route::middleware(['auth:sanctum', 'anti_fraud:offers'])->prefix('offers')->group(function () {
        Route::get('/providers', [OfferController::class, 'providers']);
        Route::get('/featured', [OfferController::class, 'featuredOffers']);
    });

    // Postback S2S Webhooks
    Route::prefix('postbacks')->group(function () {
        Route::match(['get', 'post'], '/bitlabs', [PostbackController::class, 'handleBitLabs'])
            ->middleware('verify_postback_ip:bitlabs');

        Route::match(['get', 'post'], '/cpx', [PostbackController::class, 'handleCpx'])
            ->middleware('verify_postback_ip:cpx');

        Route::match(['get', 'post'], '/offerwall-gg', [PostbackController::class, 'handleOfferwallGg'])
            ->middleware('verify_postback_ip:offerwall_gg');

        Route::match(['get', 'post'], '/offerwall-com', [PostbackController::class, 'handleOfferwallCom'])
            ->middleware('verify_postback_ip:offerwall_com');
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
