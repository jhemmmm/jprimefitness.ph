<?php

namespace App\Services;

use App\Models\SystemActivity;
use App\Models\InventoryItem;
use App\Models\MemberPtPackage;
use App\Models\MemberSubscription;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosSaleService
{
    public function __construct(
        private InventoryStockAlertService $inventoryStockAlertService,
        private SystemActivityService $systemActivityService,
    ) {}

    /**
     * @return array{
     *     inventory_items: array<int, array<string, mixed>>,
     *     membership_rates: array<int, array<string, mixed>>,
     *     pt_rates: array<int, array<string, mixed>>
     * }
     */
    public function context(): array
    {
        $inventoryItems = InventoryItem::query()
            ->with('category:id,name')
            ->where('status', InventoryItem::STATUS_ACTIVE)
            ->where('quantity', '>', 0)
            ->whereNotNull('selling_price')
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category_name' => $item->category?->name,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'selling_price' => round((float) $item->selling_price, 2),
            ])
            ->values()
            ->all();

        $membershipRates = RatePlan::query()
            ->where('is_active', true)
            ->whereNotNull('price')
            ->orderBy('duration_days')
            ->orderBy('name')
            ->get()
            ->map(fn (RatePlan $ratePlan) => [
                'id' => $ratePlan->id,
                'name' => $ratePlan->name,
                'duration_days' => $ratePlan->duration_days,
                'description' => $ratePlan->description,
                'price' => round((float) $ratePlan->price, 2),
            ])
            ->values()
            ->all();

        $ptRates = PTProduct::query()
            ->where('is_active', true)
            ->whereNotNull('price')
            ->orderBy('session_count')
            ->orderBy('name')
            ->get()
            ->map(fn (PTProduct $ptProduct) => [
                'id' => $ptProduct->id,
                'name' => $ptProduct->name,
                'session_count' => $ptProduct->session_count,
                'description' => $ptProduct->description,
                'price' => round((float) $ptProduct->price, 2),
            ])
            ->values()
            ->all();

        return [
            'inventory_items' => $inventoryItems,
            'membership_rates' => $membershipRates,
            'pt_rates' => $ptRates,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function processSale(array $data, User $processedBy): SaleTransaction
    {
        return match ($data['type']) {
            SaleTransaction::TYPE_INVENTORY => $this->sellInventory($data, $processedBy),
            SaleTransaction::TYPE_MEMBERSHIP => $this->sellMembership($data, $processedBy),
            SaleTransaction::TYPE_PT_PACKAGE => $this->sellPtPackage($data, $processedBy),
            SaleTransaction::TYPE_WALK_IN => $this->sellWalkIn($data, $processedBy),
            default => throw ValidationException::withMessages([
                'type' => ['The selected sale type is invalid.'],
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sellInventory(array $data, User $processedBy): SaleTransaction
    {
        return DB::transaction(function () use ($data, $processedBy) {
            $lines = $this->normalizeInventoryLines($data);
            $inventoryItemIds = $lines->pluck('inventory_item_id')->map(fn ($id) => (int) $id)->values();

            $items = InventoryItem::query()
                ->with('category:id,name')
                ->whereIn('id', $inventoryItemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lineItems = [];
            $saleTotal = 0.0;
            $stockDeductions = [];

            foreach ($lines as $index => $line) {
                $item = $items->get((int) $line['inventory_item_id']);

                if (! $item || $item->status !== InventoryItem::STATUS_ACTIVE) {
                    throw ValidationException::withMessages([
                        "items.{$index}.inventory_item_id" => ['The selected inventory item is not available for sale.'],
                    ]);
                }

                if ($item->selling_price === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.inventory_item_id" => ['The selected inventory item does not have a selling price.'],
                    ]);
                }

                $quantity = (int) $line['quantity'];
                $availableQuantity = round((float) $item->quantity, 2);

                if ($quantity > $availableQuantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ['The requested quantity exceeds the available stock.'],
                    ]);
                }

                $unitPrice = round((float) $item->selling_price, 2);
                $lineTotal = round($quantity * $unitPrice, 2);
                $saleTotal += $lineTotal;

                $item->quantity = round($availableQuantity - $quantity, 2);
                $item->save();
                $this->inventoryStockAlertService->sync($item);
                $stockDeductions[] = [
                    'item' => $item->fresh('category:id,name'),
                    'deducted_quantity' => $quantity,
                    'remaining_quantity' => (float) $item->quantity,
                ];

                $lineItems[] = [
                    'inventory_item_id' => $item->id,
                    'inventory_category_id' => $item->inventory_category_id,
                    'name' => $item->name,
                    'description' => $item->category?->name,
                    'quantity' => $quantity,
                    'unit' => $item->unit,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $saleTotal = round($saleTotal, 2);
            $payment = $this->resolvePayment($saleTotal, $data);

            $saleTransaction = SaleTransaction::create([
                'member_id' => null,
                'type' => SaleTransaction::TYPE_INVENTORY,
                'total' => $saleTotal,
                'payment_method' => $payment['payment_method'],
                'processed_by' => $processedBy->id,
                'sold_at' => $data['sold_at'],
                'customer_name' => null,
                'item_name' => count($lineItems) === 1 ? $lineItems[0]['name'] : count($lineItems).' inventory items',
                'details' => [
                    'line_items' => $lineItems,
                    'subtotal' => $saleTotal,
                    'payment' => $payment,
                    'notes' => $data['notes'] ?? null,
                ],
            ]);

            $saleCause = $this->recordSaleTransactionSystemActivity($saleTransaction, $processedBy);

            foreach ($stockDeductions as $stockDeduction) {
                $this->recordInventoryStockDeductionSystemActivity(
                    $stockDeduction['item'],
                    $stockDeduction['deducted_quantity'],
                    $stockDeduction['remaining_quantity'],
                    $processedBy,
                    $saleCause,
                    $saleTransaction->sold_at,
                );
            }

            return $saleTransaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sellMembership(array $data, User $processedBy): SaleTransaction
    {
        return DB::transaction(function () use ($data, $processedBy) {
            $ratePlan = RatePlan::query()
                ->whereKey((int) $data['rate_plan_id'])
                ->where('is_active', true)
                ->whereNotNull('price')
                ->first();

            if (! $ratePlan) {
                throw ValidationException::withMessages([
                    'rate_plan_id' => ['The selected membership plan is not available.'],
                ]);
            }

            $memberResult = $this->resolveMember($data);
            /** @var User $member */
            $member = $memberResult['member'];
            $memberCreated = (bool) $memberResult['created'];
            $saleTotal = round((float) $ratePlan->price, 2);

            $subscription = $member->sellMembershipPlan($ratePlan->id, $data['start_date'], [
                'sold_price' => $saleTotal,
            ]);

            $payment = $this->resolvePayment($saleTotal, $data);

            $saleTransaction = SaleTransaction::create([
                'member_id' => $member->id,
                'type' => SaleTransaction::TYPE_MEMBERSHIP,
                'total' => $saleTotal,
                'payment_method' => $payment['payment_method'],
                'processed_by' => $processedBy->id,
                'sold_at' => $data['sold_at'],
                'customer_name' => $member->name,
                'item_name' => $ratePlan->name,
                'details' => [
                    'rate_plan_id' => $ratePlan->id,
                    'subscription_id' => $subscription->id,
                    'duration_days' => $ratePlan->duration_days,
                    'start_date' => $data['start_date'],
                    'line_items' => [[
                        'name' => $ratePlan->name,
                        'description' => $ratePlan->description,
                        'quantity' => 1,
                        'unit' => 'plan',
                        'unit_price' => $saleTotal,
                        'line_total' => $saleTotal,
                    ]],
                    'subtotal' => $saleTotal,
                    'payment' => $payment,
                    'notes' => $data['notes'] ?? null,
                ],
            ]);

            $saleCause = $this->recordSaleTransactionSystemActivity($saleTransaction, $processedBy);

            if ($memberCreated) {
                $this->recordMemberCreatedSystemActivity($member->fresh(), $processedBy, $saleCause, $saleTransaction->sold_at);
            }

            $this->recordMembershipCreatedSystemActivity(
                $subscription->fresh(['ratePlan', 'member']),
                $processedBy,
                $saleCause,
                $saleTransaction->sold_at,
            );
            return $saleTransaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sellPtPackage(array $data, User $processedBy): SaleTransaction
    {
        return DB::transaction(function () use ($data, $processedBy) {
            $ptProduct = PTProduct::query()
                ->whereKey((int) $data['pt_product_id'])
                ->where('is_active', true)
                ->whereNotNull('price')
                ->first();

            if (! $ptProduct) {
                throw ValidationException::withMessages([
                    'pt_product_id' => ['The selected PT package is not available.'],
                ]);
            }

            $memberResult = $this->resolveMember($data);
            /** @var User $member */
            $member = $memberResult['member'];
            $memberCreated = (bool) $memberResult['created'];
            $soldPrice = round((float) $ptProduct->price, 2);
            $payment = $this->resolvePayment($soldPrice, $data);

            $package = $member->memberPtPackages()->create([
                'pt_product_id' => $ptProduct->id,
                'sold_price' => $soldPrice,
                'coach_id' => null,
                'total_sessions' => $ptProduct->session_count,
                'remaining_sessions' => $ptProduct->session_count,
                'assigned_at' => $data['assigned_at'],
                'expires_at' => $data['expires_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $processedBy->id,
            ]);

            $saleTransaction = SaleTransaction::create([
                'member_id' => $member->id,
                'type' => SaleTransaction::TYPE_PT_PACKAGE,
                'total' => $soldPrice,
                'payment_method' => $payment['payment_method'],
                'processed_by' => $processedBy->id,
                'sold_at' => $data['sold_at'],
                'customer_name' => $member->name,
                'item_name' => $ptProduct->name,
                'details' => [
                    'member_pt_package_id' => $package->id,
                    'pt_product_id' => $ptProduct->id,
                    'session_count' => $ptProduct->session_count,
                    'assigned_at' => $data['assigned_at'],
                    'expires_at' => $data['expires_at'] ?? null,
                    'line_items' => [[
                        'name' => $ptProduct->name,
                        'description' => $ptProduct->session_count.' sessions',
                        'quantity' => 1,
                        'unit' => 'package',
                        'unit_price' => $soldPrice,
                        'line_total' => $soldPrice,
                    ]],
                    'subtotal' => $soldPrice,
                    'payment' => $payment,
                    'notes' => $data['notes'] ?? null,
                ],
            ]);

            $saleCause = $this->recordSaleTransactionSystemActivity($saleTransaction, $processedBy);

            if ($memberCreated) {
                $this->recordMemberCreatedSystemActivity($member->fresh(), $processedBy, $saleCause, $saleTransaction->sold_at);
            }

            $this->recordPtPackageSystemActivity(
                $package->fresh(['ptProduct', 'coach', 'member']),
                'created',
                $processedBy,
                $saleCause,
                $saleTransaction->sold_at,
            );
            return $saleTransaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sellWalkIn(array $data, User $processedBy): SaleTransaction
    {
        return DB::transaction(function () use ($data, $processedBy) {
            $ratePlan = null;

            if (! empty($data['rate_plan_id'])) {
                $ratePlan = RatePlan::query()
                    ->whereKey((int) $data['rate_plan_id'])
                    ->where('is_active', true)
                    ->first();

                if (! $ratePlan) {
                    throw ValidationException::withMessages([
                        'rate_plan_id' => ['The selected walk-in plan is not available.'],
                    ]);
                }
            }

            $saleTotal = round((float) $data['amount_paid'], 2);
            $payment = $this->resolvePayment($saleTotal, $data);

            $saleTransaction = SaleTransaction::create([
                'member_id' => null,
                'type' => SaleTransaction::TYPE_WALK_IN,
                'total' => $saleTotal,
                'payment_method' => $payment['payment_method'],
                'processed_by' => $processedBy->id,
                'sold_at' => $data['sold_at'],
                'customer_name' => $data['customer_name'],
                'item_name' => $ratePlan?->name ?: 'Walk-in',
                'details' => [
                    'rate_plan_id' => $ratePlan?->id,
                    'phone' => $data['customer_phone'] ?? null,
                    'line_items' => [[
                        'name' => $ratePlan?->name ?: 'Walk-in',
                        'description' => $data['customer_phone'] ?? null,
                        'quantity' => 1,
                        'unit' => 'entry',
                        'unit_price' => $saleTotal,
                        'line_total' => $saleTotal,
                    ]],
                    'subtotal' => $saleTotal,
                    'payment' => $payment,
                    'notes' => $data['notes'] ?? null,
                ],
            ]);

            $this->recordSaleTransactionSystemActivity($saleTransaction, $processedBy);

            return $saleTransaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveMember(array $data): array
    {
        if (($data['member_mode'] ?? null) === 'existing') {
            $member = User::role('member')
                ->whereKey((int) $data['member_id'])
                ->first();

            if (! $member) {
                throw ValidationException::withMessages([
                    'member_id' => ['The selected member could not be found.'],
                ]);
            }

            return [
                'member' => $member,
                'created' => false,
            ];
        }

        $member = User::create([
            'name' => $data['customer_name'],
            'email' => $data['customer_email'] ?? null,
            'phone' => $data['customer_phone'] ?? null,
            'password' => Hash::make(Str::random(24)),
            'status' => User::STATUS_ACTIVE,
        ]);

        $member->assignRole('member');
        $member->profile()->create([]);

        return [
            'member' => $member,
            'created' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recordSaleTransactionSystemActivity(SaleTransaction $saleTransaction, User $processedBy): array
    {
        $snapshot = $this->saleTransactionSystemActivitySnapshot($saleTransaction);
        $causedBy = $this->systemActivityService->causedBy(
            SystemActivity::SUBJECT_SALE_TRANSACTION,
            $saleTransaction->id,
            'created',
            $snapshot,
            [
                'member_id' => $saleTransaction->member_id,
            ],
        );

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_SALE_TRANSACTION,
            $saleTransaction->id,
            'created',
            $snapshot,
            [],
            $processedBy->id,
            $processedBy->name,
            $saleTransaction->sold_at ?? now(),
        );

        return $causedBy;
    }

    private function recordMemberCreatedSystemActivity(
        User $member,
        User $processedBy,
        array $causedBy,
        DateTimeInterface|string|null $occurredAt = null,
    ): void
    {
        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER,
            $member->id,
            'created',
            $this->memberSystemActivitySnapshot($member),
            [
                'caused_by' => $causedBy,
            ],
            $processedBy->id,
            $processedBy->name,
            $occurredAt ?? now(),
        );
    }

    private function recordMembershipCreatedSystemActivity(
        MemberSubscription $subscription,
        User $processedBy,
        array $causedBy,
        DateTimeInterface|string|null $occurredAt = null,
    ): void
    {
        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
            $subscription->id,
            'created',
            $this->membershipSystemActivitySnapshot($subscription),
            [
                'caused_by' => $causedBy,
            ],
            $processedBy->id,
            $processedBy->name,
            $occurredAt ?? now(),
        );
    }

    private function recordPtPackageSystemActivity(
        MemberPtPackage $package,
        string $event,
        User $processedBy,
        ?array $causedBy = null,
        DateTimeInterface|string|null $occurredAt = null,
    ): void {
        $metadata = $causedBy ? ['caused_by' => $causedBy] : [];

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER_PT_PACKAGE,
            $package->id,
            $event,
            $this->ptPackageSystemActivitySnapshot($package),
            $metadata,
            $processedBy->id,
            $processedBy->name,
            $occurredAt ?? now(),
        );
    }

    private function recordInventoryStockDeductionSystemActivity(
        InventoryItem $item,
        int $deductedQuantity,
        float $remainingQuantity,
        User $processedBy,
        array $causedBy,
        DateTimeInterface|string|null $occurredAt = null,
    ): void {
        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_INVENTORY_ITEM,
            $item->id,
            'stock_deducted',
            $this->inventorySystemActivitySnapshot($item),
            [
                'deducted_quantity' => $deductedQuantity,
                'remaining_quantity' => round($remainingQuantity, 2),
                'caused_by' => $causedBy,
            ],
            $processedBy->id,
            $processedBy->name,
            $occurredAt ?? now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function saleTransactionSystemActivitySnapshot(SaleTransaction $saleTransaction): array
    {
        return [
            'id' => $saleTransaction->id,
            'member_id' => $saleTransaction->member_id,
            'type' => $saleTransaction->type,
            'customer_name' => $saleTransaction->customer_name,
            'item_name' => $saleTransaction->item_name,
            'payment_method' => $saleTransaction->payment_method,
            'total' => round((float) $saleTransaction->total, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memberSystemActivitySnapshot(User $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'status' => $member->status,
            'email' => $member->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipSystemActivitySnapshot(MemberSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'member_id' => $subscription->user_id,
            'member_name' => $subscription->member?->name ?? 'Unknown Member',
            'rate_plan_id' => $subscription->rate_plan_id,
            'rate_plan_name' => $subscription->ratePlan?->name,
            'status' => $subscription->status,
            'start_date' => $subscription->start_date?->toDateString(),
            'end_date' => $subscription->end_date?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ptPackageSystemActivitySnapshot(MemberPtPackage $package): array
    {
        return [
            'id' => $package->id,
            'member_id' => $package->user_id,
            'member_name' => $package->member?->name ?? 'Unknown Member',
            'pt_product_id' => $package->pt_product_id,
            'product_name' => $package->ptProduct?->name,
            'coach_id' => $package->coach_id,
            'coach_name' => $package->coach?->name,
            'total_sessions' => $package->total_sessions,
            'remaining_sessions' => $package->remaining_sessions,
            'assigned_at' => $package->assigned_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventorySystemActivitySnapshot(InventoryItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'category_name' => $item->category?->name,
            'quantity' => round((float) $item->quantity, 2),
            'unit' => $item->unit,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{inventory_item_id:int, quantity:int}>
     */
    private function normalizeInventoryLines(array $data): Collection
    {
        $lines = collect(Arr::wrap($data['items'] ?? []))
            ->filter(fn ($line) => filled($line['inventory_item_id'] ?? null) || filled($line['quantity'] ?? null))
            ->map(fn ($line): array => [
                'inventory_item_id' => (int) ($line['inventory_item_id'] ?? 0),
                'quantity' => (int) ($line['quantity'] ?? 0),
            ]);

        if ($lines->isEmpty() && ! empty($data['inventory_item_id'])) {
            $lines = collect([[
                'inventory_item_id' => (int) $data['inventory_item_id'],
                'quantity' => (int) ($data['quantity'] ?? 0),
            ]]);
        }

        return $lines
            ->groupBy('inventory_item_id')
            ->map(fn (Collection $group, int $inventoryItemId): array => [
                'inventory_item_id' => (int) $inventoryItemId,
                'quantity' => (int) $group->sum('quantity'),
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{payment_method:string, amount_received:float, change_amount:float, reference:?string}
     */
    private function resolvePayment(float $saleTotal, array $data): array
    {
        $paymentMethod = (string) $data['payment_method'];
        $amountReceived = array_key_exists('amount_received', $data)
            && $data['amount_received'] !== null
            && $data['amount_received'] !== ''
            ? round((float) $data['amount_received'], 2)
            : round($saleTotal, 2);

        if ($amountReceived < round($saleTotal, 2)) {
            throw ValidationException::withMessages([
                'amount_received' => ['Amount received must cover the sale total.'],
            ]);
        }

        $reference = trim((string) ($data['payment_reference'] ?? ''));
        $changeAmount = $paymentMethod === SaleTransaction::PAYMENT_METHOD_CASH
            ? round(max($amountReceived - round($saleTotal, 2), 0), 2)
            : 0.0;

        return [
            'payment_method' => $paymentMethod,
            'amount_received' => $amountReceived,
            'change_amount' => $changeAmount,
            'reference' => $reference !== '' ? $reference : null,
        ];
    }
}
