# Auth Module

Handles tenant self-registration, JWT-based login/session/logout, and FCM device-token registration. Owns the `User` and `Tenant` Eloquent models that every other module (Team, Workflows, Integrations, KnowledgeBase, Notifications) authenticates against and foreign-keys into.

## Data Model

| Model | Key fields/relationships | Migration file |
|---|---|---|
| `User` (`app/Models/User.php`) | `first_name, last_name, position, name, email, password, tenant_id, role (Role enum), is_active (bool)`. `belongsTo(Tenant)` (:65-68), `hasMany(DeviceToken)` (:16-19). Implements `JWTSubject`; uses `HasApiTokens` (Sanctum) + `Notifiable` — **no `HasFactory`**. | Base: `2026_02_13_125709_create_users_table.php`. Extended by `2026_02_13_160001_update_users_table_for_tenant_registration.php` (`first_name`, `last_name`, `tenant_id` FK cascade-delete, `role`) and `2026_04_26_100000_add_position_to_users_table.php`. **`is_active` is added by `Modules/Team/database/migrations/2026_04_22_130000_add_is_active_to_users_table.php` — not owned by Auth** (see Gotchas). |
| `Tenant` (`app/Models/Tenant.php`) | `business_name, business_type (BusinessType enum, cast at :21-26)`. `hasMany(User)` (:31-34). | `2026_02_13_110000_create_tenants_table.php` |
| `DeviceToken` (`app/Models/DeviceToken.php`) | `user_id, token (DB-unique), device_name, platform`. `belongsTo(User)`. One row per logged-in device, used for FCM push (`User::routeNotificationForFcm()`, `User.php:21-24`). | `2026_07_22_000001_create_device_tokens_table.php` |

Note: `personal_access_tokens` table (`2026_02_13_111537_create_personal_access_tokens_table.php`) exists because `User` uses Sanctum's `HasApiTokens`, but no Auth route actually authenticates through the `sanctum` guard — see Gotchas.

Both `role` (users table) and `business_type` (tenants table) are plain `string` DB columns with no DB-level check constraint; enum integrity (`Role`, `BusinessType`) is enforced only at the PHP/validation layer via Eloquent casts and `Rule::enum()`.

## Services & Repositories

| Class | File | Purpose |
|---|---|---|
| `TenantRegistrationService` | `app/Services/TenantRegistrationService.php` | `register()` (:27-48): checks for an existing email (:29-31, redundant with `RegisterTenantRequest`'s `unique:users,email` except for registration races), creates a `Tenant` named `"{first_name}'s business"` (:33-36), creates a `User` with `role = Role::BusinessOwner` (:38-45). Extends `App\Services\BaseService` and sets `$repositoryClass = TenantRepository::class`, but **doesn't actually rely on `__call` delegation** — both `TenantRepository` and `UserRepository` are injected directly in the constructor (:19-22) and called explicitly. So it's a nominal, not functional, adopter of the delegation convention. |
| `TenantRepository` | `app/Repositories/TenantRepository.php` | Single method `create()` (:12-15) → `Tenant::create()`. |
| `UserRepository` | `app/Repositories/UserRepository.php` | Single method `create()` (:12-19) → hashes password via `Hash::make`, derives `name` from `first_name`+`last_name`, then `User::create()`. **Note:** `Modules\Team\Repositories\UserRepository` is a different class with the same basename — don't confuse the two when grepping. |

All three are bound as singletons in `AuthServiceProvider::register()` (`app/Providers/AuthServiceProvider.php:43-45`).

Controllers manage transactions manually (`DB::beginTransaction()/commit()/rollBack()`), matching the repo-wide convention: `RegisterController::__invoke()` (`app/Http/Controllers/RegisterController.php:25-39`). `SessionController` and `DeviceTokenController` don't use transactions — each performs a single write.

## API Routes

`RouteServiceProvider::mapApiRoutes()` wraps `routes/api.php` in `Route::middleware('api')->prefix('api')->name('api.')`; `routes/api.php` adds `Route::prefix('v1')`. Final paths are `/api/v1/...`, final route names are `api.<name>`.

| Method | Path | Middleware | Description |
|---|---|---|---|
| POST | `/api/v1/register` | none (`@unauthenticated`) | `RegisterController` — creates tenant + BusinessOwner user. Returns 201 + `RegisterSuccessResource`. **No token is issued at registration** — client must call `/login` next. |
| POST | `/api/v1/login` | none (`@unauthenticated`) | `SessionController@store` — returns JWT + `LoginSuccessResource`. |
| GET | `/api/v1/me` | `auth:api`, `active.user` | `SessionController@me` (:59-85) — profile + role + tenant + first team membership, as a raw `response()->json()` array (different shape from `LoginSuccessResource`, not a Resource class). |
| POST | `/api/v1/logout` | `auth:api`, `active.user` | `SessionController@destroy` (:90-99) — `guard->logout()`; JWT is blacklisted (`JWT_BLACKLIST_ENABLED` defaults `true`, `config/jwt.php:223`). |
| POST | `/api/v1/mobile/device-tokens` | `auth:api`, `active.user` | `DeviceTokenController@store` (:21-39) — `updateOrCreate` keyed **only on `token`** (not `user_id`+`token`); `platform` must be `ios`\|`android`. |
| DELETE | `/api/v1/mobile/device-tokens` | `auth:api`, `active.user` | `DeviceTokenController@destroy` (:44-56) — deletes by `user_id`+`token`. |

`active.user` → `App\Http\Middleware\EnsureActiveApiUser`; `role` → `App\Http\Middleware\EnsureUserHasRole` (aliased in `bootstrap/app.php:19-20`). **Both live at the app root, not inside the Auth module**, even though they operate entirely on Auth's `User`/`Role`. Auth's own routes use `active.user` but never `role`.

## Enums

| Enum | Values | Used for |
|---|---|---|
| `Role` (`app/Enums/Role.php`) | `BusinessOwner, Manager, Admin, Employee` — backed string enum, no methods. | Authorization. Consumed app-wide by `app/Http/Middleware/EnsureUserHasRole.php`, and by the **root-level** `app/Providers/AuthServiceProvider.php` which grants `BusinessOwner` an automatic pass on every `Gate::before()` check (bypasses all policies, e.g. `WorkflowInstanceAttachmentPolicy`). Not validated with `Rule::enum()` anywhere inside the Auth module itself (only assigned internally); other modules' Form Requests (e.g. Team's `CreateUserRequest`) validate it against user input. |
| `BusinessType` (`app/Enums/BusinessType.php`) | 12 cases: `SaaS, SoftwareHouse, DigitalAgency, TechStartup, ITConsultingFirm, CloudServiceProvider, CybersecurityCompany, GameDevelopmentStudio, IoTSolutionsProvider, DevOpsServicesCompany, ARVRDevelopmentCompany, RoboticsAndAutomationFirm`. | Tenant's business category, validated with `Rule::enum(BusinessType::class)` in `RegisterTenantRequest` (:30). Ships a `labels()` static method (:23-39) with human-readable display strings for the registration dropdown. |

