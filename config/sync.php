<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Node role
    |--------------------------------------------------------------------------
    |
    | Drives which routes register, which schedules run, and which
    | integrations are wired. 'live' is the public cloud server,
    | 'local' is the on-premise PC that owns the kiosk + biometric
    | hardware. A 'standalone' role keeps everything wired (single-node
    | deployment, sync disabled) and is the default so existing
    | installs keep behaving as they did before sync was added.
    |
    */
    'role' => env('APP_NODE_ROLE', 'standalone'),

    /*
    |--------------------------------------------------------------------------
    | Node identity
    |--------------------------------------------------------------------------
    |
    | A short string identifying THIS instance. Stamped on every outbox
    | event so the receiver can dedupe by (origin_node, event_id).
    |
    */
    'node_id' => env('NODE_ID', 'standalone'),

    /*
    |--------------------------------------------------------------------------
    | Live API target (used only when role=local)
    |--------------------------------------------------------------------------
    */
    'live_api_url' => rtrim((string) env('LIVE_API_URL', ''), '/'),
    'live_token' => env('LIVE_SYNC_TOKEN'),
    'http_timeout' => (int) env('SYNC_HTTP_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Push / pull batching
    |--------------------------------------------------------------------------
    */
    'push_batch_size' => (int) env('SYNC_PUSH_BATCH_SIZE', 200),
    'pull_batch_size' => (int) env('SYNC_PULL_BATCH_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Inbound IP allowlist (used only when role=live)
    |--------------------------------------------------------------------------
    |
    | Comma-separated list. Empty means no IP restriction (token only).
    |
    */
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SYNC_ALLOWED_IPS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Receiver registry
    |--------------------------------------------------------------------------
    |
    | Maps the entity_type wire string to the model + receiver class.
    | Each receiver knows how to upsert one entity type, resolve
    | conflicts, and produce snapshot rows. Listed in dependency order
    | for snapshot bootstrap (parents before children).
    |
    */
    'entities' => [
        // Spatie auth foundation. The Role / Permission models subclass
        // Spatie's and use SyncsToOutbox, so create / update / delete
        // each emit an outbox row through the standard Eloquent path.
        // The role_has_permissions pivot has no model; the Spatie
        // PermissionAttached / PermissionDetached event listener emits
        // those via OutboxWriter::writeRaw().
        'permission' => [
            'model' => \App\Models\Permission::class,
            'receiver' => \App\Services\Sync\Receivers\PermissionReceiver::class,
        ],
        'role' => [
            'model' => \App\Models\Role::class,
            'receiver' => \App\Services\Sync\Receivers\RoleReceiver::class,
        ],
        'role_has_permissions' => [
            'receiver' => \App\Services\Sync\Receivers\RoleHasPermissionReceiver::class,
        ],

        // Master data - no foreign-key dependencies.
        'rate_plan' => ['model' => \App\Models\RatePlan::class],
        'pt_product' => ['model' => \App\Models\PTProduct::class],
        'inventory_category' => ['model' => \App\Models\InventoryCategory::class],
        'business_profile' => [
            'model' => \App\Models\BusinessProfile::class,
            'receiver' => \App\Services\Sync\Receivers\BusinessProfileReceiver::class,
        ],

        // Users (members + employees share the table).
        'user' => ['model' => \App\Models\User::class],
        'member_profile' => ['model' => \App\Models\MemberProfile::class],
        'employee_profile' => ['model' => \App\Models\EmployeeProfile::class],

        // Spatie role / permission pivots. No Eloquent model — the
        // receiver owns both apply() and snapshot(), keying by
        // (model_uuid, model_type, role/permission name, guard) so
        // local role_id and model_id drift between nodes is irrelevant.
        'model_has_roles' => [
            'receiver' => \App\Services\Sync\Receivers\ModelHasRoleReceiver::class,
        ],
        'model_has_permissions' => [
            'receiver' => \App\Services\Sync\Receivers\ModelHasPermissionReceiver::class,
        ],

        // Subscriptions, packages, sessions.
        'member_subscription' => ['model' => \App\Models\MemberSubscription::class],
        'member_pt_package' => ['model' => \App\Models\MemberPtPackage::class],
        'member_pt_session_usage' => [
            'model' => \App\Models\MemberPtSessionUsage::class,
            'receiver' => \App\Services\Sync\Receivers\MemberPtSessionUsageReceiver::class,
        ],

        // POS + inventory.
        'inventory_item' => ['model' => \App\Models\InventoryItem::class],
        'sale_transaction' => ['model' => \App\Models\SaleTransaction::class],

        // Attendance + kiosk + biometric.
        'attendance' => ['model' => \App\Models\Attendance::class],
        'kiosk_payment' => [
            'model' => \App\Models\KioskPayment::class,
            'receiver' => \App\Services\Sync\Receivers\KioskPaymentReceiver::class,
        ],
        'hikvision_event_log' => [
            'model' => \App\Models\HikvisionEventLog::class,
            'receiver' => \App\Services\Sync\Receivers\HikvisionEventLogReceiver::class,
        ],
        'employee_biometric_session' => ['model' => \App\Models\EmployeeBiometricSession::class],

        // Payroll.
        'payroll' => ['model' => \App\Models\Payroll::class],
        'payout' => ['model' => \App\Models\Payout::class],
        'cash_advance' => ['model' => \App\Models\CashAdvance::class],
        'cash_advance_repayment' => ['model' => \App\Models\CashAdvanceRepayment::class],

        // Cash drawer (physical cash tracking).
        'cash_drawer_session' => ['model' => \App\Models\CashDrawerSession::class],
        'cash_ledger_entry' => ['model' => \App\Models\CashLedgerEntry::class],

        // Activity feeds (append-only audit, both sides keep both).
        'system_activity' => [
            'model' => \App\Models\SystemActivity::class,
            'receiver' => \App\Services\Sync\Receivers\SystemActivityReceiver::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute redaction
    |--------------------------------------------------------------------------
    |
    | Attributes scrubbed from outbox payloads regardless of model. Per-model
    | overrides live in the model's syncableAttributes() method.
    |
    */
    'redacted_attributes' => [
        'password',
        'remember_token',
        'api_token',
    ],

];
