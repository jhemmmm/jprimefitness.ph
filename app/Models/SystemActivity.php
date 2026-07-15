<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SystemActivity extends Model
{
    use HasFactory, SyncsToOutbox;

    public const SUBJECT_BUSINESS_PROFILE = 'business_profile';

    public const SUBJECT_EMPLOYEE = 'employee';

    public const SUBJECT_PAYROLL = 'payroll';

    public const SUBJECT_PAYOUT = 'payout';

    public const SUBJECT_MEMBER = 'member';

    public const SUBJECT_MEMBER_SUBSCRIPTION = 'member_subscription';

    public const SUBJECT_MEMBER_PT_PACKAGE = 'member_pt_package';

    public const SUBJECT_MEMBER_PT_SESSION_USAGE = 'member_pt_session_usage';

    public const SUBJECT_ATTENDANCE = 'attendance';

    public const SUBJECT_SALE_TRANSACTION = 'sale_transaction';

    public const SUBJECT_INVENTORY_ITEM = 'inventory_item';

    public const SUBJECT_RATE_PLAN = 'rate_plan';

    public const SUBJECT_PT_PRODUCT = 'pt_product';

    public const SUBJECT_CASH_DRAWER_SESSION = 'cash_drawer_session';

    public const SUBJECT_CASH_LEDGER_ENTRY = 'cash_ledger_entry';

    public const SUBJECT_SYNC = 'sync';

    public const EVENT_SYNC_CONFLICT_DROPPED = 'sync.conflict_dropped';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'subject_label',
        'event',
        'title',
        'message',
        'actor_user_id',
        'actor_name',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function syncableAttributes(): array
    {
        $attributes = $this->decodeJsonCastAttributes($this->getAttributes());

        // Sync conflict rows embed the rejected payload in metadata. That
        // payload already lives in the originating outbox event, so we'd
        // be syncing it twice (and the embedding would be re-embedded
        // ad infinitum on subsequent re-conflicts).
        if (isset($attributes['metadata']) && is_array($attributes['metadata']) && isset($attributes['metadata']['incoming_payload'])) {
            unset($attributes['metadata']['incoming_payload']);
        }

        return $attributes;
    }

    public function eventLabel(): string
    {
        return Str::of($this->event)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}
