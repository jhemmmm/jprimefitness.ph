<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditEvent extends Model
{
    use HasFactory;

    public const SUBJECT_BUSINESS_PROFILE = 'business_profile';

    public const SUBJECT_EMPLOYEE = 'employee';

    public const SUBJECT_PAYROLL = 'payroll';

    public const SUBJECT_PAYOUT = 'payout';

    public const SUBJECT_MEMBER = 'member';

    public const SUBJECT_MEMBER_SUBSCRIPTION = 'member_subscription';

    public const SUBJECT_MEMBER_PT_PACKAGE = 'member_pt_package';

    public const SUBJECT_MEMBER_PT_SESSION_USAGE = 'member_pt_session_usage';

    public const SUBJECT_ATTENDANCE = 'attendance';

    public const SUBJECT_WALK_IN = 'walk_in';

    public const SUBJECT_SALE_TRANSACTION = 'sale_transaction';

    public const SUBJECT_INVENTORY_ITEM = 'inventory_item';

    public const SUBJECT_RATE_PLAN = 'rate_plan';

    public const SUBJECT_PT_PRODUCT = 'pt_product';

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

    public function eventLabel(): string
    {
        return Str::of($this->event)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}
