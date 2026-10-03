<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CashAdvance extends Model
{
    use HasFactory;
    use SyncsToOutbox;

    public const METHOD_CASH = 'cash';

    public const METHOD_GCASH = 'gcash';

    public const METHOD_ONLINE_PAYMENT = 'online_payment';

    protected $fillable = [
        'employee_id',
        'amount',
        'repaid_amount',
        'method',
        'reference_number',
        'released_by',
        'notes',
        'paid_at',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'repaid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    /**
     * Validation rules for how an advance is handed over (shared by manual
     * recording and request approval).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function releaseRules(): array
    {
        return [
            'method' => ['required', Rule::in([self::METHOD_CASH, self::METHOD_GCASH, self::METHOD_ONLINE_PAYMENT])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @param  array{method: string, reference_number?: ?string, notes?: ?string, paid_at?: ?string}  $data
     */
    public static function release(User $employee, float|string $amount, array $data): self
    {
        return self::create([
            'employee_id' => $employee->id,
            'amount' => $amount,
            'method' => $data['method'],
            'reference_number' => $data['reference_number'] ?? null,
            'released_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
        ]);
    }

    /**
     * Sum of unpaid, non-voided balances for an employee.
     */
    public static function outstandingFor(User $employee): float
    {
        return round((float) self::query()
            ->where('employee_id', $employee->id)
            ->outstanding()
            ->sum(DB::raw('amount - repaid_amount')), 2);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(CashAdvanceRepayment::class);
    }

    public function balance(): float
    {
        if ($this->isVoided()) {
            return 0.0;
        }

        return max(0, round((float) $this->amount - (float) $this->repaid_amount, 2));
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereNull('voided_at')->whereColumn('repaid_amount', '<', 'amount');
    }
}
