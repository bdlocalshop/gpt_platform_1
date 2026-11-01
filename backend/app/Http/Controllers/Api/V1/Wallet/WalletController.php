<?php

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Get the authenticated user's current wallet balances, breakdown, and exchange rates.
     */
    public function balance(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $available = $this->ledgerService->getUserAvailableBalance($user);
        $locked = $this->ledgerService->getUserLockedBalance($user);

        // 1,000 Points = $1.00 USD (1 Point = 0.001 USD)
        $usdAvailable = bcdiv($available, '1000', 4);
        $usdLocked = bcdiv($locked, '1000', 4);
        $totalPoints = bcadd($available, $locked, 4);
        $usdTotal = bcdiv($totalPoints, '1000', 4);

        $availableAccount = $this->ledgerService->getOrCreateUserAccount($user, Account::TYPE_USER_AVAILABLE);
        $lockedAccount = $this->ledgerService->getOrCreateUserAccount($user, Account::TYPE_USER_LOCKED);

        // Calculate lifetime earnings (credits on user_available for reward/bonus types)
        $lifetimeEarned = LedgerEntry::where('account_id', $availableAccount->id)
            ->where('direction', LedgerEntry::DIRECTION_CREDIT)
            ->whereHas('transaction', function ($q) {
                $q->whereIn('type', [
                    Transaction::TYPE_OFFER_REWARD,
                    Transaction::TYPE_SIGNUP_BONUS,
                    Transaction::TYPE_REFERRAL_BONUS,
                ]);
            })
            ->sum('amount');

        // Calculate total withdrawn
        $totalWithdrawn = LedgerEntry::where('account_id', $lockedAccount->id)
            ->where('direction', LedgerEntry::DIRECTION_DEBIT)
            ->whereHas('transaction', function ($q) {
                $q->where('type', Transaction::TYPE_PAYOUT_COMPLETE);
            })
            ->sum('amount');

        // Recent 10 entries for quick summary
        $recentEntries = LedgerEntry::whereIn('account_id', [$availableAccount->id, $lockedAccount->id])
            ->with(['transaction:id,uuid,type,reference_type,reference_id,description,created_at'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function (LedgerEntry $entry) {
                return [
                    'id' => $entry->id,
                    'direction' => $entry->direction,
                    'amount' => $entry->amount,
                    'balance_after' => $entry->balance_after,
                    'type' => $entry->transaction?->type,
                    'description' => $entry->transaction?->description,
                    'created_at' => $entry->created_at->toIso8601String(),
                ];
            });

        return $this->success([
            'currency' => 'PTS',
            'points' => [
                'available' => $available,
                'locked' => $locked,
                'total' => $totalPoints,
                'lifetime_earned' => number_format((float) ($lifetimeEarned ?? 0), 4, '.', ''),
                'lifetime_withdrawn' => number_format((float) ($totalWithdrawn ?? 0), 4, '.', ''),
            ],
            'fiat_equivalent' => [
                'currency' => 'USD',
                'rate' => '0.001',
                'available' => $usdAvailable,
                'locked' => $usdLocked,
                'total' => $usdTotal,
                'lifetime_earned' => number_format(((float) ($lifetimeEarned ?? 0)) * 0.001, 2, '.', ''),
                'lifetime_withdrawn' => number_format(((float) ($totalWithdrawn ?? 0)) * 0.001, 2, '.', ''),
            ],
            'recent_ledger_entries' => $recentEntries,
        ]);
    }

    /**
     * Get paginated ledger transaction history with filtering.
     */
    public function transactions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $availableAccount = $this->ledgerService->getOrCreateUserAccount($user, Account::TYPE_USER_AVAILABLE);
        $lockedAccount = $this->ledgerService->getOrCreateUserAccount($user, Account::TYPE_USER_LOCKED);

        $query = LedgerEntry::whereIn('account_id', [$availableAccount->id, $lockedAccount->id])
            ->with(['account:id,type', 'transaction:id,uuid,type,reference_type,reference_id,description,metadata,created_at'])
            ->orderBy('id', 'desc');

        // Optional filter by transaction type
        if ($type = $request->query('type')) {
            $query->whereHas('transaction', function ($q) use ($type) {
                $q->where('type', $type);
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $entries = $query->paginate($perPage);

        $transformed = $entries->getCollection()->map(function (LedgerEntry $entry) {
            $amountPoints = (float) $entry->amount;
            $usdAmount = number_format($amountPoints * 0.001, 4, '.', '');

            return [
                'id' => $entry->id,
                'account_type' => $entry->account?->type,
                'direction' => $entry->direction,
                'amount' => $entry->amount,
                'amount_usd' => $usdAmount,
                'balance_after' => $entry->balance_after,
                'transaction' => [
                    'uuid' => $entry->transaction?->uuid,
                    'type' => $entry->transaction?->type,
                    'description' => $entry->transaction?->description,
                    'reference_type' => $entry->transaction?->reference_type,
                    'reference_id' => $entry->transaction?->reference_id,
                    'metadata' => $entry->transaction?->metadata,
                    'created_at' => $entry->transaction?->created_at?->toIso8601String(),
                ],
                'created_at' => $entry->created_at->toIso8601String(),
            ];
        });

        return $this->success([
            'transactions' => $transformed,
            'pagination' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }
}
