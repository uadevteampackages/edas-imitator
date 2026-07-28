<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | Must use the UA Entra user shape: string primary key (Entra object id),
    | plus name, email, principal_name, username, and job_title columns.
    |
    */

    'user_model' => env('IMITATOR_USER_MODEL', 'App\\Models\\User'),

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
