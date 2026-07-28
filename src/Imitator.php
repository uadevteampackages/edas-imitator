<?php

namespace Uadevteampackages\Imitator;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Imitator
{
    public const SESSION_KEY = 'imitator';

    public function enabled(): bool
    {
        return app()->environment('local');
    }

    public function active(): bool
    {
        return session()->has($this->sessionKey('original_user_id'));
    }

    public function originalUser(): ?Authenticatable
    {
        $originalUserId = session($this->sessionKey('original_user_id'));

        if (! $originalUserId) {
            return null;
        }

        return $this->userModel()::query()->find($originalUserId);
    }

    public function targetUser(): ?Authenticatable
    {
        return Auth::guard($this->guardName())->user();
    }

    public function startedAt(): ?string
    {
        $startedAt = session($this->sessionKey('started_at'));

        return is_string($startedAt) ? $startedAt : null;
    }

    /**
     * Start imitating $target, or switch targets while keeping the original snapshot.
     */
    public function start(Authenticatable $original, Authenticatable $target): void
    {
        if (! $this->active()) {
            session()->put($this->sessionKey('snapshot'), $this->captureSessionSnapshot());
            session()->put($this->sessionKey('original_user_id'), $original->getAuthIdentifier());
            session()->put($this->sessionKey('started_at'), now()->toISOString());
        }

        session()->put($this->sessionKey('target_user_id'), $target->getAuthIdentifier());

        $this->applyCleanSlate();
        $this->login($target);
    }

    public function stop(): void
    {
        $originalUser = $this->originalUser();
        $snapshot = session($this->sessionKey('snapshot'), []);

        session()->flush();

        foreach ($snapshot as $key => $value) {
            session()->put($key, $value);
        }

        if ($originalUser) {
            $this->login($originalUser);
        }

        session()->forget(self::SESSION_KEY);
    }

    /**
     * Upsert a local user from Graph/Entra payload using the UA user shape.
     *
     * @param  array{id: string, name: string, userPrincipalName: string, email?: string|null, job_title?: string|null}  $userData
     */
    public function upsertUserFromEntra(array $userData): Authenticatable
    {
        $modelClass = $this->userModel();

        return $modelClass::query()->updateOrCreate(
            ['id' => $userData['id']],
            [
                'name' => $userData['name'],
                'email' => ($userData['email'] ?? null) ?: $userData['userPrincipalName'],
                'principal_name' => $userData['userPrincipalName'],
                'username' => strtolower(Str::before($userData['userPrincipalName'], '@')),
                'job_title' => $userData['job_title'] ?? null,
            ],
        );
    }

    /**
     * @return array{active: bool, originalUser: array{id: string, name: string|null, email: string|null}|null, targetUser: array{id: string, name: string|null, email: string|null}|null, startedAt: string|null, enabled: bool}
     */
    public function status(): array
    {
        $active = $this->active();

        return [
            'active' => $active,
            'originalUser' => $active ? $this->serializeUser($this->originalUser()) : null,
            'targetUser' => $active ? $this->serializeUser($this->targetUser()) : null,
            'startedAt' => $this->startedAt(),
            'enabled' => $this->enabled(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function captureSessionSnapshot(): array
    {
        $session = session()->all();

        unset($session[self::SESSION_KEY]);

        return $session;
    }

    private function applyCleanSlate(): void
    {
        $bag = session(self::SESSION_KEY);
        $token = session('_token');

        session()->flush();

        if (is_string($token) && $token !== '') {
            session()->put('_token', $token);
        }

        session()->put(self::SESSION_KEY, $bag);
    }

    private function login(Authenticatable $user): void
    {
        $guard = $this->guardName();

        Auth::guard($guard)->login($user);
        Auth::shouldUse($guard);
        Auth::setUser($user);
    }

    /**
     * @return class-string<Authenticatable>
     */
    private function userModel(): string
    {
        /** @var class-string<Authenticatable> $model */
        $model = (string) config('imitator.user_model');

        return $model;
    }

    private function guardName(): string
    {
        return (string) config('auth.defaults.guard', 'web');
    }

    /**
     * @return array{id: string, name: string|null, email: string|null}|null
     */
    private function serializeUser(?Authenticatable $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => (string) $user->getAuthIdentifier(),
            'name' => $user->name ?? null,
            'email' => $user->email ?? null,
        ];
    }

    private function sessionKey(string $key): string
    {
        return self::SESSION_KEY.'.'.$key;
    }
}
