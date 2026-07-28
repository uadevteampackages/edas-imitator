<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local user materialization
    |--------------------------------------------------------------------------
    |
    | unique_by: local column used to find/create the user (e.g. "id" or "email").
    | attributes: map of local column => Entra source key.
    |   Sources: id, name, email, userPrincipalName, job_title, username
    |   (username is derived from the UPN local-part; email falls back to UPN)
    | create_only: attributes set only when creating a new user.
    |   Use "random" to generate a random string (for password columns with a
    |   hashed cast, pass plain "random" and let the model hash it).
    |
    */

    'user' => [
        'model' => env('IMITATOR_USER_MODEL', 'App\\Models\\User'),
        'unique_by' => env('IMITATOR_USER_UNIQUE_BY', 'id'),
        'attributes' => [
            'id' => 'id',
            'name' => 'name',
            'email' => 'email',
            'principal_name' => 'userPrincipalName',
            'username' => 'username',
            'job_title' => 'job_title',
        ],
        'create_only' => [
            // 'password' => 'random',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session attributes
    |--------------------------------------------------------------------------
    |
    | Map of session key => source written after the clean slate on start/switch.
    | Use this for host apps that identify the current user via session keys
    | (for example Okta OIDC's session('username')) instead of Auth::user().
    |
    | Sources:
    |   - Same Entra sources as user.attributes: id, name, email,
    |     userPrincipalName, job_title, username
    |   - "preserve" — copy the key's value from the original session snapshot
    |   - "expires_at" — now + session_expires_in seconds (ISO-8601)
    |   - "okta_principal" — call okta-oidc.principal_resolver (ua/okta-oidc)
    |     with the Entra user adapted to an OIDC-like preferred_username claim
    |
    | Example for ua/okta-oidc hosts:
    |
    | 'session' => [
    |     'username' => 'okta_principal',
    |     'okta.name' => 'name',
    |     'okta.email' => 'email',
    |     'okta.session_expires_at' => 'preserve',
    | ],
    |
    */

    'session' => [
        // 'username' => 'okta_principal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session expiry helper
    |--------------------------------------------------------------------------
    |
    | Used when a session map entry uses the "expires_at" source.
    |
    */

    'session_expires_in' => (int) env('IMITATOR_SESSION_EXPIRES_IN', 60 * 60 * 8),

    /*
    |--------------------------------------------------------------------------
    | Route middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web', 'imitator.auth', 'imitator.local'],

    /*
    |--------------------------------------------------------------------------
    | Guest redirect
    |--------------------------------------------------------------------------
    |
    | Where to send unauthenticated users. If null, falls back to route('login').
    | For Okta OIDC hosts, set this to your OIDC login path, e.g. /auth/oidc/login.
    |
    */

    'redirect_guests_to' => env('IMITATOR_REDIRECT_GUESTS_TO'),

    /*
    |--------------------------------------------------------------------------
    | Banner injection
    |--------------------------------------------------------------------------
    |
    | When true, HTML responses get an imitating banner injected after <body>.
    | Set false and @include('imitator::banner') in your layout instead.
    |
    */

    'inject_banner' => env('IMITATOR_INJECT_BANNER', true),

    /*
    |--------------------------------------------------------------------------
    | Microsoft Entra / Graph (client credentials)
    |--------------------------------------------------------------------------
    */

    'azure' => [
        'tenant_id' => env('IMITATOR_AZURE_TENANT_ID'),
        'client_id' => env('IMITATOR_AZURE_CLIENT_ID'),
        'client_secret' => env('IMITATOR_AZURE_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph search filters (UA defaults)
    |--------------------------------------------------------------------------
    */

    'graph_filters' => [
        'exclude_job_titles' => ['STUDENT'],
        'exclude_upn_suffixes' => ['@bama365.onmicrosoft.com'],
        'exclude_upn_prefixes' => ['oit-', 'edas-', 'admin-', 'cs-', 'iam-'],
    ],

];
