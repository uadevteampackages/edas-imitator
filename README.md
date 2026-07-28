# UA Laravel Imitator

Local-only Entra user imitation for Laravel applications. Search Microsoft Graph, log in as the selected user, and restore your original session when you stop — without leaving leftover imitated state behind.

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- `APP_ENV=local` (the package does not register routes outside local)
- An authenticated session in the host app (routes use the `auth` middleware)
- An Entra app registration with application permission to search users (typically `User.Read.All`)

## Quick Start

### 1. Install the package

```bash
composer require uadevteampackages/imitator --dev
```

### 2. Publish the config

```bash
php artisan vendor:publish --tag=imitator-config
```

### 3. Add your Entra credentials to `.env`

```env
APP_ENV=local
IMITATOR_AZURE_TENANT_ID=your_tenant_id
IMITATOR_AZURE_CLIENT_ID=your_client_id
IMITATOR_AZURE_CLIENT_SECRET=your_client_secret
```

### 4. Open the imitator UI

Sign in to your app as usual, then visit:

```text
/imitator
```

Search for an Entra user, start imitation, use the app as that user, then stop from the banner or the imitator page.

That's it for UA-shaped user models (string Entra object id as the primary key). If your `users` table uses auto-increment ids, see [User Mapping](#user-mapping).

## How It Works

### Starting (or switching) imitation

1. You must already be authenticated in the host app
2. You select an Entra user from Graph search
3. The package **upserts** a local user from the Graph payload using your configured attribute map
4. On the **first** start in a session, it **snapshots** the entire current session (except the imitator bag)
5. It applies a **clean slate** (keeps the CSRF token), then `Auth::login()` as the target user
6. You are redirected to `/`

If you start imitation again while already imitating, the **original snapshot is kept**. Only the target user changes.

### Stopping imitation

1. The package restores the snapshotted session
2. It logs you back in as the original user
3. It clears the imitator session bag
4. You are redirected to `/imitator`

## Routes

Routes register only when `APP_ENV=local`, under the `imitator` prefix, with `web`, `auth`, and `imitator.local` middleware:

| Method   | URI               | Name              | Purpose                          |
|----------|-------------------|-------------------|----------------------------------|
| `GET`    | `/imitator`       | `imitator.index`  | Selection UI                     |
| `GET`    | `/imitator/search`| `imitator.search` | Graph user search (JSON)         |
| `POST`   | `/imitator`       | `imitator.store`  | Start or switch imitation        |
| `DELETE` | `/imitator`       | `imitator.destroy`| Stop and restore original session|

## User Mapping

When imitation starts, Graph user data is written to a local Eloquent model. Defaults match the UA Entra user shape. Override `config/imitator.php` for other schemas.

### Attribute sources

| Source key | Meaning |
|------------|---------|
| `id` | Entra object id |
| `name` | Display name |
| `email` | Mail, falling back to `userPrincipalName` when empty |
| `userPrincipalName` | Full UPN |
| `job_title` | Job title (nullable) |
| `username` | Lowercased local-part of the UPN (before `@`) |

`unique_by` is the local column used to find or create the user. Self-imitation is rejected by comparing that same key.

### UA-shaped users (default)

String primary key = Entra object id, plus `principal_name` / `username` / `job_title`:

```php
'user' => [
    'model' => App\Models\User::class,
    'unique_by' => 'id',
    'attributes' => [
        'id' => 'id',
        'name' => 'name',
        'email' => 'email',
        'principal_name' => 'userPrincipalName',
        'username' => 'username',
        'job_title' => 'job_title',
    ],
    'create_only' => [],
],
```

Or via environment:

```env
IMITATOR_USER_MODEL=App\Models\User
IMITATOR_USER_UNIQUE_BY=id
```

### Standard auto-increment users (match by email)

