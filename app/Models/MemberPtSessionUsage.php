<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPtSessionUsage extends Model
{
    protected $fillable = [
        'member_pt_package_id',
        'recorded_by',
        'sessions_used',
        'used_at',
        'confirmed_by',
        'notes',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function memberPtPackage(): BelongsTo
    {
        return $this->belongsTo(MemberPtPackage::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
