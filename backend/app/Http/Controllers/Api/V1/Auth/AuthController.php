<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\DeviceFingerprintService;
use App\Services\Ledger\LedgerService;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DeviceFingerprintService $deviceService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Register a new user account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $referrer = null;
        if (!empty($validated['referral_code'])) {
            $referrer = User::where('referral_code', $validated['referral_code'])->first();
        }

        $fingerprint = $this->deviceService->resolveFingerprint($request);

        // Generate unique 8-character uppercase referral code
        do {
            $referralCode = strtoupper(Str::random(8));
        } while (User::where('referral_code', $referralCode)->exists());

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'status' => 'active',
            'registration_ip' => $request->ip(),
            'registration_fingerprint' => $fingerprint,
            'last_login_ip' => $request->ip(),
            'last_login_at' => now(),
            'referral_code' => $referralCode,
            'referred_by_id' => $referrer?->id,
        ]);

        // Track registration device
        $this->deviceService->trackDevice($user, $request);

        // Send email verification notification
        event(new Registered($user));

        $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'referral_code' => $user->referral_code,
                'is_email_verified' => $user->isEmailVerified(),
                'created_at' => $user->created_at,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registration successful. Please verify your email address.', Response::HTTP_CREATED);
    }

    /**
     * Authenticate user with credentials.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $login = $validated['login'];

        /** @var User|null $user */
        $user = User::where('email', strtolower($login))
            ->orWhere('username', $login)
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->error('Invalid credentials provided.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->status === 'banned') {
            return $this->error('This account has been suspended due to policy violations.', Response::HTTP_FORBIDDEN);
        }

        // Track and record device
        $this->deviceService->trackDevice($user, $request);

        $user->update([
            'last_login_ip' => $request->ip(),
            'last_login_at' => now(),
        ]);

        $tokenExpiry = !empty($validated['remember']) ? now()->addDays(60) : now()->addDays(7);
        $token = $user->createToken('auth_token', ['*'], $tokenExpiry)->plainTextToken;

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'referral_code' => $user->referral_code,
                'is_email_verified' => $user->isEmailVerified(),
                'last_login_at' => $user->last_login_at,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Logged in successfully.');
    }

    /**
     * Get the authenticated user's profile and active session data.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Refresh device tracking on me request
        $device = $this->deviceService->trackDevice($user, $request);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'referral_code' => $user->referral_code,
                'is_email_verified' => $user->isEmailVerified(),
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ],
            'current_device' => [
                'fingerprint' => $device->device_fingerprint,
                'platform' => $device->client_platform,
                'ip_address' => $device->ip_address,
                'is_trusted' => $device->is_trusted,
                'is_flagged' => $device->is_flagged,
            ],
        ]);
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Verify email with temporary signed URL.
     */
    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('User not found.', Response::HTTP_NOT_FOUND);
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->error('Invalid verification link.', Response::HTTP_FORBIDDEN);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success([
                'already_verified' => true,
            ], 'Email address is already verified.');
        }

        $creditedBonus = false;
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));

            // Award one-time signup bonus (100.0000 Points = $0.10 USD)
            $alreadyAwarded = Transaction::where('type', Transaction::TYPE_SIGNUP_BONUS)
                ->where('reference_id', (string) $user->id)
                ->exists();

            if (!$alreadyAwarded) {
                $this->ledgerService->creditSignupBonus(
                    user: $user,
                    amount: '100.0000',
                    description: 'Welcome bonus for email verification'
                );
                $creditedBonus = true;
            }
        }

        return $this->success([
            'verified' => true,
            'bonus_credited' => $creditedBonus,
            'bonus_points' => $creditedBonus ? '100.0000' : '0.0000',
        ], 'Email address verified successfully. Welcome bonus of 100 Points ($0.10) credited.');
    }

    /**
     * Resend the email verification notification.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->error('Email address is already verified.', Response::HTTP_BAD_REQUEST);
        }

        $user->sendEmailVerificationNotification();

        return $this->success(null, 'Verification link sent to your registered email.');
    }
}
