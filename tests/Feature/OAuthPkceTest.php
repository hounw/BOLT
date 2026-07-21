<?php

namespace Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\CoreAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class OAuthPkceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_client_can_complete_authorization_code_pkce_flow(): void
    {
        $this->seed(CoreAccessSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(SystemRole::OwnerAdmin->value);

        $redirectUri = 'https://client.example.test/oauth/callback';
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'BOLT PKCE test client',
            [$redirectUri],
            false,
        );

        $verifier = str_repeat('bolt-pkce-verifier-', 4);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $authorization = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'employees:read knowledge:read',
            'state' => 'pkce-test-state',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]));

        $authorization->assertOk()->assertSee('Authorize BOLT PKCE test client');
        $authToken = session('authToken');
        $this->assertNotEmpty($authToken);

        $approval = $this->actingAs($user)->post('/oauth/authorize', [
            'auth_token' => $authToken,
        ])->assertRedirect();

        parse_str((string) parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $callback);
        $this->assertSame('pkce-test-state', $callback['state'] ?? null);
        $this->assertNotEmpty($callback['code'] ?? null);

        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $verifier,
            'code' => $callback['code'],
        ])->assertOk();

        $accessToken = $token->json('access_token');
        $this->assertNotEmpty($accessToken);

        $this->withToken($accessToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }
}
