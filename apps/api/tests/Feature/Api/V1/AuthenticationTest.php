<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('barkeelu_test', config('database.connections.pgsql.database'));
        $this->assertSame('barkeelu_test', DB::scalar('select current_database()'));
    }

    public function test_health_endpoint_returns_minimal_status(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_user_can_create_a_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-for-test'),
        ]);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password-for-test',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token', 'token_type'])
            ->assertJsonMissing(['password']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotSame($response->json('token'), PersonalAccessToken::query()->value('token'));
    }

    public function test_invalid_credentials_do_not_create_a_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-for-test'),
        ]);

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'incorrect-password',
            'device_name' => 'phpunit',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_token_request_validates_required_fields(): void
    {
        $this->postJson('/api/v1/auth/token', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_token_requests_are_rate_limited_after_five_attempts(): void
    {
        $payload = [
            'email' => 'unknown@example.test',
            'password' => 'incorrect-password',
            'device_name' => 'phpunit',
        ];

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/token', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/token', $payload)->assertTooManyRequests();
    }

    public function test_authenticated_user_is_returned_without_sensitive_fields(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('name', $user->name)
            ->assertJsonPath('email', $user->email)
            ->assertJsonMissing(['password', 'remember_token', 'tokens']);
    }

    public function test_unauthenticated_user_request_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/user')->assertUnauthorized();
    }

    public function test_current_token_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/token')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/user')->assertUnauthorized();
    }
}