## Key Business Rules & Gotchas

- **No `LoginController` exists.** Login is `SessionController::store()`; the same controller also owns `me()` and `destroy()` (logout) — i.e. it's a session-lifecycle controller, not a login-only one. (The `.cursor/rules/auth-module.mdc` refers to a "LoginController" — see summary discrepancy.)
- **CAPTCHA is not actually enforced.** `RegisterTenantRequest` requires `captcha_token` to be a non-empty string, but the `verifyCaptcha()` call inside `withValidator()` is commented out (`app/Http/Requests/RegisterTenantRequest.php:61-63`). The full Google reCAPTCHA `siteverify` implementation (:70-87) is dead code. Tests literally pass `'captcha_token' => 'test-token'`.
- **Password strength is enforced on registration but not login.** `PasswordStrengthRule` (lower+upper+digit+special-char) is active in `RegisterTenantRequest` (:36) but commented out in `LoginRequest` (:29) — login only requires `min:8`.
- **Duplicate-email registration surfaces as HTTP 500, not 422/409.** `RegistrationException` extends plain `Exception` (no `render()`/`Responsable`), and `bootstrap/app.php`'s `withExceptions()` hook is empty (no custom mapping) — so when `TenantRegistrationService::register()` throws `emailAlreadyTaken()`, `RegisterController`'s catch block (:35-39) only rolls back and rethrows; Laravel's default handler then renders a generic 500. In the common case this path isn't even reached because `RegisterTenantRequest` already has `unique:users,email` (:29) — the service's manual check only guards a registration race. `RegistrationException::tenantCreationFailed()` (:14-17) is defined but **never thrown anywhere** — dead code.
- **`is_active` is a Team-module column; Auth defends against its absence.** It's added by `Modules/Team/.../2026_04_22_130000_add_is_active_to_users_table.php`, not by any Auth migration, yet `SessionController::store()` reads/writes it directly, guarded by `array_key_exists('is_active', $user->getAttributes())` and `Schema::hasColumn('users','is_active')` (:27, :33) so login still works if Team's migration hasn't run. `EnsureActiveApiUser` (app-level) repeats the disabled-account check on every subsequent authenticated request.
- **Disabled-account check runs before password verification.** In `SessionController::store()` (:27-31), the `is_active === false` check runs against the user fetched by email alone, before `guard->attempt()` verifies the password — so any password against a disabled account's email returns "Your account is disabled" (403) rather than a generic invalid-credentials response, revealing account status pre-authentication.
- **JWT claims carry `tenant`, not `role`.** `User::getJWTCustomClaims()` (:81-94) embeds only `tenant: {id, business_name, business_type}`. `role` is not in the token payload — `EnsureUserHasRole` and the `Gate::before()` BusinessOwner bypass both re-resolve a fresh `User` model (via the JWT `sub` claim) from the DB on every request rather than reading decoded claims.
- **`User::getManagedTeam()` (:96-103) breaks the model's own relationship convention** — unlike every other relation method here, it executes eagerly (`->first()`) and returns `Team|null` instead of a relation builder, and silently returns `null` for any non-Manager role.
- **Sanctum is installed but not the active guard.** `User` uses `HasApiTokens` and a `personal_access_tokens` table exists, but `config/auth.php` binds the `api` guard to the `jwt` driver (:46-49), and the only `auth:sanctum` route in the app is the unrelated default-scaffold `GET /user` in root `routes/api.php`. Despite the top-level CLAUDE.md's "JWT + Sanctum" phrasing, Auth's actual flows are 100% JWT.
- **Stray root-level `database/factories/UserFactory.php` targets a nonexistent `App\Models\User`** (there is no `app/Models/` directory in this codebase). Harmless in practice because `Modules\Auth\Models\User` doesn't use `HasFactory`, so `User::factory()` isn't callable — tests create users/tenants directly via `::create()` or by driving `/register` + `/login`.
- **`Team` module dependency is real but undeclared.** `Modules/Auth/module.json` has no `"requires"` entry, yet `User::getManagedTeam()` references `Modules\Team\Models\Team` and `SessionController::me()` references `Modules\Team\Models\TeamMembership`. Conversely, Team's `Team`/`TeamMembership` models `belongsTo` Auth's `User`/`Tenant` — the two modules are mutually coupled without either declaring it.
- **Device-token uniqueness is global, not per-user.** `DeviceTokenController::store()` keys `updateOrCreate` only on `token` (:29-36); since `token` has a DB-level `unique()` constraint, if the same FCM token were ever sent while authenticated as a different user, the existing row's `user_id` would silently be reassigned rather than erroring.
- **No email-verification flow.** `EventServiceProvider::configureEmailVerification()` (`app/Providers/EventServiceProvider.php:26`) is an empty stub; despite `email_verified_at` existing on the base `users` migration, nothing sets or checks it.
- **`routes/web.php` is empty** (just `<?php`) and `resources/views/index.blade.php` is unused "Hello World" scaffold — both are template leftovers from `nwidart/laravel-modules`, not functional.
- **Tenant scoping is not centrally enforced by Auth.** There is no global Eloquent scope tying queries to `tenant_id`; every other module is individually responsible for filtering by the authenticated user's `tenant_id`.

