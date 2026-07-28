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
