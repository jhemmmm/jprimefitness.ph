<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAdvance extends Model
{
    const STATUS_REQUESTED = 'requested';
    const STATUS_APPROVED = 'approved';
    const STATUS_RELEASED = 'released';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'branch_id',
        'amount',
        'remaining_amount',
        'status',
        'notes',
        'requested_at',
        'approved_at',
        'approved_by',
        'released_at',
        'released_by',
        'cancelled_at',
        'cancelled_by',
        'paid_at',
        'audit_data',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'requested_at'     => 'datetime',
        'approved_at'      => 'datetime',
        'released_at'      => 'datetime',
        'cancelled_at'     => 'datetime',
        'paid_at'          => 'datetime',
        'audit_data'       => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function appendAuditEvent(array $event): void
    {
        $auditData = $this->audit_data ?? [];
        $auditData[] = array_filter($event, fn($value) => $value !== null && $value !== '');

        $this->audit_data = array_values($auditData);
    }
}
