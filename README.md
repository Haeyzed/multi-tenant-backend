# Multi-Tenant SaaS API Boilerplate

Laravel 13 + Stancl Tenancy v4 **database-per-tenant** API boilerplate.

## Stack

- PHP 8.4 / Laravel 13
- Stancl Tenancy v4 (`dev-master`, pinned commit)
- Laravel Sanctum
- Spatie Query Builder, Permission
- Dedoc Scramble (OpenAPI)

## Architecture

| Context | Purpose |
|---|---|
| **Central** | Landlord API: auth, tenant provisioning |
| **Tenant** | Tenant API: auth, users (RBAC), app resources |

Tenants are identified by **domain**. Each tenant gets its own database, migrated and seeded on create.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure APP_URL to your central domain (Herd: https://multi-tenant-backend.test)
php artisan migrate
php artisan db:seed
```

Central seeder creates `admin@central.test` / `password`.

## Quick start

### 1. Authenticate (central)

```bash
curl -X POST "$APP_URL/api/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@central.test","password":"password","device_name":"cli"}'
```

### 2. Provision a tenant

```bash
curl -X POST "$APP_URL/api/tenants" \
  -H "Authorization: Bearer {CENTRAL_TOKEN}" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Acme","domain":"acme.multi-tenant-backend.test"}'
```

Map the tenant domain in Herd/DNS, then:

```bash
curl -X POST "https://acme.multi-tenant-backend.test/api/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@tenant.test","password":"password"}'
```

Default tenant admin is seeded as `admin@tenant.test` / `password` with the `admin` role.

## API documentation

| API | UI | OpenAPI JSON |
|---|---|---|
| Central | `/docs/central` | `/docs/central.json` |
| Tenant | `/docs/tenant` | `/docs/tenant.json` |

Docs are open in `local` / `testing`. Restrict via the `viewApiDocs` gate in production.

## Response envelope

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {},
  "meta": null,
  "errors": null
}
```

## Key paths

```text
app/Models/Central          Central models (e.g. User)
app/Models/Tenant           Tenant models (e.g. User)
app/Http/Controllers/Central
app/Http/Controllers/Tenant
app/Services                Business logic
database/migrations         Central migrations
database/migrations/tenant  Tenant migrations
routes/api.php              Central API
routes/tenant/api.php       Tenant API
```

## Tenancy notes

- Central domain comes from `APP_URL` (`tenancy.identification.central_domains`)
- Bootstrappers isolate database, cache, filesystem, queue, and sessions
- Stancl is pinned: `dev-master#553f57a8…` — bump deliberately before production releases
- For local `migrate:fresh`, consider enabling `tenancy.database.drop_tenant_databases_on_migrate_fresh`

## Testing

```bash
php artisan test --compact
```

PHPUnit uses SQLite in-memory with `CACHE_STORE=file` (array cache is incompatible with Stancl’s cache bootstrapper).

## Security defaults

- Sanctum bearer tokens for central and tenant APIs
- Auth endpoints throttled (5/min); authenticated APIs throttled (60/min)
- Spatie Permission on tenant users (`admin` / `member`)
- Production passwords require mixed case, numbers, symbols, and uncompromised checks
- Health endpoints omit sensitive DB details in production
