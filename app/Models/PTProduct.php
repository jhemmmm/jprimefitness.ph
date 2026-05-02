<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PTProduct extends Model
{
    public const CATEGORY_SINGLE = 'single';

    public const CATEGORY_PACKAGE = 'package';

    protected $table = 'pt_products';

    protected $fillable = [
        'name',
        'slug',
        'session_count',
        'category',
        'price',
        'is_active',
        'description',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function memberPtPackages(): HasMany
    {
        return $this->hasMany(MemberPtPackage::class);
    }
}
