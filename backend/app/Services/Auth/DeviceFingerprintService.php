<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceFingerprintService
{
    /**
     * Resolve the device fingerprint from request headers or fallback to a deterministic client signature.
     */
    public function resolveFingerprint(Request $request): string
    {
        $headerFingerprint = $request->header('X-Device-Fingerprint');

        if (!empty($headerFingerprint) && is_string($headerFingerprint)) {
            return substr(trim($headerFingerprint), 0, 128);
        }

        // Deterministic fallback signature based on IP, User-Agent, and Accept headers
        $raw = implode('|', [
            $request->ip(),
            $request->userAgent() ?? 'unknown-ua',
            $request->header('Accept-Language') ?? 'unknown-lang',
        ]);

        return hash('sha256', $raw);
    }

    /**
     * Check if the fingerprint is already used by another distinct user account.
     */
    public function isFingerprintSharedByOthers(string $fingerprint, ?int $currentUserId = null): bool
    {
        $query = UserDevice::where('device_fingerprint', $fingerprint);

        if ($currentUserId) {
            $query->where('user_id', '!=', $currentUserId);
        }

        return $query->exists();
    }

    /**
     * Record or update the device information for an authenticated user.
     */
    public function trackDevice(User $user, Request $request): UserDevice
    {
        $fingerprint = $this->resolveFingerprint($request);
        $clientPlatform = substr((string) ($request->header('X-Client-Platform') ?? 'web'), 0, 64);
        $ip = $request->ip() ?? '127.0.0.1';
        $ua = $request->userAgent();

        $isShared = $this->isFingerprintSharedByOthers($fingerprint, $user->id);

        if ($isShared) {
            Log::warning('Multi-accounting alert: Fingerprint collision detected', [
                'user_id' => $user->id,
                'fingerprint' => $fingerprint,
                'ip' => $ip,
            ]);
        }

        /** @var UserDevice $device */
        $device = UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_fingerprint' => $fingerprint,
            ],
            [
                'client_platform' => $clientPlatform,
                'user_agent' => $ua,
                'ip_address' => $ip,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'is_flagged' => $isShared,
            ]
        );

        return $device;
    }
}
