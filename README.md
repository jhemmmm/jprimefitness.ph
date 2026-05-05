# JPrime Fitness PH

Gym management system for JPrime Fitness PH - Laravel 13 + Vue 3 SPA running POS, memberships, attendance, biometric (Hikvision), kiosk QR check-in, and payroll.

The system ships in three deployment modes:

| `APP_NODE_ROLE`          | Where it runs            | What it does                                                                                               |
| ------------------------ | ------------------------ | ---------------------------------------------------------------------------------------------------------- |
| `standalone` _(default)_ | Single server            | Everything in one place. Sync system inert. Pre-existing single-node behavior.                             |
| `live`                   | Public cloud server      | Public registration, online membership purchases (PayMongo), admin panel. Receives sync events from local. |
| `local`                  | On-premise PC at the gym | Counter POS, kiosk QR scan, Hikvision biometric. Stays functional offline. Pushes/pulls sync against live. |

If you only run one server, leave `APP_NODE_ROLE=standalone` and ignore the live/local sections below.

## Quick start (single-node / dev)

```bash
composer run setup     # install + key + migrate + npm install
composer run dev       # serve, queue, logs, Vite concurrently
composer run test      # full PHPUnit suite
```

Run a single test file or test:

```bash
php artisan test --compact tests/Feature/SomeTest.php
php artisan test --compact --filter=test_method_name
```

Build production frontend assets: `npm run build`.

> Do not run `vendor/bin/pint --dirty --format agent` in this project. Use VSCode's default PHP formatter instead.

## Stack

- PHP 8.3+, Laravel 13, PHPUnit 12 (no Pest)
- Vue 3.5, Bootstrap 5.3, Tailwind 4, Vite 8
- MySQL in production; SQLite in-memory for tests (`phpunit.xml`)
- Redis for cache and queue (default in `.env.example`)
- Spatie Permission for role/permission gating

## Project layout

- `routes/web.php` - public + `/panel/*` (auth + role-gated).
- `routes/api.php` - kiosk, biometric, PayMongo, sync endpoints.
- `routes/console.php` - schedules.
- `app/Http/Controllers/Home/*` - public-facing pages.
- `app/Http/Controllers/Panel/*` - admin/staff (Members, Sales, Inventory, Pricing, Attendance, Payroll, Settings, …).
- `app/Http/Controllers/Api/Sync/*` - `/api/sync/{push,pull,snapshot,heartbeat}` (live-only).
- `app/Services/*` - POS, payroll, audit, Hikvision, sync.
- `app/Models/*` - domain models. The shape: `User` is the identity; `MemberProfile` and `EmployeeProfile` extend it. There is no `Branch`/`Member`/`Employee` model.

`AGENTS.md` and `CLAUDE.md` carry binding rules for AI tooling: PHPUnit only, factories not seeders for tests, do not change dependencies without approval.

---

# Live ↔ Local sync

The sync system lets the gym run two synchronized copies of this app:

- A **live** server on the public internet - admins, members, the public registration form, and PayMongo webhooks all hit this one.
- A **local** PC at the gym - the Hikvision reader and kiosk QR scanner are physically attached here. Counter staff use it. Must keep working when the internet drops.

## How it works

1. Every domain model emits a row to a local `sync_outbox` table on every create/update/delete (the `SyncsToOutbox` trait does this).
2. A scheduled worker on the **local** node pushes the outbox to the **live** node's `/api/sync/push` endpoint and pulls live's outbox via `/api/sync/pull`. Live never reaches into local - local is behind NAT.
3. Receivers on the live side upsert by UUID, applying last-write-wins on `updated_at`. Conflicting updates are recorded in `system_activities` with `event=sync.conflict_dropped` so nothing is silently lost.
4. The transport is HTTPS with a single shared bearer token (`LIVE_SYNC_TOKEN`). **The local node never sees the live database credentials.**

Append-only tables (`Attendance`, `MemberPtSessionUsage`, `HikvisionEventLog`, `KioskPayment`, `SystemActivity`) are idempotent - replays of the same UUID/natural key are skipped. UUID-keyed mutable rows resolve via last-write-wins.

## What syncs and which way

| Entity                                          | Created on                  | Notes                                                                          |
| ----------------------------------------------- | --------------------------- | ------------------------------------------------------------------------------ |
| `User`, `MemberProfile`                         | either                      | Public reg → live. Counter walk-up reg → local.                                |
| `MemberSubscription`                            | either                      | Activation from PayMongo lands on live; offline counter sales create on local. |
| `RatePlan`, `PTProduct`, `BusinessProfile`      | either _(in practice live)_ | Admin config; rarely touched offline.                                          |
| `EmployeeProfile`, `Payroll`, `Payout`          | either                      |                                                                                |
| `MemberPtPackage`                               | either                      |                                                                                |
| `MemberPtSessionUsage`                          | local-dominant              | Append-only.                                                                   |
| `Attendance`                                    | local-dominant              | Kiosk + Hikvision + manual. Append-only in practice.                           |
| `HikvisionEventLog`, `EmployeeBiometricSession` | local-only                  | Hardware-bound.                                                                |
| `KioskPayment`                                  | local-only                  | Has unique `reference`.                                                        |
| `SaleTransaction`                               | either                      | UUID-keyed. POS at counter → local; online membership purchase → live.         |
| `InventoryItem`, `InventoryCategory`            | either                      |                                                                                |
| `SystemActivity`                                | both, append-only           | Each side mirrors the other's audit feed.                                      |

