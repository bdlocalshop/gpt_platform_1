<?php

namespace App\Services\Postbacks;

use App\Models\Postback;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Postbacks\SignatureValidators\BitLabsSignatureValidator;
use App\Services\Postbacks\SignatureValidators\CpxSignatureValidator;
use App\Services\Postbacks\SignatureValidators\OfferwallComSignatureValidator;
use App\Services\Postbacks\SignatureValidators\OfferwallGgSignatureValidator;
use App\Services\Postbacks\SignatureValidators\SignatureValidatorInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostbackService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Process an incoming postback request for a specific provider.
     *
     * @return array{success: bool, status_code: int, response_data: mixed}
     */
    public function handle(string $provider, Request $request): array
    {
        $validator = $this->resolveValidator($provider);

        // 1. Signature validation
        if (!$validator || !$validator->validate($request)) {
            Log::warning("Postback signature failed for provider [{$provider}]", [
                'ip' => $request->ip(),
                'provider' => $provider,
                'payload' => $request->all(),
            ]);

            return [
                'success' => false,
                'status_code' => 403,
                'response_data' => [
                    'status' => 'error',
                    'message' => 'Invalid signature.',
                ],
            ];
        }

        // 2. Normalize payload
        $normalized = $this->normalizePayload($provider, $request);
        $externalTxId = $normalized['external_transaction_id'];
        $userId = $normalized['user_id'];
        $amount = $normalized['amount'];
        $isReversal = $normalized['is_reversal'];

        if (empty($externalTxId) || empty($userId) || bccomp((string) $amount, '0.0000', 4) <= 0) {
            return [
                'success' => false,
                'status_code' => 422,
                'response_data' => [
                    'status' => 'error',
                    'message' => 'Missing or invalid required postback parameters.',
                ],
            ];
        }

        // 3. Enforce Idempotency First
        $existing = Postback::where('provider', $provider)
            ->where('external_transaction_id', $externalTxId)
            ->first();

        if ($existing) {
            Log::info("Idempotent postback replay detected for provider [{$provider}:{$externalTxId}]");

            return [
                'success' => true,
                'status_code' => 200,
                'response_data' => $this->formatSuccessResponse($provider, 'duplicate'),
            ];
        }

        // 4. Verify User
        $user = User::find($userId);
        if (!$user) {
            Log::error("User [{$userId}] not found for postback [{$provider}:{$externalTxId}]");

            return [
                'success' => false,
                'status_code' => 404,
                'response_data' => [
                    'status' => 'error',
                    'message' => "User [{$userId}] not found.",
                ],
            ];
        }

        // 5. Execute Financial Ledger Mutation
        try {
            $ip = $request->header('X-Simulate-Ip') ?? $request->ip() ?? '127.0.0.1';

            $result = DB::transaction(function () use ($provider, $externalTxId, $user, $amount, $isReversal, $normalized, $ip, $request) {
                if ($isReversal) {
                    $transaction = $this->ledgerService->clawbackChargeback(
                        user: $user,
                        amount: (string) $amount,
                        provider: $provider,
                        externalTxId: $externalTxId,
                        description: "Offer reversal from {$provider} ({$externalTxId})"
                    );
                    $status = Postback::STATUS_REVERSED;
                } else {
                    $transaction = $this->ledgerService->creditOfferReward(
                        user: $user,
                        amount: (string) $amount,
                        provider: $provider,
                        externalTxId: $externalTxId,
                        description: "Offer reward from {$provider} ({$externalTxId})",
                        metadata: ['source' => 's2s_postback']
                    );
                    $status = Postback::STATUS_CREDITED;
                }

                $postback = Postback::create([
                    'provider' => $provider,
                    'external_transaction_id' => $externalTxId,
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $amount,
                    'status' => $status,
                    'is_reversal' => $isReversal,
                    'signature' => $normalized['signature'],
                    'ip_address' => $ip,
                    'raw_payload' => $request->all(),
                ]);

                // Check for referral maturation if this was a valid credit
                if (!$isReversal && $user->referred_by_id) {
                    $this->checkAndAwardReferralBonus($user);
                }

                return $postback;
            });

            return [
                'success' => true,
                'status_code' => 200,
                'response_data' => $this->formatSuccessResponse($provider, 'processed'),
            ];
        } catch (\Throwable $e) {
            Log::error("Failed to process postback [{$provider}:{$externalTxId}]: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'response_data' => [
                    'status' => 'error',
                    'message' => 'Internal processing error: ' . $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check if referee has completed at least 1,000 points in verified offer conversions,
     * and award referral bonus if not already awarded.
     */
    protected function checkAndAwardReferralBonus(User $user): void
    {
        $referrerId = $user->referred_by_id;
        if (!$referrerId) {
            return;
        }

        // Check if referral bonus already paid for this user
        $alreadyAwarded = Transaction::where('type', Transaction::TYPE_REFERRAL_BONUS)
            ->where('reference_id', "referrer:{$referrerId}_referred:{$user->id}")
            ->exists();

        if ($alreadyAwarded) {
            return;
        }

        // Sum completed offer credits
        $totalEarned = Postback::where('user_id', $user->id)
            ->where('status', Postback::STATUS_CREDITED)
            ->where('is_reversal', false)
            ->sum('amount');

        if (bccomp((string) $totalEarned, '1000.0000', 4) >= 0) {
            $this->ledgerService->creditReferralBonus(
                referrer: $referrerId,
                referred: $user->id,
                amount: '500.0000'
            );

            Log::info("Awarded 500.0000 referral bonus to user [{$referrerId}] for referee [{$user->id}]");
        }
    }

    /**
     * Normalize provider specific parameters.
     */
    protected function normalizePayload(string $provider, Request $request): array
    {
        $signature = $request->header('X-Bitlabs-Signature')
            ?? $request->header('X-CPX-Signature')
            ?? $request->header('X-Offerwall-Signature')
            ?? $request->header('X-Signature')
            ?? $request->input('hash')
            ?? $request->input('signature');

        switch ($provider) {
            case 'bitlabs':
                $tx = (string) ($request->input('tx') ?? $request->input('trans_id') ?? $request->input('external_transaction_id'));
                $uid = (string) ($request->input('uid') ?? $request->input('user_id'));
                $amount = (float) ($request->input('val') ?? $request->input('amount') ?? 0);
                $type = strtoupper((string) $request->input('type', 'COMPLETE'));
                $isReversal = ($type === 'RECONCILIATION') || ($amount < 0) || ((int) $request->input('status', 1) < 0);
                return [
                    'external_transaction_id' => $tx,
                    'user_id' => $uid,
                    'amount' => abs($amount),
                    'is_reversal' => $isReversal,
                    'signature' => $signature,
                ];

            case 'cpx':
                $tx = (string) ($request->input('trans_id') ?? $request->input('tx'));
                $uid = (string) ($request->input('user_id') ?? $request->input('uid'));
                $amount = (float) ($request->input('amount_local') ?? $request->input('amount') ?? 0);
                $status = (int) $request->input('status', 1);
                $isReversal = ($status === 2) || ($status < 0) || ($amount < 0);
                return [
                    'external_transaction_id' => $tx,
                    'user_id' => $uid,
                    'amount' => abs($amount),
                    'is_reversal' => $isReversal,
                    'signature' => $signature,
                ];

            case 'offerwall_gg':
                $tx = (string) ($request->input('transaction_id') ?? $request->input('trans_id') ?? $request->input('tx'));
                $uid = (string) ($request->input('user_id') ?? $request->input('uid'));
                $amount = (float) ($request->input('amount') ?? $request->input('payout') ?? 0);
                $type = strtolower((string) $request->input('type', 'credit'));
                $isReversal = ($type === 'reversal') || ((int) $request->input('status', 1) < 0) || ($amount < 0);
                return [
                    'external_transaction_id' => $tx,
                    'user_id' => $uid,
                    'amount' => abs($amount),
                    'is_reversal' => $isReversal,
                    'signature' => $signature,
                ];

            case 'offerwall_com':
                $tx = (string) ($request->input('transId') ?? $request->input('transaction_id') ?? $request->input('tx'));
                $uid = (string) ($request->input('subId') ?? $request->input('user_id') ?? $request->input('uid'));
                $amount = (float) ($request->input('reward') ?? $request->input('amount') ?? 0);
                $status = (int) $request->input('status', 1);
                $type = strtolower((string) $request->input('type', 'credit'));
                $isReversal = ($status === -1) || ($status === 0) || ($type === 'reversal') || ($amount < 0);
                return [
                    'external_transaction_id' => $tx,
                    'user_id' => $uid,
                    'amount' => abs($amount),
                    'is_reversal' => $isReversal,
                    'signature' => $signature,
                ];

            default:
                return [
                    'external_transaction_id' => (string) $request->input('transaction_id'),
                    'user_id' => (string) $request->input('user_id'),
                    'amount' => abs((float) $request->input('amount', 0)),
                    'is_reversal' => (bool) $request->input('is_reversal', false),
                    'signature' => $signature,
                ];
        }
    }

    /**
     * Resolve validator instance for provider.
     */
    protected function resolveValidator(string $provider): ?SignatureValidatorInterface
    {
        return match ($provider) {
            'bitlabs' => new BitLabsSignatureValidator(),
            'cpx' => new CpxSignatureValidator(),
            'offerwall_gg' => new OfferwallGgSignatureValidator(),
            'offerwall_com' => new OfferwallComSignatureValidator(),
            default => null,
        };
    }

    /**
     * Format response matching provider network expectations.
     */
    protected function formatSuccessResponse(string $provider, string $action): mixed
    {
        return match ($provider) {
            'bitlabs' => ['status' => 'success'],
            'cpx' => 1,
            'offerwall_gg' => ['status' => 'ok', 'action' => $action],
            'offerwall_com' => ['status' => 'ok', 'result' => 1],
            default => ['success' => true, 'action' => $action],
        };
    }
}
