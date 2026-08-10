# Notifications Module

Manages in-app (database) and push (Firebase Cloud Messaging) notifications for workflow task lifecycle events — assignment, due-soon, overdue — and exposes a paginated mobile "inbox" API for reading them. It is a thin, mostly read-side module: the actual sending is triggered from the Workflows module and from two hourly scheduled jobs, not from any write endpoint of its own.

## Data Model

This module owns **no Eloquent models and no migrations**. It reads/writes exclusively through Laravel's built-in notification plumbing:

| Table / Source | Owned by | Key fields | Notes |
| --- | --- | --- | --- |
| `notifications` (Laravel's standard polymorphic table) | Main app, **not** this module — migration is `database/migrations/2026_07_22_000002_create_notifications_table.php` | `id` (uuid pk), `type` (notification FQCN), `notifiable_type`/`notifiable_id` (morphs), `data` (text/json), `read_at`, timestamps | Populated via the `Illuminate\Notifications\Notifiable` trait, used by `Modules\Auth\Models\User` (`Modules/Auth/app/Models/User.php:14`). All reads in this module go through `$user->notifications()` / `$user->unreadNotifications()`. |
| `device_tokens` | `Modules\Auth\Models\DeviceToken` (Auth module) | `user_id`, `token`, `device_name`, `platform` | Not part of this module either; used by `FcmChannel` to look up push targets via `$notifiable->routeNotificationForFcm()`. |

## Services & Notification Classes

| Class | File | Purpose |
| --- | --- | --- |
| `NotificationService` | `app/Services/NotificationService.php` | Read-side orchestration for the mobile inbox: list/paginate (shapes each row into `{id,type,title,body,data,is_read,read_at,created_at}`), mark one/all as read, unread count. **Does not extend `App\Services\BaseService`** — plain constructor DI of `NotificationRepository`, unlike the convention used elsewhere in the app. |
| `NotificationRepository` | `app/Repositories/NotificationRepository.php` | Thin wrapper over `$user->notifications()` / `$user->unreadNotifications()` Eloquent queries; filters by `data->type` via `whereJsonContains` (repository line 19). |
| `NotificationType` (enum) | `app/Enums/NotificationType.php` | String-backed: `TaskAssigned='task_assigned'`, `TaskDueSoon='task_due_soon'`, `TaskOverdue='task_overdue'`. |
| `TaskAssignedNotification` | `app/Notifications/TaskAssignedNotification.php` | Sent to a `WorkflowTask`'s assignee when the task is created. Channels: `database` + `FcmChannel`. |
| `TaskDueSoonNotification` | `app/Notifications/TaskDueSoonNotification.php` | Sent once when an open task's `due_at` falls within the next 24h. |
| `TaskOverdueNotification` | `app/Notifications/TaskOverdueNotification.php` | Sent once when an open task's `due_at` has already passed. |
| `Notifications\Channels\FcmChannel` | `app/Notifications/Channels/FcmChannel.php` | Custom Laravel notification channel. Calls the FCM v1 HTTP API directly via Laravel's `Http` client, self-minting an OAuth2 token from a service-account JWT — explicitly to avoid a heavier Firebase SDK that requires PHP 8.3+ (see file docblock, lines 11-14). |
| `TaskDueSoonNotificationJob` | `app/Jobs/TaskDueSoonNotificationJob.php` | Hourly scheduled job. Queries open `WorkflowTask` rows due within 24h, dedups, dispatches `TaskDueSoonNotification`. |
| `TaskOverdueNotificationJob` | `app/Jobs/TaskOverdueNotificationJob.php` | Hourly scheduled job. Same pattern for tasks whose `due_at` has passed. |
| `NotificationController` | `app/Http/Controllers/NotificationController.php` | Mobile inbox HTTP endpoints: `index`, `markRead`, `markAllRead`, `unreadCount`. Resolves the actor via `auth('api')->user()`. |
| `NotificationsServiceProvider` | `app/Providers/NotificationsServiceProvider.php` | Loads the `notifications` translation namespace from `lang/`; registers the two jobs on the schedule (`->hourly()->withoutOverlapping()`, lines 21-22); registers `RouteServiceProvider`. No container bindings — services resolve via Laravel's automatic constructor-injection. |
| `RouteServiceProvider` | `app/Providers/RouteServiceProvider.php` | Maps `routes/api.php` under the `api` middleware group with prefix `api`. |

## API Routes

Defined in `Modules/Notifications/routes/api.php`. Effective prefix is `/api` (from `RouteServiceProvider`) + `/v1/mobile/notifications` (declared in the route file itself, line 7).

| Method | Path | Middleware | Description |
| --- | --- | --- | --- |
| GET | `/api/v1/mobile/notifications/unread-count` | `auth:api`, `active.user` | Badge count of unread notifications |
| PATCH | `/api/v1/mobile/notifications/read-all` | `auth:api`, `active.user` | Marks all unread as read; returns `{updated_count}` |
| GET | `/api/v1/mobile/notifications` | `auth:api`, `active.user` | Paginated list (20/page), newest first; optional `?type=` filter (matches `NotificationType` values, e.g. `task_due_soon`) |
| PATCH | `/api/v1/mobile/notifications/{notification}/read` | `auth:api`, `active.user` | Marks one as read; 404 if not found or not owned by the caller |

Static routes (`/unread-count`, `/read-all`) are deliberately declared **before** the `{notification}` wildcard route to avoid ambiguous matching (comment at `routes/api.php:8`).

Not in this module, but closely related: FCM device-token registration — `POST` / `DELETE /api/v1/mobile/device-tokens` — lives in the **Auth** module (`Modules/Auth/routes/api.php:15-16`, `DeviceTokenController`). That's how tokens get into `device_tokens` for `FcmChannel` to use.

## Delivery Channels

Each notification class declares its channels via `via()`:

1. **`database`** — Laravel's built-in channel, writes to the shared `notifications` table. This is what `NotificationController`/`NotificationService`/`NotificationRepository` read back for the mobile inbox.
2. **`FcmChannel`** (custom, `app/Notifications/Channels/FcmChannel.php`) — pushes via Firebase Cloud Messaging v1 HTTP API:
   - Looks up device tokens via `$notifiable->routeNotificationForFcm()` (`Modules/Auth/app/Models/User.php:21-24`, backed by the `deviceTokens()` relation).
   - Mints its own short-lived OAuth2 access token with a hand-rolled JWT bearer grant (RS256, signed using the service account's `private_key`) — no Google SDK dependency (lines 88-126).
   - Credentials path + FCM project ID come from `config('services.firebase.*')` (`config/services.php:43-46`), read from `env('FIREBASE_CREDENTIALS')` / `env('FIREBASE_PROJECT_ID')` with hardcoded defaults — see Gotchas below, this is a real finding.
   - Auto-cleans stale tokens: on FCM error codes `UNREGISTERED` / `INVALID_ARGUMENT` / `NOT_FOUND`, deletes the offending `DeviceToken` row (lines 74-75).
   - Fails soft everywhere: missing `toFcm()` method, no device tokens, or missing credentials/token all just `return`/log a warning — a push failure never blocks the `database` write, and `FcmChannel::send()` never throws.

**Mail is not used** by this module — no notification class here implements `toMail()`, even though Postmark/Resend/SES are configured app-wide in `config/services.php` (presumably for other modules).

**Broadcast/Reverb is not used inside this module.** Note for context: `Modules\Workflows\Notifications\DynamicFlowDesignRequested` (in the **Workflows** module, not here) separately uses `database` + `broadcast` channels over the private `App.Models.User.{id}` Reverb channel. It's a parallel notification path — see Cross-Module Dependencies.

## Key Business Rules & Gotchas

- **Unusual module layout, confirmed by directory listing**: has `app/`, `routes/`, `lang/`, `storage/`, but **no `database/`, `config/`, or `tests/`** directories — a real deviation from every other module (`Auth`, `Integrations`, `KnowledgeBase`, `Team`, `Workflows` all have at least `database/` and either `config/` or module-level config elsewhere). `composer.json` still declares PSR-4 mappings for `Database\Factories`/`Database\Seeders` under `database/factories`/`database/seeders` (`composer.json:13-14`) even though neither directory exists — harmless dead scaffold from the `nwidart` module generator.
- **No `config/` directory**: Firebase config lives in the *main app's* `config/services.php`, not a module-local config file, unlike a typically-scaffolded nwidart module.
- **Committed Firebase service-account credentials — security finding.** `Modules/Notifications/storage/app/workflow-auto-notif-firebase-adminsdk-fbsvc-862073bad6.json` is tracked in git (added by commit `914c736` — "feat(notifications): configure Firebase Admin SDK credentials and services config") and is the literal hardcoded default for `config('services.firebase.credentials')` (`config/services.php:44`), so a fresh clone/deploy uses this committed file unless `FIREBASE_CREDENTIALS` is overridden — and `.env.example` has no `FIREBASE_CREDENTIALS`/`FIREBASE_PROJECT_ID` entries, so there's no documented "bring your own" path either. `FcmChannel::getAccessToken()` (lines 88-126) reads `client_email` and `private_key` straight out of this file to sign OAuth2 JWTs, so it is a functioning credential file by construction, not a placeholder. **A second copy of the same filename is also tracked at the module root** (`Modules/Notifications/workflow-auto-notif-firebase-adminsdk-fbsvc-862073bad6.json`, 2399 bytes vs. 2397 bytes for the `storage/app` copy) — that copy isn't referenced by any code found in this reading and looks like an accidental leftover from initial setup. Recommend rotating the key and removing both files from git history.
- **Scheduled jobs need the scheduler actually running.** `TaskDueSoonNotificationJob`/`TaskOverdueNotificationJob` are registered `->hourly()->withoutOverlapping()` in `NotificationsServiceProvider::boot()` (lines 18-23), which requires `php artisan schedule:work` (or cron → `schedule:run`). The documented `composer run dev` script (`composer.json:56-59`) only runs `serve` + `queue:listen` + `pail` + `npm run dev` — **no scheduler** — so these jobs silently never fire under the standard local dev workflow.
- **Dedup logic references a legacy FQCN.** Both jobs check for an already-sent notification by matching `type = <CurrentClass>::class` **or** the literal string `'Modules\Workflows\Notifications\TaskDueSoonNotification'` / `'...TaskOverdueNotification'` (`TaskDueSoonNotificationJob.php:42-44`, `TaskOverdueNotificationJob.php:42-44`). These notification classes evidently used to live in the Workflows module and were moved here; the OR-clause guards against re-notifying based on rows written under the old class name before the move.
- **Lang fallback to `workflows::` is effectively dead code.** Every notification's `toArray()`/`toFcm()` tries `notifications::notifications.*` first and falls back to `workflows::notifications.*` only if the key resolves to itself (e.g. `TaskAssignedNotification.php:36-39`). `Modules/Workflows/lang/{en,ar}/notifications.php` is byte-identical to `Modules/Notifications/lang/{en,ar}/notifications.php` — a leftover from the same pre-move era. Since this module's own translations always load successfully (registered in `NotificationsServiceProvider::boot()`), the fallback branch is unreachable in practice.
- **Writes bypass the Service/Repository layer entirely.** `NotificationService`/`NotificationRepository` are read-only (list, mark-read, unread-count) and are used only by `NotificationController`. Notifications are *sent* by calling `$user->notify(new XyzNotification(...))` directly from `WorkflowTaskObserver::created()` (Workflows module) and from the two scheduled jobs — there is no `NotificationService::send()`-style write method in this module.
- **`?type=` filters on the JSON `data.type` key, not the `notifications.type` column.** `NotificationRepository::getForUser()` uses `whereJsonContains('data->type', $type)` (line 19), matching `NotificationType` enum values like `task_assigned`. The table's own `type` column stores Laravel's default value — the notification class FQCN — and is not what the API filters on (it's what the dedup checks above match against, though).
- **Not every row in the shared `notifications` table follows this module's `{type,title,body}` contract.** `Modules\Workflows\Notifications\DynamicFlowDesignRequested` (`Modules/Workflows/app/Notifications/DynamicFlowDesignRequested.php`) writes to the same `database` channel/table directly, but its `toArray()` payload has no `type` key — so it would surface via the mobile inbox `index` endpoint with `"type": null` and can never match a `?type=` filter. As of this reading it also isn't dispatched from anywhere in the codebase (repo-wide grep for the class name matches only its own definition) — it appears to be wired up for the Dynamic Flow epic but not yet triggered by any code path.

## Cross-Module Dependencies

- **Workflows → Notifications**: `Modules\Workflows\Observers\WorkflowTaskObserver::created()` (`Modules/Workflows/app/Observers/WorkflowTaskObserver.php`) calls `$task->assignee->notify(new TaskAssignedNotification($task))` whenever a `WorkflowTask` is created with a non-null `assignee_id`. Registered via `WorkflowTask::observe(WorkflowTaskObserver::class)` in `Modules/Workflows/app/Providers/WorkflowsServiceProvider.php:75`.
- **Notifications → Workflows**: `TaskDueSoonNotificationJob` and `TaskOverdueNotificationJob` query `Modules\Workflows\Models\WorkflowTask` directly (`status='open'`, `due_at` windows) — this module reaches into Workflows' models rather than Workflows pushing data to it.
- **Notifications → Auth**: every notification/job operates on `Modules\Auth\Models\User` (the `Notifiable`). `FcmChannel` depends on `User::routeNotificationForFcm()` and `Modules\Auth\Models\DeviceToken`. Device-token registration endpoints live in the Auth module, not here (see API Routes).
- **Workflows has its own, separate notification** (`Modules\Workflows\Notifications\DynamicFlowDesignRequested`) that bypasses this module entirely, writing straight to the `database` channel plus a Reverb `broadcast` — see Gotchas.
- No other module (`Integrations`, `KnowledgeBase`, `Team`) references `Modules\Notifications\*` as of this reading (repo-wide grep for the namespace).

## Testing

**No `tests/` directory inside `Modules/Notifications/`.** All coverage for this module lives in the main app's suite at `tests/Feature/NotificationApiTest.php` (679 lines), which exercises:

- The full mobile inbox API — list/paginate/filter-by-type/ownership-isolation, mark-one-read (incl. idempotency and cross-user 404), mark-all-read, unread-count.
- Both scheduled jobs — send condition, skip conditions (too far out / not yet due / task completed), and dedup-on-rerun — invoked directly as `(new TaskDueSoonNotificationJob)->handle()` rather than through the scheduler.
- `WorkflowTaskObserver` — sends on task creation with an assignee, skips without one.
- Notification payload/i18n shape (`type`, `title`, `body`, `task_id`, `instance_id`).

Note the test file's scope is broader than this module alone — it also contains Auth-module device-token tests (`B-21`) and unrelated mobile-documents tests (`B-28`/`B-29`) in the same class, so the test-file boundary does not match the module boundary. Run with `composer test` or `php artisan test --filter=NotificationApiTest`.
