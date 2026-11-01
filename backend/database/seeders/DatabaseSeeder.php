<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with system operational accounts and demo users.
     */
    public function run(): void
    {
        $ledgerService = app(LedgerService::class);

        // 1. Create or ensure platform operational system accounts
        $ledgerService->getOrCreateSystemAccount(Account::TYPE_PLATFORM_LIABILITY);
        $ledgerService->getOrCreateSystemAccount(Account::TYPE_PLATFORM_REVENUE);
        $ledgerService->getOrCreateSystemAccount(Account::TYPE_PLATFORM_RESERVE);

        // 2. Seed Administrator Account
        $admin = User::firstOrCreate([
            'email' => 'admin@gptplatform.com',
        ], [
            'name' => 'Platform Administrator',
            'username' => 'admin',
            'password' => Hash::make('AdminPassword123!'),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'referral_code' => 'ADMINVIP',
            'registration_ip' => '127.0.0.1',
            'registration_fingerprint' => 'admin_seed_fp',
        ]);

        // 3. Seed Demo User Account
        $user = User::firstOrCreate([
            'email' => 'demo@gptplatform.com',
        ], [
            'name' => 'Demo User',
            'username' => 'demouser',
            'password' => Hash::make('Password123!'),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => now(),
            'referral_code' => 'DEMO2026',
            'referred_by_id' => $admin->id,
            'registration_ip' => '127.0.0.1',
            'registration_fingerprint' => 'demo_seed_fp',
        ]);

        // 4. Fund demo user via double-entry ledger
        $userAvailableAccount = $ledgerService->getOrCreateUserAccount($user, Account::TYPE_USER_AVAILABLE);
        if (bccomp($userAvailableAccount->balance, '0.0000', 4) === 0) {
            // Credit Signup Bonus (100.0000 PTS = $0.10 USD)
            $ledgerService->creditSignupBonus($user, '100.0000', 'Welcome signup bonus');

            // Credit Sample Completed Offer Reward (15,000.0000 PTS = $15.00 USD)
            $ledgerService->creditOfferReward(
                user: $user,
                amount: '15000.0000',
                provider: 'bitlabs',
                externalTxId: 'seed_init_reward',
                description: 'BitLabs Welcome Survey Completion Reward'
            );
        }

        // Verify ledger integrity
        $ledgerService->reconcileSystem();
    }
}