## What does NOT sync

- File uploads (gallery images, member photos). Member QR payloads are deterministic from `qr_payload`, so the QR image is regenerated locally and doesn't need transport.
- Payroll computation state mid-flight; final `Payroll`/`Payout` rows do.
- The biometric template itself (lives on the Hikvision device).

---

## Initial setup - LIVE node

Run on the public cloud server.

1. Deploy the codebase as you normally would (clone, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`).
2. Copy `.env.example` → `.env` and set:

    ```env
    APP_NODE_ROLE=live
    NODE_ID=live

    # Standard Laravel config (DB, mail, cache, queue, redis, etc.)
    APP_URL=https://your-public-domain
    DB_HOST=...
    DB_DATABASE=...
    DB_USERNAME=...
    DB_PASSWORD=...

    # Generate one shared sync token (see step 4) and paste here too:
    LIVE_SYNC_TOKEN=

    # Optional: restrict /api/sync/* to known caller IPs (comma-separated):
    SYNC_ALLOWED_IPS=

    # PayMongo (live keys):
    PAYMONGO_PUBLIC_KEY=...
    PAYMONGO_SECRET_KEY=...
    PAYMONGO_WEBHOOK_SECRET=...

    # Biometric helper is unused on live - leave empty:
    BIOMETRIC_HELPER_BASE_URL=
    ```

3. Run migrations:

    ```bash
    php artisan migrate --force
    ```

4. Generate the sync token (run once):

    ```bash
    php artisan sync:issue-token
    ```

    Copy the printed string. Paste it into **both** the live `.env` (`LIVE_SYNC_TOKEN=…`) and the local `.env` (same key, same value). The live middleware compares against this; the local client sends it as a `Bearer` token.

5. Restart PHP-FPM / queue workers so the new env is picked up.

6. Confirm `/api/sync/heartbeat` exists. From any machine with the token:

    ```bash
    curl -X POST https://your-public-domain/api/sync/heartbeat \
      -H "Authorization: Bearer <token>" \
      -H "Content-Type: application/json" \
      -d '{"node_id":"smoke-test"}'
    ```

    Expect `{"ok":true,...}`.

> Live does not run any sync schedules. It's purely the receiver. The default `panel:send-expiring-membership-notifications` cron still runs as before.

---

## Initial setup - LOCAL node

Run on the on-premise PC at the gym.

1. Install PHP 8.3+, MySQL (or MariaDB), Redis, Node 22+, Nginx (or Apache), and the Hikvision helper service (the on-prem helper that the Hikvision device's webhook posts to). Same stack as live.
2. Deploy the codebase. The local DB is a **separate** MySQL database - local does not connect to live's DB.
3. Copy `.env.example` → `.env` and set:

    ```env
    APP_NODE_ROLE=local
    NODE_ID=local-jprime-main          # any short string; appears in audit logs

    APP_URL=http://localhost            # or LAN address used inside the gym
    DB_HOST=127.0.0.1
    DB_DATABASE=jprimefitness_local
    DB_USERNAME=...
    DB_PASSWORD=...

    # The same token the live operator generated:
    LIVE_API_URL=https://your-public-domain
    LIVE_SYNC_TOKEN=<paste from sync:issue-token output>

    # Tunable; defaults are fine:
    SYNC_HTTP_TIMEOUT=30
    SYNC_PUSH_BATCH_SIZE=200
    SYNC_PULL_BATCH_SIZE=500

    # Hikvision helper on the LAN:
    BIOMETRIC_TOKEN=<shared with the helper service>
    BIOMETRIC_HELPER_BASE_URL=http://127.0.0.1:8081
    BIOMETRIC_HELPER_ENABLED=true

    # Kiosk:
    KIOSK_TOKEN=<shared with the kiosk frontend>

    # PayMongo: leave empty on local. Online payments are completed by the
    # public web client → live's webhook. Local picks the result up via sync.
    PAYMONGO_PUBLIC_KEY=
    PAYMONGO_SECRET_KEY=
    PAYMONGO_WEBHOOK_SECRET=
    ```

4. Run migrations on the local DB:

    ```bash
    php artisan migrate --force
    ```

5. Seed local from live (one time, requires internet):

    ```bash
    php artisan sync:bootstrap
    ```

    This walks every shared entity in dependency order, calls `/api/sync/snapshot/{type}`, upserts each row, and finally records the live outbox high-water mark in `sync_state.live_pull_cursor` so subsequent incremental pulls don't replay history. Replayable - if the local DB is wiped, re-run it.

6. Wire up the schedule. The local instance must have Laravel's scheduler running. Add to root crontab:

    ```cron
    * * * * * cd /var/www/jprimefitness.ph && php artisan schedule:run >> /dev/null 2>&1
    ```

    `routes/console.php` already adds these schedules **only** when `APP_NODE_ROLE=local`:
    - `sync:push` - every 30 seconds
    - `sync:pull` - every 30 seconds
    - `sync:heartbeat` - every minute

7. Smoke-test:

    ```bash
    php artisan sync:heartbeat   # should exit 0; check live for sync_state.last_local_seen_at
    php artisan sync:push        # drains the local outbox; expect "Pushed 0 events." on a fresh box
    php artisan sync:pull        # pulls anything live has accumulated
    ```

8. Configure the Hikvision device to POST events to `http://<local-pc-LAN-ip>/api/biometric/hikvision/callback` with header `X-Biometric-Token: <BIOMETRIC_TOKEN>`. Configure the kiosk frontend to POST to `http://<local-pc-LAN-ip>/api/kiosk/...` with header `X-Kiosk-Token: <KIOSK_TOKEN>`. Both are unchanged from single-node deployment.

---

## Token rotation

To rotate `LIVE_SYNC_TOKEN`:

1. Run `php artisan sync:issue-token` on live to generate a new value.
2. Update **live's** `.env` to the new token.
3. Update **local's** `.env` to the same new token.
4. Restart PHP-FPM (or equivalent) on both nodes.

There is a small window where one side has the old token and the other has the new - sync will 401 during that window and resume cleanly afterward (the outbox is durable; nothing is lost).

## Operations

- **Backlog visibility (live):** `select count(*) from sync_inbox group by origin_node, op;` - what local has pushed.
- **Backlog visibility (local):** `select count(*) from sync_outbox where pushed_at is null;` - events waiting to ship up.
- **Last-seen-at:** `select * from sync_state` on live shows `last_local_seen_at` (heartbeat) and `last_push_received_at`.
- **Conflict log:** `select * from system_activities where event='sync.conflict_dropped'` - every dropped update with the full incoming payload in `metadata`.
- **Outage:** if the link to live is down, local keeps appending to `sync_outbox` indefinitely. When it comes back, the next `sync:push` tick drains the backlog. Same for `sync:pull` (cursor stays put). Outbox rows are not pruned automatically; for a busy gym, vacuum old `pushed_at IS NOT NULL` rows monthly.

## Failure modes worth knowing

- **A new member registers offline:** local creates the User + MemberSubscription with a UUID and a locally-generated QR. They can immediately walk in. When the link returns, those rows push up to live. The activation email cannot send while offline; once live receives the subscription it can send the email itself if mail is configured there.
- **Online membership purchase via PayMongo while local is offline:** PayMongo webhook lands on live, subscription activates on live. Local picks it up on the next `sync:pull`. Until then, the kiosk QR scan for that member won't work - they're not in the local DB yet.
- **Concurrent edits to the same row from both sides within the same second:** last-write-wins on `updated_at`. The losing edit is preserved in `system_activities` for manual recovery. In practice this is rare for a single gym.
- **Inventory stock decrement during outage:** stock changes are applied locally as part of the sale transaction. When sync resumes, the live row receives the new stock count via last-write-wins. If admin also edited stock on live during the outage with a newer timestamp, admin's value wins - review the conflict log.

## Where things live

- Sync config: `config/sync.php`
- Outbox emission: `app/Models/Concerns/SyncsToOutbox.php` + `app/Services/Sync/OutboxWriter.php`
- Receivers: `app/Services/Sync/Receivers/*Receiver.php`
- HTTP endpoints (live-only): `app/Http/Controllers/Api/Sync/*Controller.php`
- HTTP client (local-only): `app/Services/Sync/SyncClient.php`
- Console commands: `app/Console/Commands/Sync/*Command.php`
- Token middleware: `app/Http/Middleware/VerifySyncToken.php`
- Tests: `tests/Feature/Sync/*`

## Adding a new synced entity

1. Create the model and migration as usual. Include a `uuid` column with a unique index for any entity that can be created on either side; or use a different natural key and override `syncEntityKey()` and `syncsUuid()` on the model.
2. Add `use SyncsToOutbox;` to the model.
3. Add an entry to `config/sync.php` under `entities`, in dependency order (parents before children).
4. Create a receiver under `app/Services/Sync/Receivers/`. Most can extend `AbstractReceiver` with no overrides. Append-only? Override `applyUpsert` to skip on existing UUID. Different lookup key? Override `locateExisting`.
5. Cover with a feature test under `tests/Feature/Sync/`.
