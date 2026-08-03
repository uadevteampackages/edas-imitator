<?php

namespace Uadevteampackages\Imitator\Tests\Unit;

use Illuminate\Support\Facades\Auth;
use Uadevteampackages\Imitator\Imitator;
use Uadevteampackages\Imitator\Tests\Models\StandardUser;
use Uadevteampackages\Imitator\Tests\Models\User;
use Uadevteampackages\Imitator\Tests\Support\FakeUsernamePrincipalResolver;
use Uadevteampackages\Imitator\Tests\Support\FakeUserPrincipalNamePrincipalResolver;
use Uadevteampackages\Imitator\Tests\TestCase;

class ImitatorTest extends TestCase
{
    public function test_enabled_requires_flag_and_non_prod_environment(): void
    {
        $imitator = $this->app->make(Imitator::class);

        config(['imitator.enabled' => true]);
        $this->app['env'] = 'local';
        $this->assertTrue($imitator->enabled());

        $this->app['env'] = 'testing';
        $this->assertTrue($imitator->enabled());

        $this->app['env'] = 'production';
        $this->assertFalse($imitator->enabled());

        $this->app['env'] = 'Prod';
        $this->assertFalse($imitator->enabled());

        $this->app['env'] = 'prod-replica';
        $this->assertFalse($imitator->enabled());

        config(['imitator.enabled' => false]);
        $this->app['env'] = 'local';
        $this->assertFalse($imitator->enabled());
    }

    public function test_start_snapshots_session_applies_clean_slate_and_logs_in_target(): void
    {
        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $target = User::query()->create([
            'id' => 'target-id',
            'name' => 'Target User',
            'email' => 'target@ua.edu',
            'principal_name' => 'target@ua.edu',
            'username' => 'target',
        ]);

        Auth::login($original);
        session(['custom_context' => 'keep-me']);

        $imitator = $this->app->make(Imitator::class);
        $imitator->start($original, $target);

        $this->assertTrue($imitator->active());
        $this->assertSame('target-id', Auth::id());
        $this->assertNull(session('custom_context'));
        $this->assertSame('keep-me', session('imitator.snapshot')['custom_context']);
        $this->assertSame('original-id', session('imitator.original_user_id'));
    }

    public function test_stop_restores_snapshot_and_original_user(): void
    {
        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $target = User::query()->create([
            'id' => 'target-id',
            'name' => 'Target User',
            'email' => 'target@ua.edu',
            'principal_name' => 'target@ua.edu',
            'username' => 'target',
        ]);

        Auth::login($original);
        session(['custom_context' => 'keep-me']);

        $imitator = $this->app->make(Imitator::class);
        $imitator->start($original, $target);
        $imitator->stop();

        $this->assertFalse($imitator->active());
        $this->assertSame('original-id', Auth::id());
        $this->assertSame('keep-me', session('custom_context'));
        $this->assertFalse(session()->has('imitator'));
    }

    public function test_switch_keeps_original_snapshot(): void
    {
        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $first = User::query()->create([
            'id' => 'first-target',
            'name' => 'First Target',
            'email' => 'first@ua.edu',
            'principal_name' => 'first@ua.edu',
            'username' => 'first',
        ]);

        $second = User::query()->create([
            'id' => 'second-target',
            'name' => 'Second Target',
            'email' => 'second@ua.edu',
            'principal_name' => 'second@ua.edu',
            'username' => 'second',
        ]);

        Auth::login($original);
        session(['custom_context' => 'keep-me']);

        $imitator = $this->app->make(Imitator::class);
        $imitator->start($original, $first);
        $imitator->start($original, $second);

        $this->assertSame('second-target', Auth::id());
        $this->assertSame('original-id', session('imitator.original_user_id'));
        $this->assertSame('keep-me', session('imitator.snapshot')['custom_context']);
    }

    public function test_upsert_user_from_entra_uses_ua_shape(): void
    {
        $imitator = $this->app->make(Imitator::class);

        $user = $imitator->upsertUserFromEntra([
            'id' => 'graph-1',
            'name' => 'Graph User',
            'userPrincipalName' => 'graph.user@ua.edu',
            'email' => null,
            'job_title' => 'Analyst',
        ]);

        $this->assertSame('graph-1', $user->id);
        $this->assertSame('graph.user@ua.edu', $user->email);
        $this->assertSame('graph.user@ua.edu', $user->principal_name);
        $this->assertSame('graph.user', $user->username);
        $this->assertSame('Analyst', $user->job_title);
    }