## Cross-Module Dependencies

**Exposes to other modules:**
- `Modules\Auth\Models\User` — the sole authenticatable model (`config/auth.php:3,72`, `AUTH_MODEL` env override). Foreign-keyed by Team (`manager_id`, `team_memberships.user_id`), and referenced throughout Workflows/KnowledgeBase/Integrations/Notifications for ownership/assignment.
- `Modules\Auth\Models\Tenant` — the tenancy root; `tenant_id` FK pattern used across Team/Workflows/Integrations/KnowledgeBase tables.
- `Modules\Auth\Enums\Role` — consumed by `app/Http/Middleware/EnsureUserHasRole.php`, root `app/Providers/AuthServiceProvider.php` (Gate bypass), and other modules' Form Requests/services for role checks.
- The `auth:api` (JWT) guard and `active.user`/`role` middleware aliases — the standard protection used on virtually every other module's routes.

**Depends on:**
- `Modules\Team\Models\Team` / `TeamMembership` (see Gotchas — undeclared dependency).
- App-root `App\Http\Middleware\EnsureActiveApiUser` / `EnsureUserHasRole` — middleware Auth's routes require but doesn't own.
- `is_active` column — owned/migrated by the Team module.
- `tymon/jwt-auth` (guard driver; `User::getJWTIdentifier()`/`getJWTCustomClaims()`), `laravel/sanctum` (`HasApiTokens`, effectively unused), `dedoc/scramble` (reads `@unauthenticated` docblocks for OpenAPI generation).

## Testing

- `Modules/Auth/tests/Feature/` and `Modules/Auth/tests/Unit/` are **empty** (only `.gitkeep`) despite `composer.json` mapping `Modules\Auth\Tests\` → `tests/` — don't look here for coverage.
- Real coverage lives at the repo root: `tests/Feature/AuthApiTest.php` — `test_register_creates_business_owner_and_tenant` and `test_login_returns_jwt_token_for_registered_user`. Uses `RefreshDatabase` + `Tests\TestCase`, drives the HTTP endpoints end-to-end (no factories involved).
- `tests/Feature/RoleMiddlewareTest.php` exercises the app-level `EnsureUserHasRole` middleware against this module's `Role` enum.
- Other modules' Feature tests (`TeamManagementApiTest.php`, `WorkflowManagementApiTest.php`, `IntegrationsApiTest.php`, etc.) bootstrap their own tenant/user fixtures by calling `Tenant::create()`/`User::create()` directly, or via locally-defined per-test-class helpers (e.g. `registerAndLoginOwner()`, `createTenantUser()` in `tests/Feature/TeamManagementApiTest.php`) that drive `/api/v1/register` + `/api/v1/login`.
- `Modules/Auth/database/factories/` is empty (`.gitkeep` only) — no `UserFactory`/`TenantFactory` for this module; the unrelated root-level `database/factories/UserFactory.php` is stale scaffold (see Gotchas).
