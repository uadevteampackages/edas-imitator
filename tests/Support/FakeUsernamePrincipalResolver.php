<?php

namespace Uadevteampackages\Imitator\Tests\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Test double shaped like ua/okta-oidc UsernamePrincipalResolver.
 */
class FakeUsernamePrincipalResolver
{
    public function resolve(object $oidcUser, Request $request): string
    {
        $preferredUsername = data_get($oidcUser->getRaw(), 'preferred_username');

        return Str::of((string) $preferredUsername)->before('@')->lower()->toString();
    }
}
