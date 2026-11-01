<?php

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
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
     * Get the authenticated user's current wallet balances and exchange rates.
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

        // Fetch recent ledger entry movements for this user's accounts
        $availableAccount = $this->ledgerService->getOrCreateUserAccount($user, 'user_available');
        $lockedAccount = $this->ledgerService->getOrCreateUserAccount($user, 'user_locked');

        $recentEntries = LedgerEntry::whereIn('account_id', [$availableAccount->id, $lockedAccount->id])
            ->with(['transaction:id,uuid,type,reference_type,description,created_at'])
            ->orderBy('id', 'desc')
            ->limit(15)
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
            ],
            'fiat_equivalent' => [
                'currency' => 'USD',
                'rate' => '0.001',
                'available' => $usdAvailable,
                'locked' => $usdLocked,
                'total' => $usdTotal,
            ],
            'recent_ledger_entries' => $recentEntries,
        ]);
    }
}
