<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_device_fingerprint(): void
    {
        Event::fake([Registered::class]);

        $payload = [
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];

        $fingerprint = hash('sha256', 'mock-canvas-audio-hardware-fingerprint');

        $response = $this->withHeaders([
            'X-Device-Fingerprint' => $fingerprint,
            'X-Client-Platform' => 'web-chrome',
        ])->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registration successful. Please verify your email address.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'username',
                        'email',
                        'role',
                        'status',
                        'referral_code',
                        'is_email_verified',
                    ],
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'registration_fingerprint' => $fingerprint,
        ]);

        $this->assertDatabaseHas('user_devices', [
            'device_fingerprint' => $fingerprint,
            'client_platform' => 'web-chrome',
        ]);

        Event::assertDispatched(Registered::class);
    }

    public function test_registration_fails_on_duplicate_email(): void
    {
        User::create([
            'name' => 'Existing',
            'username' => 'existinguser',
            'email' => 'duplicate@example.com',
            'password' => Hash::make('password123'),
            'referral_code' => 'EXISTING',
        ]);

        $payload = [
            'name' => 'Another User',
            'username' => 'anotheruser',
            'email' => 'duplicate@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_email_or_username(): void
    {
        $user = User::create([
            'name' => 'Alice Smith',
            'username' => 'alicesmith',
            'email' => 'alice@example.com',
            'password' => Hash::make('Secret123!'),
            'referral_code' => 'ALICE123',
        ]);

        $fingerprint = hash('sha256', 'alice-device-fp');

        // Test login with email
        $responseEmail = $this->withHeaders([
            'X-Device-Fingerprint' => $fingerprint,
        ])->postJson('/api/v1/auth/login', [
            'login' => 'alice@example.com',
            'password' => 'Secret123!',
        ]);

        $responseEmail->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'alice@example.com',
                    ],
                ],
            ]);

        // Test login with username
        $responseUsername = $this->withHeaders([
            'X-Device-Fingerprint' => $fingerprint,
        ])->postJson('/api/v1/auth/login', [
            'login' => 'alicesmith',
            'password' => 'Secret123!',
        ]);

        $responseUsername->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::create([
            'name' => 'Bob',
            'username' => 'bobuser',
            'email' => 'bob@example.com',
            'password' => Hash::make('CorrectPassword'),
            'referral_code' => 'BOBCODE1',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'bob@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_authenticated_user_can_fetch_profile_and_logout(): void
    {
        $user = User::create([
            'name' => 'Charlie',
            'username' => 'charlie',
            'email' => 'charlie@example.com',
            'password' => Hash::make('Password123!'),
            'referral_code' => 'CHARLIE1',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        // Fetch profile
        $profileResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $profileResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'charlie@example.com',
                    ],
                ],
            ]);

        // Logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_email_verification_flow(): void
    {
        $user = User::create([
            'name' => 'Dave',
            'username' => 'daveuser',
            'email' => 'dave@example.com',
            'password' => Hash::make('Password123!'),
            'referral_code' => 'DAVECODE',
        ]);

        $this->assertFalse($user->hasVerifiedEmail());

        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->getJson($signedUrl);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['verified' => true],
            ]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
