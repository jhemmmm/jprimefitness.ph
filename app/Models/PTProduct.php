<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PTProduct extends Model
{
    const CATEGORY_SINGLE = 'single';

    const CATEGORY_PACKAGE = 'package';

    protected $table = 'pt_products';

    protected $fillable = [
        'name',
        'slug',
        'session_count',
        'category',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_pt_prices', 'pt_product_id', 'branch_id')
            ->withPivot(['price', 'coach_commission_rate', 'is_active', 'effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function memberPtPackages(): HasMany
    {
        return $this->hasMany(MemberPtPackage::class);
    }
}
