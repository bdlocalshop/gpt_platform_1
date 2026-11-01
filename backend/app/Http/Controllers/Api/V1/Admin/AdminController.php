<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudFlag;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\Payouts\PayoutService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PayoutService $payoutService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Get platform overview metrics, financial volume, and reconciliation state.
     */
    public function stats(): JsonResponse
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $suspendedUsers = User::where('status', 'suspended')->count();
        $bannedUsers = User::where('status', 'banned')->count();

        $pendingPayouts = PayoutRequest::where('status', PayoutRequest::STATUS_PENDING);
        $pendingCount = $pendingPayouts->count();
        $pendingUsd = $pendingPayouts->sum('usd_amount');

        $completedPayouts = PayoutRequest::where('status', PayoutRequest::STATUS_COMPLETED);
        $completedCount = $completedPayouts->count();
        $completedUsd = $completedPayouts->sum('usd_amount');

        $unresolvedFraudFlags = FraudFlag::where('is_resolved', false)->count();

        // System double-entry reconciliation
        $reconciliation = $this->ledgerService->reconcileSystem();

        return $this->success([
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'suspended' => $suspendedUsers,
                'banned' => $bannedUsers,
            ],
            'payouts' => [
                'pending_count' => $pendingCount,
                'pending_usd' => number_format((float) ($pendingUsd ?? 0), 2, '.', ''),
                'completed_count' => $completedCount,
                'completed_usd' => number_format((float) ($completedUsd ?? 0), 2, '.', ''),
            ],
            'security' => [
                'unresolved_fraud_flags' => $unresolvedFraudFlags,
            ],
            'ledger_integrity' => [
                'is_reconciled' => $reconciliation['is_reconciled'],
                'total_debits' => $reconciliation['total_debits'],
                'total_credits' => $reconciliation['total_credits'],
                'discrepancy' => $reconciliation['discrepancy'],
            ],
        ]);
    }

    /**
     * List payouts with review queue filters.
     */
    public function payouts(Request $request): JsonResponse
    {
        $query = PayoutRequest::with([
            'user:id,username,email,status',
            'user.fraudFlags' => fn ($q) => $q->where('is_resolved', false),
            'reviewer:id,username',
        ])->orderBy('id', 'desc');

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $payouts = $query->paginate(20);

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

    /**
     * Approve and disburse a pending payout.
     */
    public function approvePayout(Request $request, int $id): JsonResponse
    {
        $payout = PayoutRequest::find($id);
        if (!$payout) {
            return $this->error('Payout request not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $notes = $request->input('notes');
            $updated = $this->payoutService->approvePayout($payout, $request->user(), $notes);

            return $this->success([
                'id' => $updated->id,
                'uuid' => $updated->uuid,
                'status' => $updated->status,
                'tx_hash' => $updated->tx_hash,
                'reviewed_at' => $updated->reviewed_at?->toIso8601String(),
            ], 'Payout approved and cryptocurrency disbursement settled.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return $this->error('Failed to approve payout: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reject a pending payout and refund points back to available balance.
     */
    public function rejectPayout(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $payout = PayoutRequest::find($id);
        if (!$payout) {
            return $this->error('Payout request not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $updated = $this->payoutService->rejectPayout($payout, $request->user(), $validated['reason']);

            return $this->success([
                'id' => $updated->id,
                'uuid' => $updated->uuid,
                'status' => $updated->status,
                'admin_notes' => $updated->admin_notes,
                'reviewed_at' => $updated->reviewed_at?->toIso8601String(),
            ], 'Payout rejected and locked points refunded to user available balance.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return $this->error('Failed to reject payout: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List platform users with balances and fraud audit metadata.
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::withCount([
            'fraudFlags as unresolved_fraud_count' => fn ($q) => $q->where('is_resolved', false),
        ])->orderBy('id', 'desc');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $users = $query->paginate(20);

        $transformed = $users->getCollection()->map(function (User $user) {
            $available = $this->ledgerService->getUserAvailableBalance($user);
            $locked = $this->ledgerService->getUserLockedBalance($user);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'points_available' => $available,
                'points_locked' => $locked,
                'is_email_verified' => $user->isEmailVerified(),
                'registration_ip' => $user->registration_ip,
                'unresolved_fraud_count' => $user->unresolved_fraud_count,
                'created_at' => $user->created_at->toIso8601String(),
            ];
        });

        return $this->success([
            'users' => $transformed,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Update user account status (active, suspended, banned).
     */
    public function updateUserStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,suspended,banned'],
        ]);

        $user = User::find($id);
        if (!$user) {
            return $this->error('User not found.', Response::HTTP_NOT_FOUND);
        }

        $user->update(['status' => $validated['status']]);

        // Revoke tokens if account is banned or suspended
        if (in_array($validated['status'], ['banned', 'suspended'])) {
            $user->tokens()->delete();
        }

        return $this->success([
            'id' => $user->id,
            'username' => $user->username,
            'status' => $user->status,
        ], "User account status updated to [{$user->status}].");
    }

    /**
     * List security & anti-fraud flags.
     */
    public function fraudFlags(Request $request): JsonResponse
    {
        $query = FraudFlag::with(['user:id,username,email,status', 'resolvedBy:id,username'])
            ->orderBy('is_resolved', 'asc')
            ->orderBy('id', 'desc');

        if ($request->has('unresolved_only') && $request->boolean('unresolved_only')) {
            $query->where('is_resolved', false);
        }

        $flags = $query->paginate(25);

        return $this->success([
            'fraud_flags' => $flags->items(),
            'pagination' => [
                'current_page' => $flags->currentPage(),
                'last_page' => $flags->lastPage(),
                'per_page' => $flags->perPage(),
                'total' => $flags->total(),
            ],
        ]);
    }

    /**
     * Resolve a fraud flag.
     */
    public function resolveFraudFlag(Request $request, int $id): JsonResponse
    {
        $flag = FraudFlag::find($id);
        if (!$flag) {
            return $this->error('Fraud flag not found.', Response::HTTP_NOT_FOUND);
        }

        $flag->update([
            'is_resolved' => true,
            'resolved_by_id' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return $this->success([
            'id' => $flag->id,
            'is_resolved' => true,
            'resolved_at' => $flag->resolved_at->toIso8601String(),
        ], 'Fraud flag resolved successfully.');
    }
}