```php
'user' => [
    'model' => App\Models\User::class,
    'unique_by' => 'email',
    'attributes' => [
        'name' => 'name',
        'email' => 'email',
    ],
    'create_only' => [
        'password' => 'random',
    ],
],
```

`create_only` attributes are set only when creating a new row. Use `'random'` to generate a random string — if your model casts `password` as `hashed`, pass plain `'random'` and let the cast hash it.

```env
IMITATOR_USER_UNIQUE_BY=email
```

## Banner

While imitation is active, HTML responses get an imitating banner injected after `<body>` by default, including a Stop control.

Disable injection and place the banner yourself:

```env
IMITATOR_INJECT_BANNER=false
```

```blade
@include('imitator::banner')
```

Publish views if you want to customize them:

```bash
php artisan vendor:publish --tag=imitator-views
```

## Session Behavior

| Phase | Behavior |
|-------|----------|
| First start | Full session snapshot stored under `imitator.snapshot` (imitator bag excluded) |
| While imitating | Clean slate session + imitator metadata + target auth; CSRF `_token` preserved |
| Switch target | Original snapshot unchanged; clean slate again; login as new target |
| Stop | Snapshot restored; original user logged back in; `imitator.*` cleared |

This is the main difference from packages that exit proxy mode by flushing the session and forcing a full logout.

## Graph Search Filters

Search uses client credentials against Microsoft Graph. Results are filtered with UA defaults:

| Filter | Default |
|--------|---------|
| Exclude job titles | `STUDENT` |
| Exclude UPN suffixes | `@bama365.onmicrosoft.com` |
| Exclude UPN prefixes | `oit-`, `edas-`, `admin-`, `cs-`, `iam-` |

Override in config:

```php
'graph_filters' => [
    'exclude_job_titles' => ['STUDENT'],
    'exclude_upn_suffixes' => ['@bama365.onmicrosoft.com'],
    'exclude_upn_prefixes' => ['oit-', 'edas-', 'admin-', 'cs-', 'iam-'],
],
```

Set a list to `[]` to disable that filter category.

## Configuration Reference

Publish with `php artisan vendor:publish --tag=imitator-config`.

| Key | Default | Description |
|-----|---------|-------------|
| `user.model` | `env('IMITATOR_USER_MODEL', 'App\\Models\\User')` | Eloquent user model |
| `user.unique_by` | `env('IMITATOR_USER_UNIQUE_BY', 'id')` | Local column used to find/create the user |
| `user.attributes` | UA Entra map (see above) | Local column → Entra source key |
| `user.create_only` | `[]` | Attributes set only on create (`'random'` supported) |
| `inject_banner` | `env('IMITATOR_INJECT_BANNER', true)` | Auto-inject imitating banner into HTML responses |
| `azure.tenant_id` | `env('IMITATOR_AZURE_TENANT_ID')` | Entra tenant id |
| `azure.client_id` | `env('IMITATOR_AZURE_CLIENT_ID')` | App registration client id |
| `azure.client_secret` | `env('IMITATOR_AZURE_CLIENT_SECRET')` | App registration client secret |
| `graph_filters.exclude_job_titles` | `['STUDENT']` | Job titles excluded via `$filter` |
| `graph_filters.exclude_upn_suffixes` | `['@bama365.onmicrosoft.com']` | UPN suffixes dropped from results |
| `graph_filters.exclude_upn_prefixes` | `['oit-', 'edas-', …]` | UPN prefixes dropped from results |

## Security Notes

- The package only registers routes and banner middleware when `APP_ENV=local`. Outside local it stays inert.
- Install as **`require-dev` only**. Never ship this to shared test or production environments.
- Graph credentials use the **client credentials** flow. The Entra app needs permission to read/search users (typically application `User.Read.All`), granted admin consent.
- There is **no admin Gate**. Any authenticated user in a local app with this package installed can start and stop imitation.
- Stopping imitation restores the original session snapshot; it does not depend on the target user remaining an admin.
