<?php

namespace Uadevteampackages\Imitator\Tests\Unit;

use Illuminate\Support\Facades\Auth;
use Uadevteampackages\Imitator\Imitator;
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
}
