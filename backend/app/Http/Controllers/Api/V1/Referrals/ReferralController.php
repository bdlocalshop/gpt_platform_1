<?php

namespace App\Http\Controllers\Api\V1\Referrals;

use App\Http\Controllers\Controller;
use App\Models\Postback;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    use ApiResponse;

    /**
     * Get the authenticated user's referral dashboard stats, share link, and referees list.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $referralLink = rtrim($frontendUrl, '/') . '/register?ref=' . $user->referral_code;

        // Fetch referred users
        $referees = User::where('referred_by_id', $user->id)
            ->select(['id', 'username', 'name', 'created_at'])
            ->orderBy('id', 'desc')
            ->get();

        $totalReferrals = $referees->count();
        $qualifiedCount = 0;

        $refereeList = $referees->map(function (User $referee) use ($user, &$qualifiedCount) {
            // Calculate referee verified offer credits from postbacks
            $verifiedPoints = Postback::where('user_id', $referee->id)
                ->where('status', Postback::STATUS_CREDITED)
                ->where('is_reversal', false)
                ->sum('amount');

            $verifiedPointsFloat = (float) $verifiedPoints;
            $isQualified = $verifiedPointsFloat >= 1000.0;
            if ($isQualified) {
                $qualifiedCount++;
            }

            // Check if referral bonus transaction exists for this referee
            $bonusAwarded = Transaction::where('type', Transaction::TYPE_REFERRAL_BONUS)
                ->where('reference_id', "referrer:{$user->id}_referred:{$referee->id}")
                ->exists();

            $progress = min(100, round(($verifiedPointsFloat / 1000.0) * 100));

            return [
                'id' => $referee->id,
                'username' => $referee->username,
                'joined_at' => $referee->created_at->toIso8601String(),
                'verified_points_earned' => number_format($verifiedPointsFloat, 4, '.', ''),
                'points_needed' => number_format(max(0, 1000.0 - $verifiedPointsFloat), 4, '.', ''),
                'progress_percentage' => $progress,
                'is_qualified' => $isQualified,
                'bonus_awarded' => $bonusAwarded,
            ];
        });

        // Calculate total referral bonus earned by user
        $totalEarnedPoints = \App\Models\LedgerEntry::whereHas('account', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('direction', \App\Models\LedgerEntry::DIRECTION_CREDIT)
            ->whereHas('transaction', function ($q) {
                $q->where('type', Transaction::TYPE_REFERRAL_BONUS);
            })
            ->sum('amount');

        $totalEarnedPointsFloat = (float) $totalEarnedPoints;
        $totalEarnedUsd = number_format($totalEarnedPointsFloat * 0.001, 2, '.', '');

        return $this->success([
            'referral_code' => $user->referral_code,
            'referral_link' => $referralLink,
            'rules' => [
                'qualification_threshold_points' => 1000,
                'bonus_per_referral_points' => 500,
                'bonus_per_referral_usd' => '0.50',
                'description' => 'Earn 500 Points ($0.50 USD) when your invited friend earns at least 1,000 Points in verified offers.',
            ],
            'statistics' => [
                'total_referrals' => $totalReferrals,
                'qualified_referrals' => $qualifiedCount,
                'pending_referrals' => $totalReferrals - $qualifiedCount,
                'total_earned_points' => number_format($totalEarnedPointsFloat, 4, '.', ''),
                'total_earned_usd' => $totalEarnedUsd,
            ],
            'referees' => $refereeList,
        ]);
    }
}
