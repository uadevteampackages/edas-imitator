<?php

namespace Uadevteampackages\Imitator\Tests\Support;

use Illuminate\Http\Request;

/**
 * Test double shaped like ua/okta-oidc UserPrincipalNamePrincipalResolver.
 */
class FakeUserPrincipalNamePrincipalResolver
{
    public function resolve(object $oidcUser, Request $request): string
    {
        return strtolower((string) data_get($oidcUser->getRaw(), 'preferred_username'));
    }
}