    public function test_upsert_can_match_standard_users_by_email(): void
    {
        config([
            'imitator.user' => [
                'model' => StandardUser::class,
                'unique_by' => 'email',
                'attributes' => [
                    'name' => 'name',
                    'email' => 'email',
                ],
                'create_only' => [
                    'password' => 'random',
                ],
            ],
        ]);

        $imitator = $this->app->make(Imitator::class);

        $created = $imitator->upsertUserFromEntra([
            'id' => 'entra-guid-1',
            'name' => 'Standard User',
            'userPrincipalName' => 'standard.user@ua.edu',
            'email' => 'standard.user@ua.edu',
        ]);

        $this->assertInstanceOf(StandardUser::class, $created);
        $this->assertIsInt($created->id);
        $this->assertSame('Standard User', $created->name);
        $this->assertSame('standard.user@ua.edu', $created->email);
        $this->assertNotEmpty($created->password);

        $updated = $imitator->upsertUserFromEntra([
            'id' => 'entra-guid-1',
            'name' => 'Standard User Updated',
            'userPrincipalName' => 'standard.user@ua.edu',
            'email' => 'standard.user@ua.edu',
        ]);

        $this->assertSame($created->id, $updated->id);
        $this->assertSame('Standard User Updated', $updated->name);
        $this->assertSame(1, StandardUser::query()->count());
    }

    public function test_targets_same_user_compares_configured_unique_key(): void
    {
        config([
            'imitator.user' => [
                'model' => StandardUser::class,
                'unique_by' => 'email',
                'attributes' => [
                    'name' => 'name',
                    'email' => 'email',
                ],
                'create_only' => [
                    'password' => 'random',
                ],
            ],
        ]);

        $original = StandardUser::query()->create([
            'name' => 'Me',
            'email' => 'me@ua.edu',
            'password' => 'secret-password',
        ]);

        $imitator = $this->app->make(Imitator::class);

        $this->assertTrue($imitator->targetsSameUser($original, [
            'id' => 'different-entra-id',
            'name' => 'Me',
            'userPrincipalName' => 'me@ua.edu',
            'email' => 'me@ua.edu',
        ]));

        $this->assertFalse($imitator->targetsSameUser($original, [
            'id' => 'different-entra-id',
            'name' => 'Other',
            'userPrincipalName' => 'other@ua.edu',
            'email' => 'other@ua.edu',
        ]));
    }

    public function test_start_writes_configured_session_attributes_from_entra_payload(): void
    {
        config([
            'imitator.session' => [
                'username' => 'username',
                'okta.name' => 'name',
                'okta.email' => 'email',
            ],
        ]);

        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $target = User::query()->create([
            'id' => 'target-id',
            'name' => 'Target User',
            'email' => 'target@ua.edu',
            'principal_name' => 'target@ua.edu',
            'username' => 'target',
        ]);

        Auth::login($original);
        session([
            'username' => 'original@ua.edu',
            'okta.session_expires_at' => now()->addHour()->toIso8601String(),
        ]);

        $imitator = $this->app->make(Imitator::class);
        $imitator->start($original, $target, [
            'id' => 'target-id',
            'name' => 'Target User',
            'userPrincipalName' => 'Target.User@UA.edu',
            'email' => 'Target.User@UA.edu',
        ]);

        $this->assertSame('target.user', session('username'));
        $this->assertSame('Target User', session('okta.name'));
        $this->assertSame('target.user@ua.edu', session('okta.email'));
        $this->assertNull(session('okta.session_expires_at'));
    }

    public function test_start_uses_okta_principal_resolver_from_host_config(): void
    {
        config([
            'okta-oidc.principal_resolver' => FakeUsernamePrincipalResolver::class,
            'imitator.session' => [
                'username' => 'okta_principal',
            ],
        ]);

        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $target = User::query()->create([
            'id' => 'target-id',
            'name' => 'Target User',
            'email' => 'target@ua.edu',
            'principal_name' => 'target@ua.edu',
            'username' => 'target',
        ]);

        Auth::login($original);

        $imitator = $this->app->make(Imitator::class);
        $imitator->start($original, $target, [
            'id' => 'target-id',
            'name' => 'Target User',
            'userPrincipalName' => 'Target.User@UA.edu',
            'email' => 'Target.User@UA.edu',
        ]);

        $this->assertSame('target.user', session('username'));
    }

    public function test_okta_principal_source_follows_upn_resolver_when_configured(): void
    {
        config([
            'okta-oidc.principal_resolver' => FakeUserPrincipalNamePrincipalResolver::class,
            'imitator.session' => [
                'username' => 'okta_principal',
            ],
        ]);

        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $target = User::query()->create([
            'id' => 'target-id',
            'name' => 'Target User',
            'email' => 'target@ua.edu',
            'principal_name' => 'target@ua.edu',
            'username' => 'target',
        ]);

        Auth::login($original);

        $imitator = $this->app->make(Imitator::class);
        $imitator->start($original, $target, [
            'id' => 'target-id',
            'name' => 'Target User',
            'userPrincipalName' => 'Target.User@UA.edu',
        ]);

        $this->assertSame('target.user@ua.edu', session('username'));
    }

