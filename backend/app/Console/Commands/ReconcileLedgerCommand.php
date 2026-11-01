<?php

namespace App\Console\Commands;

use App\Services\Ledger\LedgerService;
use Illuminate\Console\Command;

class ReconcileLedgerCommand extends Command
{
    protected $signature = 'ledger:reconcile';
    protected $description = 'Perform automated system-wide double-entry ledger reconciliation';

    public function handle(LedgerService $ledgerService): int
    {
        $this->info('Starting double-entry ledger reconciliation...');

        $result = $ledgerService->reconcileSystem();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Balanced State', $result['is_balanced'] ? '✓ BALANCED' : '✗ DISCREPANCY DETECTED'],
                ['Total System Debits', $result['total_debits'] . ' PTS'],
                ['Total System Credits', $result['total_credits'] . ' PTS'],
                ['Net Discrepancy', $result['discrepancy'] . ' PTS'],
                ['Reconciled At', $result['reconciled_at']],
            ]
        );

        if (!$result['is_balanced']) {
            $this->error('CRITICAL: Total debits do not match total credits! Financial integrity compromised.');
            return Command::FAILURE;
        }

        $this->info('Ledger integrity verified successfully: All debits strictly equal all credits.');
        return Command::SUCCESS;
    }
}
