<?php

namespace Uadevteampackages\Imitator;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
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
     * Upsert a local user from Graph/Entra payload using the configured attribute map.
     *
     * @param  array{id: string, name: string, userPrincipalName: string, email?: string|null, job_title?: string|null}  $userData
     */
    public function upsertUserFromEntra(array $userData): Authenticatable
    {
        $uniqueBy = $this->uniqueByColumn();
        $uniqueValue = $this->resolveMappedValue($userData, $uniqueBy);

        if ($uniqueValue === null || $uniqueValue === '') {
            throw new \InvalidArgumentException("Imitator cannot resolve unique key [{$uniqueBy}] from Entra payload.");
        }

        /** @var Model&Authenticatable $user */
        $user = $this->userModel()::query()->firstOrNew([
            $uniqueBy => $uniqueValue,
        ]);

        $isNew = ! $user->exists;

        foreach ($this->attributeMap() as $column => $source) {
            $user->setAttribute($column, $this->resolveSourceValue($userData, $source));
        }

        if ($isNew) {
            foreach ($this->createOnlyAttributes() as $column => $value) {
                $user->setAttribute($column, $this->resolveCreateOnlyValue($value));
            }
        }

        $user->save();

        return $user;
    }

    /**
     * @param  array{id: string, name: string, userPrincipalName: string, email?: string|null, job_title?: string|null}  $userData
     */
    public function targetsSameUser(Authenticatable $original, array $userData): bool
    {
        $uniqueBy = $this->uniqueByColumn();
        $incoming = $this->resolveMappedValue($userData, $uniqueBy);
        $current = data_get($original, $uniqueBy);

        if ($incoming === null || $incoming === '' || $current === null || $current === '') {
            return false;
        }

        return (string) $incoming === (string) $current;
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
     * @return class-string<Model&Authenticatable>
     */
    private function userModel(): string
    {
        /** @var class-string<Model&Authenticatable> $model */
        $model = (string) config('imitator.user.model', config('imitator.user_model', 'App\\Models\\User'));

        return $model;
    }

    private function uniqueByColumn(): string
    {
        return (string) config('imitator.user.unique_by', 'id');
    }

    /**
     * @return array<string, string>
     */
    private function attributeMap(): array
    {
        /** @var array<string, string> $map */
        $map = config('imitator.user.attributes', [
            'id' => 'id',
            'name' => 'name',
            'email' => 'email',
            'principal_name' => 'userPrincipalName',
            'username' => 'username',
            'job_title' => 'job_title',
        ]);

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function createOnlyAttributes(): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = config('imitator.user.create_only', []);

        return $attributes;
    }

    /**
     * @param  array{id: string, name: string, userPrincipalName: string, email?: string|null, job_title?: string|null}  $userData
     */
    private function resolveMappedValue(array $userData, string $column): mixed
    {
        $source = $this->attributeMap()[$column] ?? $column;

        return $this->resolveSourceValue($userData, $source);
    }

    /**
     * @param  array{id: string, name: string, userPrincipalName: string, email?: string|null, job_title?: string|null}  $userData
     */
    private function resolveSourceValue(array $userData, string $source): mixed
    {
        return match ($source) {
            'id' => $userData['id'],
            'name' => $userData['name'],
            'email' => ($userData['email'] ?? null) ?: $userData['userPrincipalName'],
            'userPrincipalName' => $userData['userPrincipalName'],
            'job_title' => $userData['job_title'] ?? null,
            'username' => strtolower(Str::before($userData['userPrincipalName'], '@')),
            default => $userData[$source] ?? null,
        };
    }

    private function resolveCreateOnlyValue(mixed $value): mixed
    {
        if ($value === 'random') {
            return Str::password(40);
        }

        return $value;
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