    public function test_okta_principal_source_requires_resolver_config(): void
    {
        config([
            'okta-oidc.principal_resolver' => null,
            'imitator.session' => [
                'username' => 'okta_principal',
            ],
        ]);

        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $target = User::query()->create([
            'id' => 'target-id',
            'name' => 'Target User',
            'email' => 'target@ua.edu',
            'principal_name' => 'target@ua.edu',
            'username' => 'target',
        ]);

        Auth::login($original);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('okta-oidc.principal_resolver');

        $this->app->make(Imitator::class)->start($original, $target, [
            'id' => 'target-id',
            'name' => 'Target User',
            'userPrincipalName' => 'target@ua.edu',
        ]);
    }

    public function test_start_can_preserve_and_synthesize_session_expiry_keys(): void
    {
        config([
            'imitator.session' => [
                'username' => 'username',
                'okta.session_expires_at' => 'preserve',
                'okta.imitator_expires_at' => 'expires_at',
            ],
            'imitator.session_expires_in' => 120,
        ]);

        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $target = User::query()->create([
            'id' => 'target-id',
            'name' => 'Target User',
            'email' => 'target@ua.edu',
            'principal_name' => 'target@ua.edu',
            'username' => 'target',
        ]);

        $expiresAt = now()->addHours(3)->toIso8601String();

        Auth::login($original);
        session([
            'username' => 'original',
            'okta.session_expires_at' => $expiresAt,
        ]);

        $this->travelTo(now());

        $imitator = $this->app->make(Imitator::class);
        $imitator->start($original, $target, [
            'id' => 'target-id',
            'name' => 'Target User',
            'userPrincipalName' => 'target@ua.edu',
            'email' => 'target@ua.edu',
        ]);

        $this->assertSame('target', session('username'));
        $this->assertSame($expiresAt, session('okta.session_expires_at'));
        $this->assertSame(now()->addSeconds(120)->toIso8601String(), session('okta.imitator_expires_at'));
    }

    public function test_stop_restores_original_session_attributes(): void
    {
        config([
            'imitator.session' => [
                'username' => 'username',
                'okta.session_expires_at' => 'preserve',
            ],
        ]);

        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $target = User::query()->create([
            'id' => 'target-id',
            'name' => 'Target User',
            'email' => 'target@ua.edu',
            'principal_name' => 'target@ua.edu',
            'username' => 'target',
        ]);

        $expiresAt = now()->addHours(2)->toIso8601String();

        Auth::login($original);
        session([
            'username' => 'original',
            'okta.session_expires_at' => $expiresAt,
        ]);

        $imitator = $this->app->make(Imitator::class);
        $imitator->start($original, $target, [
            'id' => 'target-id',
            'name' => 'Target User',
            'userPrincipalName' => 'target@ua.edu',
        ]);
        $imitator->stop();

        $this->assertSame('original', session('username'));
        $this->assertSame($expiresAt, session('okta.session_expires_at'));
    }

    public function test_preserve_refreshes_expired_session_expiry_keys(): void
    {
        config([
            'imitator.session' => [
                'username' => 'username',
                'okta.session_expires_at' => 'preserve',
            ],
            'imitator.session_expires_in' => 120,
        ]);

        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $target = User::query()->create([
            'id' => 'target-id',
            'name' => 'Target User',
            'email' => 'target@ua.edu',
            'principal_name' => 'target@ua.edu',
            'username' => 'target',
        ]);

        Auth::login($original);
        session([
            'username' => 'original',
            'okta.session_expires_at' => now()->subMinute()->toIso8601String(),
        ]);

        $this->travelTo(now());

        $imitator = $this->app->make(Imitator::class);
        $imitator->start($original, $target, [
            'id' => 'target-id',
            'name' => 'Target User',
            'userPrincipalName' => 'target@ua.edu',
        ]);

        $this->assertSame(now()->addSeconds(120)->toIso8601String(), session('okta.session_expires_at'));
    }

    public function test_target_user_comes_from_stored_target_id_not_auth_guard(): void
    {
        $original = User::query()->create([
            'id' => 'original-id',
            'name' => 'Original User',
            'email' => 'original@ua.edu',
            'principal_name' => 'original@ua.edu',
            'username' => 'original',
        ]);

        $target = User::query()->create([
            'id' => 'target-id',
            'name' => 'Target User',
            'email' => 'target@ua.edu',
            'principal_name' => 'target@ua.edu',
            'username' => 'target',
        ]);

        Auth::login($original);

        $imitator = $this->app->make(Imitator::class);
        $imitator->start($original, $target);

        Auth::login($original);

        $this->assertSame('target-id', $imitator->targetUser()?->getAuthIdentifier());
        $this->assertSame('Target User', $imitator->status()['targetUser']['name']);
    }
}
