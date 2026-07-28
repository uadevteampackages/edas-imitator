<?php

namespace Uadevteampackages\Imitator\Tests\Unit;

use Illuminate\Support\Facades\Auth;
use Uadevteampackages\Imitator\Imitator;
use Uadevteampackages\Imitator\Tests\Models\StandardUser;
use Uadevteampackages\Imitator\Tests\Models\User;
use Uadevteampackages\Imitator\Tests\TestCase;

class ImitatorTest extends TestCase
{
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
}
