<?php

namespace App\Http\Controllers\Api\V1\Offers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    /**
     * Retrieve available offerwall providers with authenticated iFrame embed URLs.
     */
    public function providers(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (string) $user->id;

        $bitlabsToken = config('services.offerwalls.bitlabs.app_token');
        $cpxAppId = config('services.offerwalls.cpx.app_id');
        $cpxSecureHash = config('services.offerwalls.cpx.secure_hash');
        $owggKey = config('services.offerwalls.offerwall_gg.api_key');
        $owcomPubId = config('services.offerwalls.offerwall_com.publisher_id');

        // Generate CPX secure user hash: md5(userId . '-' . secure_hash)
        $cpxUserHash = md5("{$userId}-{$cpxSecureHash}");

        $providers = [
            [
                'id' => 'bitlabs',
                'name' => 'BitLabs',
                'description' => 'Top paying surveys & mobile task rewards with instant qualification.',
                'category' => 'Surveys & Rewards',
                'tag' => 'Popular',
                'color' => '#6366f1',
                'rating' => 4.9,
                'iframe_url' => "https://web.bitlabs.ai/?token={$bitlabsToken}&uid={$userId}",
                'supported_types' => ['surveys', 'tasks', 'app_installs'],
            ],
            [
                'id' => 'cpx',
                'name' => 'CPX Research',
                'description' => 'Dynamic consumer opinion surveys with high payout rates and consolation points.',
                'category' => 'Surveys',
                'tag' => 'Instant Payout',
                'color' => '#06b6d4',
                'rating' => 4.8,
                'iframe_url' => "https://offers.cpx-research.com/index.php?app_id={$cpxAppId}&ext_user_id={$userId}&secure_hash={$cpxUserHash}",
                'supported_types' => ['surveys'],
            ],
            [
                'id' => 'offerwall_gg',
                'name' => 'Offerwall.gg',
                'description' => 'High-velocity CPA campaigns, financial app signups, and trial subscriptions.',
                'category' => 'CPA & Signups',
                'tag' => 'High Yield',
                'color' => '#10b981',
                'rating' => 4.7,
                'iframe_url' => "https://offerwall.gg/wall/{$owggKey}?user_id={$userId}",
                'supported_types' => ['apps', 'signups', 'trials'],
            ],
            [
                'id' => 'offerwall_com',
                'name' => 'Offerwall.com',
                'description' => 'Premium multi-stage gaming tasks, level progression, and desktop downloads.',
                'category' => 'Mobile & PC Gaming',
                'tag' => 'VIP Games',
                'color' => '#f59e0b',
                'rating' => 4.9,
                'iframe_url' => "https://wall.offerwall.com/?pub={$owcomPubId}&subId={$userId}",
                'supported_types' => ['games', 'level_milestones', 'downloads'],
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Available offerwall networks retrieved.',
            'data' => [
                'user_id' => $user->id,
                'providers' => $providers,
            ],
        ]);
    }

    /**
     * Retrieve featured direct campaign cards from integrated provider feeds.
     */
    public function featuredOffers(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (string) $user->id;

        // Simulated high-value direct campaign card feeds
        $offers = [
            [
                'id' => 'camp_raid_shadow_legends',
                'provider' => 'offerwall_com',
                'title' => 'Raid: Shadow Legends',
                'description' => 'Install the game and open 2 Sacred Shards within 30 days of registration.',
                'category' => 'Gaming',
                'device' => 'PC / Mobile',
                'payout_points' => 45000,
                'payout_usd' => 45.00,
                'badge' => 'Highest Payout',
                'icon' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=150&h=150&fit=crop',
                'click_url' => "https://wall.offerwall.com/click?offer_id=raid_01&subId={$userId}",
                'difficulty' => 'Medium',
                'estimated_time' => '3-5 days',
            ],
            [
                'id' => 'camp_crypto_com_app',
                'provider' => 'offerwall_gg',
                'title' => 'Crypto.com App',
                'description' => 'Register a verified account and complete your first fiat deposit of $10 or more.',
                'category' => 'Finance',
                'device' => 'iOS / Android',
                'payout_points' => 32000,
                'payout_usd' => 32.00,
                'badge' => 'Staff Pick',
                'icon' => 'https://images.unsplash.com/photo-1622979135225-d2ba269bc1df?w=150&h=150&fit=crop',
                'click_url' => "https://offerwall.gg/click?campaign=crypto_com&user_id={$userId}",
                'difficulty' => 'Easy',
                'estimated_time' => '15 mins',
            ],
            [
                'id' => 'camp_cpx_daily_survey',
                'provider' => 'cpx',
                'title' => 'Global Technology & AI Opinion Survey',
                'description' => 'Share your thoughts on emerging AI developer tools and tech consumer trends.',
                'category' => 'Surveys',
                'device' => 'All Devices',
                'payout_points' => 1800,
                'payout_usd' => 1.80,
                'badge' => 'Instant Credit',
                'icon' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=150&h=150&fit=crop',
                'click_url' => "https://offers.cpx-research.com/index.php?app_id=" . config('services.offerwalls.cpx.app_id') . "&ext_user_id={$userId}",
                'difficulty' => 'Easy',
                'estimated_time' => '12 mins',
            ],
            [
                'id' => 'camp_bitlabs_market_pulse',
                'provider' => 'bitlabs',
                'title' => 'E-Commerce Shopping Habits Survey',
                'description' => 'Answer questions regarding your online shopping, subscription services, and entertainment.',
                'category' => 'Surveys',
                'device' => 'All Devices',
                'payout_points' => 2400,
                'payout_usd' => 2.40,
                'badge' => 'High Match Rate',
                'icon' => 'https://images.unsplash.com/photo-1557821552-17105176677c?w=150&h=150&fit=crop',
                'click_url' => "https://web.bitlabs.ai/?token=" . config('services.offerwalls.bitlabs.app_token') . "&uid={$userId}",
                'difficulty' => 'Easy',
                'estimated_time' => '10 mins',
            ],
            [
                'id' => 'camp_monopoly_go',
                'provider' => 'offerwall_com',
                'title' => 'Monopoly GO!',
                'description' => 'Reach Board 26 within 14 days. Multi-tier payout with rewards at Board 5, 12, and 26.',
                'category' => 'Gaming',
                'device' => 'iOS / Android',
                'payout_points' => 28000,
                'payout_usd' => 28.00,
                'badge' => 'Multi-Tier',
                'icon' => 'https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?w=150&h=150&fit=crop',
                'click_url' => "https://wall.offerwall.com/click?offer_id=mgo_26&subId={$userId}",
                'difficulty' => 'Medium',
                'estimated_time' => '7 days',
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Featured offers retrieved.',
            'data' => [
                'total_offers' => count($offers),
                'offers' => $offers,
            ],
        ]);
    }
}
