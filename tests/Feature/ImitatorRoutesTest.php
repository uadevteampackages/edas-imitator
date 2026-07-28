<?php

namespace Uadevteampackages\Imitator\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Uadevteampackages\Imitator\Tests\Models\User;
use Uadevteampackages\Imitator\Tests\Support\FakeUsernamePrincipalResolver;
use Uadevteampackages\Imitator\Tests\TestCase;

class ImitatorRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/login', fn () => 'login')->name('login');

        Route::get('/', fn () => response('<html><body><h1>Home</h1></body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]))
            ->middleware('web')
            ->name('home');

        $this->withoutMiddleware([
            PreventRequestForgery::class,
        ]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/imitator')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_imitator_page(): void
    {
        $user = $this->makeUser('admin-id', 'Admin');

        $this->actingAs($user)
            ->get('/imitator')
            ->assertOk()
            ->assertSee('Imitator');
    }

    public function test_store_starts_imitation_and_redirects_home(): void
    {
        $admin = $this->makeUser('admin-id', 'Admin');

        $this->actingAs($admin)
            ->post('/imitator', [
                'user' => [
                    'id' => 'target-id',
                    'name' => 'Target User',
                    'userPrincipalName' => 'target@ua.edu',
                    'email' => 'target@ua.edu',
                    'job_title' => 'Developer',
                ],
            ])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs(User::query()->findOrFail('target-id'));
        $this->assertSame('admin-id', session('imitator.original_user_id'));
    }

    public function test_store_writes_configured_session_attributes(): void
    {
        config([
            'imitator.session' => [
                'username' => 'okta_principal',
                'okta.name' => 'name',
                'okta.session_expires_at' => 'preserve',
            ],
            'okta-oidc.principal_resolver' => FakeUsernamePrincipalResolver::class,
        ]);

        $admin = $this->makeUser('admin-id', 'Admin');
        $expiresAt = now()->addHour()->toIso8601String();

        $this->actingAs($admin)
            ->withSession([
                'username' => 'admin',
                'okta.session_expires_at' => $expiresAt,
            ])
            ->post('/imitator', [
                'user' => [
                    'id' => 'target-id',
                    'name' => 'Target User',
                    'userPrincipalName' => 'target@ua.edu',
                    'email' => 'target@ua.edu',
                ],
            ])
            ->assertRedirect('/');

        $this->assertSame('target', session('username'));
        $this->assertSame('Target User', session('okta.name'));
        $this->assertSame($expiresAt, session('okta.session_expires_at'));
    }

    public function test_destroy_stops_imitation_and_redirects_to_imitator(): void
    {
        $admin = $this->makeUser('admin-id', 'Admin');

        $this->actingAs($admin)
            ->post('/imitator', [
                'user' => [
                    'id' => 'target-id',
                    'name' => 'Target User',
                    'userPrincipalName' => 'target@ua.edu',
                ],
            ]);

        $this->delete('/imitator')
            ->assertRedirect('/imitator');

        $this->assertAuthenticatedAs($admin->fresh());
        $this->assertFalse(session()->has('imitator'));
    }

    public function test_search_returns_filtered_graph_users(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token'], 200),
            'graph.microsoft.com/v1.0/users*' => Http::response([
                'value' => [
                    [
                        'id' => '1',
                        'displayName' => 'Jane Doe',
                        'userPrincipalName' => 'jane.doe@ua.edu',
                        'jobTitle' => 'Analyst',
                    ],
                    [
                        'id' => '2',
                        'displayName' => 'Service Account',
                        'userPrincipalName' => 'oit-bot@ua.edu',
                        'jobTitle' => 'Bot',
                    ],
                ],
            ], 200),
        ]);

        $user = $this->makeUser('admin-id', 'Admin');

        $this->actingAs($user)
            ->getJson('/imitator/search?q=jane')
            ->assertOk()
            ->assertJsonCount(1, 'value')
            ->assertJsonPath('value.0.userPrincipalName', 'jane.doe@ua.edu');
    }

    public function test_rejects_imitating_yourself(): void
    {
        $admin = $this->makeUser('admin-id', 'Admin');

        $this->actingAs($admin)
            ->from('/imitator')
            ->post('/imitator', [
                'user' => [
                    'id' => 'admin-id',
                    'name' => 'Admin',
                    'userPrincipalName' => 'admin@ua.edu',
                ],
            ])
            ->assertRedirect('/imitator')
            ->assertSessionHasErrors('user.id');
    }

    public function test_banner_is_injected_while_imitating(): void
    {
        $admin = $this->makeUser('admin-id', 'Admin');

        $this->actingAs($admin)
            ->post('/imitator', [
                'user' => [
                    'id' => 'target-id',
                    'name' => 'Target User',
                    'userPrincipalName' => 'target@ua.edu',
                ],
            ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('imitator-banner', false)
            ->assertSee('Imitating Target User');
    }

    private function makeUser(string $id, string $name): User
    {
        return User::query()->create([
            'id' => $id,
            'name' => $name,
            'email' => strtolower($name).'@ua.edu',
            'principal_name' => strtolower($name).'@ua.edu',
            'username' => strtolower($name),
        ]);
    }
}
