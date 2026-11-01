<?php

namespace App\Services\Ledger;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger\Exceptions\AccountFrozenException;
use App\Services\Ledger\Exceptions\InsufficientBalanceException;
use App\Services\Ledger\Exceptions\UnbalancedTransactionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LedgerService
{
    public const SYSTEM_LIABILITY_CODE = 'system:platform_liability';
    public const SYSTEM_REVENUE_CODE = 'system:platform_revenue';
    public const SYSTEM_RESERVE_CODE = 'system:platform_reserve';

    /**
     * Get or create a user's operational account (user_available or user_locked).
     */
    public function getOrCreateUserAccount(User|int $user, string $type): Account
    {
        $userId = $user instanceof User ? $user->id : $user;

        if (!in_array($type, [Account::TYPE_USER_AVAILABLE, Account::TYPE_USER_LOCKED])) {
            throw new InvalidArgumentException("Invalid user account type: {$type}");
        }

        $code = "user:{$userId}:{$type}";

        return Account::firstOrCreate(
            ['code' => $code],
            [
                'user_id' => $userId,
                'type' => $type,
                'currency' => 'PTS',
                'balance' => '0.0000',
                'status' => 'active',
            ]
        );
    }

    /**
     * Get or create a platform operational account.
     */
    public function getOrCreateSystemAccount(string $type): Account
    {
        $codeMap = [
            Account::TYPE_PLATFORM_LIABILITY => self::SYSTEM_LIABILITY_CODE,
            Account::TYPE_PLATFORM_REVENUE => self::SYSTEM_REVENUE_CODE,
            Account::TYPE_PLATFORM_RESERVE => self::SYSTEM_RESERVE_CODE,
        ];

        if (!isset($codeMap[$type])) {
            throw new InvalidArgumentException("Invalid system account type: {$type}");
        }

        $code = $codeMap[$type];

        return Account::firstOrCreate(
            ['code' => $code],
            [
                'user_id' => null,
                'type' => $type,
                'currency' => 'PTS',
                'balance' => '0.0000',
                'status' => 'active',
            ]
        );
    }

    /**
     * Get current spendable balance for a user.
     */
    public function getUserAvailableBalance(User|int $user): string
    {
        $account = $this->getOrCreateUserAccount($user, Account::TYPE_USER_AVAILABLE);
        return $account->balance;
    }

    /**
     * Get current locked (in-review) balance for a user.
     */
    public function getUserLockedBalance(User|int $user): string
    {
        $account = $this->getOrCreateUserAccount($user, Account::TYPE_USER_LOCKED);
        return $account->balance;
    }

    /**
     * Record an offer reward from a postback.
     * Double entry:
     *   DEBIT: platform_liability
     *   CREDIT: user_available
     */
    public function creditOfferReward(
        User|int $user,
        string $amount,
        string $provider,
        string $externalTxId,
        ?string $description = null,
        array $metadata = []
    ): Transaction {
        $amount = $this->formatAmount($amount);

        return $this->recordDoubleEntryTransaction(
            type: Transaction::TYPE_OFFER_REWARD,
            entries: [
                [
                    'account' => $this->getOrCreateSystemAccount(Account::TYPE_PLATFORM_LIABILITY),
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount' => $amount,
                ],
                [
                    'account' => $this->getOrCreateUserAccount($user, Account::TYPE_USER_AVAILABLE),
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount' => $amount,
                ],
            ],
            referenceType: 'postback',
            referenceId: "{$provider}:{$externalTxId}",
            description: $description ?? "Reward from {$provider} task ({$externalTxId})",
            metadata: array_merge($metadata, [
                'provider' => $provider,
                'external_transaction_id' => $externalTxId,
            ])
        );
    }

    /**
     * Credit one-time signup bonus upon email verification.
     * Double entry:
     *   DEBIT: platform_liability
     *   CREDIT: user_available
     */
    public function creditSignupBonus(
        User|int $user,
        string $amount = '100.0000',
        ?string $description = null
    ): Transaction {
        $amount = $this->formatAmount($amount);
        $userId = $user instanceof User ? $user->id : $user;

        return $this->recordDoubleEntryTransaction(
            type: Transaction::TYPE_SIGNUP_BONUS,
            entries: [
                [
                    'account' => $this->getOrCreateSystemAccount(Account::TYPE_PLATFORM_LIABILITY),
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount' => $amount,
                ],
                [
                    'account' => $this->getOrCreateUserAccount($userId, Account::TYPE_USER_AVAILABLE),
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount' => $amount,
                ],
            ],
            referenceType: 'user',
            referenceId: (string) $userId,
            description: $description ?? 'Welcome bonus for email verification',
            metadata: ['bonus_type' => 'signup']
        );
    }

    /**
     * Credit referral bonus once the referee reaches the qualification threshold.
     * Double entry:
     *   DEBIT: platform_liability
     *   CREDIT: user_available (referrer)
     */
    public function creditReferralBonus(
        User|int $referrer,
        User|int $referred,
        string $amount = '500.0000'
    ): Transaction {
        $amount = $this->formatAmount($amount);
        $referrerId = $referrer instanceof User ? $referrer->id : $referrer;
        $referredId = $referred instanceof User ? $referred->id : $referred;

        return $this->recordDoubleEntryTransaction(
            type: Transaction::TYPE_REFERRAL_BONUS,
            entries: [
                [
                    'account' => $this->getOrCreateSystemAccount(Account::TYPE_PLATFORM_LIABILITY),
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount' => $amount,
                ],
                [
                    'account' => $this->getOrCreateUserAccount($referrerId, Account::TYPE_USER_AVAILABLE),
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount' => $amount,
                ],
            ],
            referenceType: 'referral',
            referenceId: "referrer:{$referrerId}_referred:{$referredId}",
            description: "Referral bonus for qualified invitee #{$referredId}",
            metadata: [
                'referrer_id' => $referrerId,
                'referred_id' => $referredId,
            ]
        );
    }

    /**
     * Lock user available balance when requesting a cashout.
     * Invariant: Available balance must be >= amount!
     * Double entry:
     *   DEBIT: user_available
     *   CREDIT: user_locked
     */
    public function lockBalanceForPayout(
        User|int $user,
        string $amount,
        string $payoutUuid,
        ?string $description = null
    ): Transaction {
        $amount = $this->formatAmount($amount);
        $availableAccount = $this->getOrCreateUserAccount($user, Account::TYPE_USER_AVAILABLE);
        $lockedAccount = $this->getOrCreateUserAccount($user, Account::TYPE_USER_LOCKED);

        return DB::transaction(function () use ($availableAccount, $lockedAccount, $amount, $payoutUuid, $description) {
            // Pessimistic lock on available account
            /** @var Account $lockedAvailable */
            $lockedAvailable = Account::where('id', $availableAccount->id)->lockForUpdate()->first();

            if (bccomp($lockedAvailable->balance, $amount, 4) < 0) {
                throw new InsufficientBalanceException(
                    "Insufficient available balance. Required: {$amount} PTS, Available: {$lockedAvailable->balance} PTS"
                );
            }

            return $this->recordDoubleEntryTransaction(
                type: Transaction::TYPE_PAYOUT_LOCK,
                entries: [
                    [
                        'account' => $lockedAvailable,
                        'direction' => LedgerEntry::DIRECTION_DEBIT,
                        'amount' => $amount,
                    ],
                    [
                        'account' => $lockedAccount,
                        'direction' => LedgerEntry::DIRECTION_CREDIT,
                        'amount' => $amount,
                    ],
                ],
                referenceType: 'payout_request',
                referenceId: $payoutUuid,
                description: $description ?? "Cashout hold for payout request {$payoutUuid}",
                metadata: ['payout_uuid' => $payoutUuid]
            );
        });
    }

    /**
     * Settle a completed payout disbursement.
     * Double entry:
     *   DEBIT: user_locked
     *   CREDIT: platform_reserve
     */
    public function settlePayoutDisbursement(
        User|int $user,
        string $amount,
        string $payoutUuid,
        ?string $description = null
    ): Transaction {
        $amount = $this->formatAmount($amount);
        $lockedAccount = $this->getOrCreateUserAccount($user, Account::TYPE_USER_LOCKED);
        $reserveAccount = $this->getOrCreateSystemAccount(Account::TYPE_PLATFORM_RESERVE);

        return $this->recordDoubleEntryTransaction(
            type: Transaction::TYPE_PAYOUT_COMPLETE,
            entries: [
                [
                    'account' => $lockedAccount,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount' => $amount,
                ],
                [
                    'account' => $reserveAccount,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount' => $amount,
                ],
            ],
            referenceType: 'payout_request',
            referenceId: $payoutUuid,
            description: $description ?? "Payout disbursement settled for {$payoutUuid}",
            metadata: ['payout_uuid' => $payoutUuid]
        );
    }

    /**
     * Refund locked points back to available balance when a cashout is rejected/cancelled.
     * Double entry:
     *   DEBIT: user_locked
     *   CREDIT: user_available
     */
    public function rejectPayoutRefund(
        User|int $user,
        string $amount,
        string $payoutUuid,
        ?string $reason = null
    ): Transaction {
        $amount = $this->formatAmount($amount);
        $lockedAccount = $this->getOrCreateUserAccount($user, Account::TYPE_USER_LOCKED);
        $availableAccount = $this->getOrCreateUserAccount($user, Account::TYPE_USER_AVAILABLE);

        return $this->recordDoubleEntryTransaction(
            type: Transaction::TYPE_PAYOUT_REJECT,
            entries: [
                [
                    'account' => $lockedAccount,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount' => $amount,
                ],
                [
                    'account' => $availableAccount,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount' => $amount,
                ],
            ],
            referenceType: 'payout_request',
            referenceId: $payoutUuid,
            description: "Payout {$payoutUuid} rejected: " . ($reason ?? 'Compliance review'),
            metadata: [
                'payout_uuid' => $payoutUuid,
                'rejection_reason' => $reason,
            ]
        );
    }

    /**
     * Reversal / Chargeback clawback for cancelled offer conversion.
     * Double entry:
     *   DEBIT: user_available
     *   CREDIT: platform_liability
     *
     * Invariant: As per RULES.md Section 2:
     * "If the user's available balance is insufficient, allow their balance to go negative
     * and place an automated hold on their account."
     */
    public function clawbackChargeback(
        User|int $user,
        string $amount,
        string $provider,
        string $externalTxId,
        ?string $description = null
    ): Transaction {
        $amount = $this->formatAmount($amount);
        $userId = $user instanceof User ? $user->id : $user;
        $availableAccount = $this->getOrCreateUserAccount($userId, Account::TYPE_USER_AVAILABLE);
        $liabilityAccount = $this->getOrCreateSystemAccount(Account::TYPE_PLATFORM_LIABILITY);

        $tx = $this->recordDoubleEntryTransaction(
            type: Transaction::TYPE_REVERSAL,
            entries: [
                [
                    'account' => $availableAccount,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount' => $amount,
                ],
                [
                    'account' => $liabilityAccount,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount' => $amount,
                ],
            ],
            referenceType: 'chargeback',
            referenceId: "{$provider}:{$externalTxId}",
            description: $description ?? "Clawback reversal from {$provider} ({$externalTxId})",
            metadata: [
                'provider' => $provider,
                'external_transaction_id' => $externalTxId,
            ]
        );

        // Check if user balance went negative; if so, flag user account
        $refreshed = Account::find($availableAccount->id);
        if ($refreshed && bccomp($refreshed->balance, '0.0000', 4) < 0) {
            User::where('id', $userId)->update(['status' => 'suspended']);
        }

        return $tx;
    }

    /**
     * Core atomic double-entry execution engine.
     * Enforces pessimistic locking, debits == credits validation, and immutability.
     */
    public function recordDoubleEntryTransaction(
        string $type,
        array $entries,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $description = null,
        array $metadata = []
    ): Transaction {
        if (count($entries) < 2) {
            throw new InvalidArgumentException('Double-entry transactions require at least 2 entries (debit and credit).');
        }

        // 1. Validate that Total Debits == Total Credits with DECIMAL(16, 4) precision
        $totalDebits = '0.0000';
        $totalCredits = '0.0000';

        foreach ($entries as $entry) {
            $amt = $this->formatAmount($entry['amount']);
            if (bccomp($amt, '0.0000', 4) <= 0) {
                throw new InvalidArgumentException('Ledger entry amount must be strictly positive.');
            }

            if ($entry['direction'] === LedgerEntry::DIRECTION_DEBIT) {
                $totalDebits = bcadd($totalDebits, $amt, 4);
            } elseif ($entry['direction'] === LedgerEntry::DIRECTION_CREDIT) {
                $totalCredits = bcadd($totalCredits, $amt, 4);
            } else {
                throw new InvalidArgumentException("Invalid ledger direction: {$entry['direction']}");
            }
        }

        if (bccomp($totalDebits, $totalCredits, 4) !== 0) {
            throw new UnbalancedTransactionException($totalDebits, $totalCredits);
        }

        // 2. Extract and sort unique account IDs to prevent deadlocks
        $accountMap = [];
        foreach ($entries as $entry) {
            $acc = $entry['account'];
            $id = $acc instanceof Account ? $acc->id : (int) $acc;
            $accountMap[$id] = true;
        }

        $sortedAccountIds = array_keys($accountMap);
        sort($sortedAccountIds, SORT_NUMERIC);

        // 3. Execute inside an atomic database transaction with pessimistic row locks
        return DB::transaction(function () use ($sortedAccountIds, $entries, $type, $referenceType, $referenceId, $description, $metadata) {
            // Lock all involved accounts in ascending order
            $lockedAccounts = Account::whereIn('id', $sortedAccountIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Verify accounts are active
            foreach ($lockedAccounts as $lockedAcc) {
                if ($lockedAcc->status !== 'active') {
                    throw new AccountFrozenException($lockedAcc->code);
                }
            }

            // Create Transaction record
            $transaction = Transaction::create([
                'uuid' => (string) Str::uuid(),
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            // Process each ledger entry and mutate account balances
            foreach ($entries as $entry) {
                $acc = $entry['account'];
                $accId = $acc instanceof Account ? $acc->id : (int) $acc;

                /** @var Account $account */
                $account = $lockedAccounts->get($accId);
                $amt = $this->formatAmount($entry['amount']);
                $direction = $entry['direction'];

                // Calculate new balance based on account type conventions:
                // User Accounts: CREDIT increases (+), DEBIT decreases (-)
                // Platform Liability: DEBIT increases (+), CREDIT decreases (-)
                // Platform Revenue / Reserve: CREDIT increases (+), DEBIT decreases (-)
                if ($account->type === Account::TYPE_PLATFORM_LIABILITY) {
                    $newBalance = $direction === LedgerEntry::DIRECTION_DEBIT
                        ? bcadd($account->balance, $amt, 4)
                        : bcsub($account->balance, $amt, 4);
                } else {
                    $newBalance = $direction === LedgerEntry::DIRECTION_CREDIT
                        ? bcadd($account->balance, $amt, 4)
                        : bcsub($account->balance, $amt, 4);
                }

                // Update account balance
                $account->update(['balance' => $newBalance]);

                // Create immutable ledger entry
                LedgerEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $account->id,
                    'direction' => $direction,
                    'amount' => $amt,
                    'balance_after' => $newBalance,
                    'created_at' => now(),
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Run system-wide ledger reconciliation.
     * Verifies that total debits across the entire ledger equal total credits.
     */
    public function reconcileSystem(): array
    {
        $totals = LedgerEntry::select(
            DB::raw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END) as total_debits"),
            DB::raw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END) as total_credits")
        )->first();

        $totalDebits = $this->formatAmount($totals->total_debits ?? '0');
        $totalCredits = $this->formatAmount($totals->total_credits ?? '0');
        $isBalanced = (bccomp($totalDebits, $totalCredits, 4) === 0);

        // Account balances summary
        $accountsSummary = Account::select('type', DB::raw('SUM(balance) as total_balance'))
            ->groupBy('type')
            ->pluck('total_balance', 'type')
            ->toArray();

        return [
            'is_balanced' => $isBalanced,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'discrepancy' => bcsub($totalDebits, $totalCredits, 4),
            'account_balances' => $accountsSummary,
            'reconciled_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Format any numeric input strictly to 4 decimal places without float corruption.
     */
    private function formatAmount(float|int|string $amount): string
    {
        if (is_numeric($amount)) {
            return number_format((float) $amount, 4, '.', '');
        }

        throw new InvalidArgumentException("Amount must be numeric: {$amount}");
    }
}
