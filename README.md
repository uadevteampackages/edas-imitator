# UA Dev Team Imitator

Local-only Laravel package for imitating an Entra user in the current session.

## Install

```bash
composer require uadevteampackages/imitator --dev
```

Add a VCS repository pointing at this GitHub repo if it is not on Packagist.

Publish config (optional):

```bash
php artisan vendor:publish --tag=imitator-config
```

### Environment

```env
APP_ENV=local
IMITATOR_AZURE_TENANT_ID=
IMITATOR_AZURE_CLIENT_ID=
IMITATOR_AZURE_CLIENT_SECRET=
IMITATOR_USER_MODEL=App\Models\User
IMITATOR_INJECT_BANNER=true
```

The Azure app registration needs Graph application permission to search users (typically `User.Read.All`).

### User model

The configured user model must use the UA Entra shape:

- string primary key = Entra object id (`incrementing = false`)
- columns: `name`, `email`, `principal_name`, `username`, `job_title`

## Usage

With `APP_ENV=local` and an authenticated session, open `/imitator`.

| Route | Purpose |
| --- | --- |
| `GET /imitator` | Selection UI |
| `GET /imitator/search?q=` | Graph user search JSON |
| `POST /imitator` | Start or switch imitation |
| `DELETE /imitator` | Stop and restore original session |

### Session behavior

1. On first start, the full session is snapshotted (except the imitator bag).
2. The live session is cleared to a clean slate (CSRF token kept).
3. The selected Entra user is upserted locally and logged in.
4. Switching targets keeps the original snapshot.
5. Stop restores the snapshot and re-logs the original user.

### Banner

HTML responses get an imitating banner after `<body>` by default. Disable injection and place the banner yourself:

```blade
@include('imitator::banner')
```

```env
IMITATOR_INJECT_BANNER=false
```

## Safety

Routes and banner middleware only register when `APP_ENV=local`. Outside local, the package stays inert (no `/imitator` routes).

Never install this outside local development.
