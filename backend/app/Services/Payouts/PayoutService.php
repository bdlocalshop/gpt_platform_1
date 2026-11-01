<?php

namespace App\Services\Payouts;

use App\Models\FraudFlag;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Fraud\AntiFraudService;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PayoutService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected AntiFraudService $antiFraudService,
        protected CryptoAddressValidator $addressValidator,
        protected CryptoDisbursementService $disbursementService
    ) {}

    /**
     * Submit a new cryptocurrency payout request.
     */
    public function requestPayout(
        User $user,
        string $asset,
        string $address,
        string|float $pointsAmount,
        Request $request
    ): PayoutRequest {
        $pointsAmount = number_format((float) $pointsAmount, 4, '.', '');
        $address = trim($address);

        // 1. Minimum withdrawal requirement: 5,000 Points ($5.00 USD)
        if (bccomp($pointsAmount, '5000.0000', 4) < 0) {
            throw new InvalidArgumentException('Minimum withdrawal amount is 5,000 Points ($5.00 USD).');
        }

        // 2. Email verification invariant
        if (!$user->hasVerifiedEmail()) {
            throw new InvalidArgumentException('Email address must be verified before submitting payout requests.');
        }

        // 3. Crypto address format validation
        if (!$this->addressValidator->isValid($asset, $address)) {
            throw new InvalidArgumentException("Invalid destination address format for {$asset}.");
        }

        // 4. Sufficient available balance validation
        $availableBalance = $this->ledgerService->getUserAvailableBalance($user);
        if (bccomp($availableBalance, $pointsAmount, 4) < 0) {
            throw new InvalidArgumentException('Insufficient available points balance.');
        }

        // 5. Anti-fraud check: Crypto address collision across accounts
        $hasAddressCollision = $this->antiFraudService->checkCryptoAddressCollision($address, $user->id);

        // 6. Check if user is eligible for automated disbursement
        $completedCashoutsCount = PayoutRequest::where('user_id', $user->id)
            ->where('status', PayoutRequest::STATUS_COMPLETED)
            ->count();

        $hasUnresolvedFraudFlags = FraudFlag::where('user_id', $user->id)
            ->where('is_resolved', false)
            ->exists();

        $usdAmount = bcdiv($pointsAmount, '1000', 4);
        $isOverAutoLimit = bccomp($usdAmount, '20.0000', 4) >= 0;

        // Invariant: First withdrawal, address collision, unresolved fraud, or > $20 requires admin review
        $requiresManualReview = ($completedCashoutsCount === 0)
            || $hasAddressCollision
            || $hasUnresolvedFraudFlags
            || $isOverAutoLimit;

        $ip = $request->header('X-Simulate-Ip') ?? $request->ip() ?? '127.0.0.1';
        $fingerprint = $request->header('X-Device-Fingerprint') ?? $user->registration_fingerprint;

        $uuid = Str::uuid()->toString();

        return DB::transaction(function () use (
            $user,
            $asset,
            $address,
            $pointsAmount,
            $usdAmount,
            $uuid,
            $ip,
            $fingerprint,
            $requiresManualReview
        ) {
            // Lock points in ledger: DEBIT user_available, CREDIT user_locked
            $lockTx = $this->ledgerService->lockBalanceForPayout(
                user: $user,
                amount: $pointsAmount,
                payoutUuid: $uuid,
                description: "Crypto withdrawal hold for {$pointsAmount} PTS to {$asset}"
            );

            // Compute crypto amount estimation
            $cryptoAmount = match ($asset) {
                'USDT_TRC20', 'USDT_BEP20' => $usdAmount,
                'LTC' => number_format((float) $usdAmount / 80.0, 8, '.', ''),
                'BTC' => number_format((float) $usdAmount / 65000.0, 8, '.', ''),
                default => $usdAmount,
            };

            $payout = PayoutRequest::create([
                'uuid' => $uuid,
                'user_id' => $user->id,
                'transaction_id' => $lockTx->id,
                'asset' => $asset,
                'crypto_address' => $address,
                'points_amount' => $pointsAmount,
                'usd_amount' => $usdAmount,
                'crypto_amount' => $cryptoAmount,
                'status' => PayoutRequest::STATUS_PENDING,
                'ip_address' => $ip,
                'device_fingerprint' => $fingerprint,
            ]);

            // If verified clean account with previous history under $20, automated instant dispatch
            if (!$requiresManualReview) {
                $dispatch = $this->disbursementService->dispatch($payout);
                if ($dispatch['success']) {
                    $this->ledgerService->settlePayoutDisbursement(
                        user: $user,
                        amount: $pointsAmount,
                        payoutUuid: $uuid,
                        description: "Automated instant crypto disbursement: {$asset} ({$dispatch['tx_hash']})"
                    );

                    $payout->update([
                        'status' => PayoutRequest::STATUS_COMPLETED,
                        'tx_hash' => $dispatch['tx_hash'],
                        'reviewed_at' => now(),
                        'admin_notes' => 'Automated fast-track dispatch approved by system.',
                    ]);
                }
            }

            return $payout->fresh();
        });
    }

    /**
     * Admin manually approves and settles a pending payout.
     */
    public function approvePayout(PayoutRequest $payout, User $reviewer, ?string $notes = null): PayoutRequest
    {
        if ($payout->status !== PayoutRequest::STATUS_PENDING) {
            throw new InvalidArgumentException("Payout [{$payout->uuid}] is not in pending status.");
        }

        return DB::transaction(function () use ($payout, $reviewer, $notes) {
            $dispatch = $this->disbursementService->dispatch($payout);

            // Settle locked funds in ledger: DEBIT user_locked, CREDIT platform_liability
            $this->ledgerService->settlePayoutDisbursement(
                user: $payout->user,
                amount: $payout->points_amount,
                payoutUuid: $payout->uuid,
                description: "Manual admin approval disbursement: {$payout->asset} ({$dispatch['tx_hash']})"
            );

            $payout->update([
                'status' => PayoutRequest::STATUS_COMPLETED,
                'reviewed_by_id' => $reviewer->id,
                'reviewed_at' => now(),
                'tx_hash' => $dispatch['tx_hash'],
                'admin_notes' => $notes ?? 'Approved by administrator.',
            ]);

            return $payout->fresh();
        });
    }

    /**
     * Admin manually rejects a pending payout and refunds locked points.
     */
    public function rejectPayout(PayoutRequest $payout, User $reviewer, string $reason): PayoutRequest
    {
        if ($payout->status !== PayoutRequest::STATUS_PENDING) {
            throw new InvalidArgumentException("Payout [{$payout->uuid}] is not in pending status.");
        }

        return DB::transaction(function () use ($payout, $reviewer, $reason) {
            // Refund locked funds back to available balance in ledger: DEBIT user_locked, CREDIT user_available
            $this->ledgerService->rejectPayoutRefund(
                user: $payout->user,
                amount: $payout->points_amount,
                payoutUuid: $payout->uuid,
                reason: $reason
            );

            $payout->update([
                'status' => PayoutRequest::STATUS_REJECTED,
                'reviewed_by_id' => $reviewer->id,
                'reviewed_at' => now(),
                'admin_notes' => $reason,
            ]);

            return $payout->fresh();
        });
    }
}
