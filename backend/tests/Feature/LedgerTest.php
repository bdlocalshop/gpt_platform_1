<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger\Exceptions\InsufficientBalanceException;
use App\Services\Ledger\Exceptions\UnbalancedTransactionException;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledgerService = app(LedgerService::class);
    }

    private function createTestUser(string $username = 'ledgeruser', string $email = 'ledger@example.com'): User
    {
        return User::create([
            'name' => 'Ledger Tester',
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('password123'),
            'referral_code' => strtoupper(Str::random(8)),
            'status' => 'active',
        ]);
    }

    public function test_credit_offer_reward_creates_balanced_ledger_entries(): void
    {
        $user = $this->createTestUser();

        $tx = $this->ledgerService->creditOfferReward(
            user: $user,
            amount: '1500.2500',
            provider: 'bitlabs',
            externalTxId: 'bl_tx_99812',
            description: 'Completed consumer opinion survey #99812'
        );

        $this->assertEquals(Transaction::STATUS_COMPLETED, $tx->status);
        $this->assertEquals(Transaction::TYPE_OFFER_REWARD, $tx->type);

        // Check user available balance
        $availableBalance = $this->ledgerService->getUserAvailableBalance($user);
        $this->assertEquals('1500.2500', $availableBalance);

        // Check platform liability balance
        $liabilityAccount = $this->ledgerService->getOrCreateSystemAccount(Account::TYPE_PLATFORM_LIABILITY);
        $this->assertEquals('1500.2500', $liabilityAccount->balance);

        // Verify ledger entries
        $this->assertDatabaseCount('ledger_entries', 2);

        $debitEntry = LedgerEntry::where('direction', LedgerEntry::DIRECTION_DEBIT)->first();
        $creditEntry = LedgerEntry::where('direction', LedgerEntry::DIRECTION_CREDIT)->first();

        $this->assertEquals($liabilityAccount->id, $debitEntry->account_id);
        $this->assertEquals('1500.2500', $debitEntry->amount);

        $this->assertEquals($this->ledgerService->getOrCreateUserAccount($user, Account::TYPE_USER_AVAILABLE)->id, $creditEntry->account_id);
        $this->assertEquals('1500.2500', $creditEntry->amount);
    }

    public function test_payout_locking_moves_balance_from_available_to_locked(): void
    {
        $user = $this->createTestUser();

        // 1. Credit 5,000 points
        $this->ledgerService->creditOfferReward(
            user: $user,
            amount: '5000.0000',
            provider: 'cpx',
            externalTxId: 'cpx_tx_001'
        );

        $this->assertEquals('5000.0000', $this->ledgerService->getUserAvailableBalance($user));
        $this->assertEquals('0.0000', $this->ledgerService->getUserLockedBalance($user));

        // 2. Lock 3,000 points for payout
        $payoutUuid = (string) Str::uuid();
        $lockTx = $this->ledgerService->lockBalanceForPayout(
            user: $user,
            amount: '3000.0000',
            payoutUuid: $payoutUuid
        );

        $this->assertEquals(Transaction::TYPE_PAYOUT_LOCK, $lockTx->type);

        // Available balance should now be 2,000, and locked balance 3,000
        $this->assertEquals('2000.0000', $this->ledgerService->getUserAvailableBalance($user));
        $this->assertEquals('3000.0000', $this->ledgerService->getUserLockedBalance($user));
    }

    public function test_insufficient_balance_prevents_double_spend(): void
    {
        $user = $this->createTestUser();

        // Credit 2,000 points
        $this->ledgerService->creditOfferReward(
            user: $user,
            amount: '2000.0000',
            provider: 'offerwall_gg',
            externalTxId: 'ow_882'
        );

        $payout1 = (string) Str::uuid();
        $payout2 = (string) Str::uuid();

        // Lock 1,500 points (succeeds)
        $this->ledgerService->lockBalanceForPayout($user, '1500.0000', $payout1);
        $this->assertEquals('500.0000', $this->ledgerService->getUserAvailableBalance($user));

        // Attempting to lock 1,000 points should fail because only 500 is available
        $this->expectException(InsufficientBalanceException::class);
        $this->ledgerService->lockBalanceForPayout($user, '1000.0000', $payout2);

        // Assert balance did not change
        $this->assertEquals('500.0000', $this->ledgerService->getUserAvailableBalance($user));
    }

    public function test_settle_payout_disbursement_and_reject_refund(): void
    {
        $user = $this->createTestUser();

        // Credit 10,000 points
        $this->ledgerService->creditOfferReward($user, '10000.0000', 'offerwall_com', 'owc_111');

        // Request 1: Lock 4,000 points and settle disbursement
        $payout1 = (string) Str::uuid();
        $this->ledgerService->lockBalanceForPayout($user, '4000.0000', $payout1);
        $this->ledgerService->settlePayoutDisbursement($user, '4000.0000', $payout1);

        $this->assertEquals('6000.0000', $this->ledgerService->getUserAvailableBalance($user));
        $this->assertEquals('0.0000', $this->ledgerService->getUserLockedBalance($user));

        $reserveAccount = $this->ledgerService->getOrCreateSystemAccount(Account::TYPE_PLATFORM_RESERVE);
        $this->assertEquals('4000.0000', $reserveAccount->balance);

        // Request 2: Lock 3,000 points and reject/refund
        $payout2 = (string) Str::uuid();
        $this->ledgerService->lockBalanceForPayout($user, '3000.0000', $payout2);
        $this->assertEquals('3000.0000', $this->ledgerService->getUserAvailableBalance($user));
        $this->assertEquals('3000.0000', $this->ledgerService->getUserLockedBalance($user));

        $this->ledgerService->rejectPayoutRefund($user, '3000.0000', $payout2, 'Invalid crypto wallet address format');

        // Available balance is restored to 6,000 points
        $this->assertEquals('6000.0000', $this->ledgerService->getUserAvailableBalance($user));
        $this->assertEquals('0.0000', $this->ledgerService->getUserLockedBalance($user));
    }

    public function test_clawback_chargeback_allows_negative_balance_and_suspends_user(): void
    {
        $user = $this->createTestUser();

        // User earned 200 points
        $this->ledgerService->creditOfferReward($user, '200.0000', 'bitlabs', 'fraudulent_tx_1');
        $this->assertEquals('200.0000', $this->ledgerService->getUserAvailableBalance($user));
        $this->assertEquals('active', $user->fresh()->status);

        // Network issues a clawback of 500 points (exceeding balance)
        $this->ledgerService->clawbackChargeback(
            user: $user,
            amount: '500.0000',
            provider: 'bitlabs',
            externalTxId: 'fraudulent_tx_1',
            description: 'Reversal: Chargeback flagged by provider'
        );

        // Balance should go negative: 200 - 500 = -300.0000
        $this->assertEquals('-300.0000', $this->ledgerService->getUserAvailableBalance($user));

        // User account status must be automatically placed on hold/suspended
        $this->assertEquals('suspended', $user->fresh()->status);
    }

    public function test_unbalanced_transactions_are_strictly_rejected(): void
    {
        $user = $this->createTestUser();
        $userAcc = $this->ledgerService->getOrCreateUserAccount($user, Account::TYPE_USER_AVAILABLE);
        $liabilityAcc = $this->ledgerService->getOrCreateSystemAccount(Account::TYPE_PLATFORM_LIABILITY);

        $this->expectException(UnbalancedTransactionException::class);

        // Intentionally create an unbalanced transaction: Debit 100 vs Credit 90
        $this->ledgerService->recordDoubleEntryTransaction(
            type: Transaction::TYPE_ADJUSTMENT,
            entries: [
                [
                    'account' => $liabilityAcc,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount' => '100.0000',
                ],
                [
                    'account' => $userAcc,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount' => '90.0000',
                ],
            ]
        );
    }

    public function test_ledger_entries_are_strictly_immutable(): void
    {
        $user = $this->createTestUser();
        $this->ledgerService->creditOfferReward($user, '500.0000', 'bitlabs', 'tx_immutability');

        /** @var LedgerEntry $entry */
        $entry = LedgerEntry::first();

        // Attempting to mutate amount should fail
        $this->expectException(RuntimeException::class);
        $entry->update(['amount' => '9999.0000']);
    }

    public function test_system_reconciliation_verifies_total_debits_equal_total_credits(): void
    {
        $alice = $this->createTestUser('alice_ledger', 'alice_ledger@example.com');
        $bob = $this->createTestUser('bob_ledger', 'bob_ledger@example.com');

        // Various operations
        $this->ledgerService->creditSignupBonus($alice, '100.0000');
        $this->ledgerService->creditOfferReward($alice, '2500.0000', 'bitlabs', 'bl_1');
        $this->ledgerService->creditReferralBonus($bob, $alice, '500.0000');

        $payoutUuid = (string) Str::uuid();
        $this->ledgerService->lockBalanceForPayout($alice, '2000.0000', $payoutUuid);
        $this->ledgerService->settlePayoutDisbursement($alice, '2000.0000', $payoutUuid);

        $reconciliation = $this->ledgerService->reconcileSystem();

        $this->assertTrue($reconciliation['is_balanced']);
        $this->assertEquals('0.0000', $reconciliation['discrepancy']);
        $this->assertEquals($reconciliation['total_debits'], $reconciliation['total_credits']);
    }

    public function test_concurrency_race_condition_simulation(): void
    {
        $user = $this->createTestUser('race_user', 'race@example.com');

        // User starts with 1,000 points
        $this->ledgerService->creditOfferReward($user, '1000.0000', 'bitlabs', 'tx_race');
        $this->assertEquals('1000.0000', $this->ledgerService->getUserAvailableBalance($user));

        $successfulLocks = 0;
        $failedLocks = 0;

        // Simulate two sequential withdrawal attempts of 800 points each
        // In an un-locked system, both might pass reading balance = 1000.
        // With pessimistic row locking (lockForUpdate), the second one MUST see remaining balance = 200 and fail.
        for ($i = 0; $i < 2; $i++) {
            try {
                DB::transaction(function () use ($user, &$successfulLocks, $i) {
                    $this->ledgerService->lockBalanceForPayout(
                        user: $user,
                        amount: '800.0000',
                        payoutUuid: (string) Str::uuid(),
                        description: "Concurrent attempt #{$i}"
                    );
                    $successfulLocks++;
                });
            } catch (InsufficientBalanceException) {
                $failedLocks++;
            }
        }

        $this->assertEquals(1, $successfulLocks, 'Only 1 withdrawal attempt should succeed.');
        $this->assertEquals(1, $failedLocks, 'The 2nd double-spend withdrawal attempt must be rejected.');

        // Available balance must be exactly 200.0000, locked balance 800.0000
        $this->assertEquals('200.0000', $this->ledgerService->getUserAvailableBalance($user));
        $this->assertEquals('800.0000', $this->ledgerService->getUserLockedBalance($user));
    }

    public function test_wallet_balance_api_endpoint(): void
    {
        $user = $this->createTestUser('wallet_api_user', 'wallet_api@example.com');
        $token = $user->createToken('wallet_token')->plainTextToken;

        // Credit 2,500 points
        $this->ledgerService->creditOfferReward($user, '2500.0000', 'cpx', 'tx_api_01');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/wallet/balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'currency' => 'PTS',
                    'points' => [
                        'available' => '2500.0000',
                        'locked' => '0.0000',
                        'total' => '2500.0000',
                    ],
                    'fiat_equivalent' => [
                        'currency' => 'USD',
                        'available' => '2.5000',
                        'locked' => '0.0000',
                        'total' => '2.5000',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'recent_ledger_entries',
                ],
            ]);
    }
}
