<?php

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\PayoutService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayoutController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PayoutService $payoutService
    ) {}

    /**
     * Submit a new cryptocurrency payout request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset' => ['required', 'string', 'in:USDT_TRC20,USDT_BEP20,LTC,BTC'],
            'crypto_address' => ['required', 'string', 'max:120'],
            'points_amount' => ['required', 'numeric', 'min:5000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $payout = $this->payoutService->requestPayout(
                user: $user,
                asset: $validated['asset'],
                address: $validated['crypto_address'],
                pointsAmount: $validated['points_amount'],
                request: $request
            );

            $message = $payout->status === PayoutRequest::STATUS_COMPLETED
                ? 'Payout dispatched automatically.'
                : 'Payout request submitted and queued for security review.';

            return $this->success([
                'uuid' => $payout->uuid,
                'asset' => $payout->asset,
                'crypto_address' => $payout->crypto_address,
                'points_amount' => $payout->points_amount,
                'usd_amount' => $payout->usd_amount,
                'crypto_amount' => $payout->crypto_amount,
                'status' => $payout->status,
                'tx_hash' => $payout->tx_hash,
                'created_at' => $payout->created_at->toIso8601String(),
            ], $message, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return $this->error('Failed to process payout request: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List user's historical payout requests.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payouts = PayoutRequest::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->paginate(15);

        return $this->success([
            'payouts' => $payouts->items(),
            'pagination' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }
}
