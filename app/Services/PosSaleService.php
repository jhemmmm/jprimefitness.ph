<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\MemberPtPackage;
use App\Models\MemberSubscription;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Models\WalkIn;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosSaleService
{
    public function __construct(private BranchCashLedgerService $branchCashLedgerService) {}

    /**
     * @return array{
     *     inventory_items: array<int, array<string, mixed>>,
     *     membership_rates: array<int, array<string, mixed>>,
     *     pt_rates: array<int, array<string, mixed>>
     * }
     */
    public function branchContext(Branch $branch): array
    {
        $inventoryItems = InventoryItem::query()
            ->with('category:id,name')
            ->where('branch_id', $branch->id)
            ->where('status', InventoryItem::STATUS_ACTIVE)
            ->where('quantity', '>', 0)
            ->whereNotNull('selling_price')
            ->orderBy('name')
            ->get()
            ->map(function (InventoryItem $item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category_name' => $item->category?->name,
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                    'selling_price' => round((float) $item->selling_price, 2),
                ];
            })
            ->values()
            ->all();

        $membershipRates = $branch->ratePlans()
            ->where('rate_plans.is_active', true)
            ->wherePivot('is_active', true)
            ->orderBy('duration_days')
            ->orderBy('name')
            ->get()
            ->map(function (RatePlan $ratePlan) {
                return [
                    'id' => $ratePlan->id,
                    'name' => $ratePlan->name,
                    'duration_days' => $ratePlan->duration_days,
                    'description' => $ratePlan->description,
                    'price' => round((float) $ratePlan->pivot->price, 2),
                    'manager_commission_rate' => round((float) ($ratePlan->pivot->manager_commission_rate ?? 0), 2),
                ];
            })
            ->values()
            ->all();

        $ptRates = $branch->ptProducts()
            ->where('pt_products.is_active', true)
            ->wherePivot('is_active', true)
            ->orderBy('session_count')
            ->orderBy('name')
            ->get()
            ->map(function (PTProduct $ptProduct) {
                return [
                    'id' => $ptProduct->id,
                    'name' => $ptProduct->name,
                    'session_count' => $ptProduct->session_count,
                    'description' => $ptProduct->description,
                    'price' => round((float) $ptProduct->pivot->price, 2),
                ];
            })
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
    public function processSale(Branch $branch, array $data, User $processedBy): SaleTransaction
    {
        return match ($data['type']) {
            SaleTransaction::TYPE_INVENTORY => $this->sellInventory($branch, $data, $processedBy),
            SaleTransaction::TYPE_MEMBERSHIP => $this->sellMembership($branch, $data, $processedBy),
            SaleTransaction::TYPE_PT_PACKAGE => $this->sellPtPackage($branch, $data, $processedBy),
            SaleTransaction::TYPE_WALK_IN => $this->sellWalkIn($branch, $data, $processedBy),
            default => throw ValidationException::withMessages([
                'type' => ['The selected sale type is invalid.'],
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sellInventory(Branch $branch, array $data, User $processedBy): SaleTransaction
    {
        return DB::transaction(function () use ($branch, $data, $processedBy) {
            $lines = $this->normalizeInventoryLines($data);
            $inventoryItemIds = $lines->pluck('inventory_item_id')->map(fn ($id) => (int) $id)->values();

            $items = InventoryItem::query()
                ->with('category:id,name')
                ->whereIn('id', $inventoryItemIds)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lineItems = [];
            $saleTotal = 0.0;

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
                'branch_id' => $branch->id,
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

            $this->branchCashLedgerService->syncSaleTransaction($saleTransaction);

            return $saleTransaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sellMembership(Branch $branch, array $data, User $processedBy): SaleTransaction
    {
        return DB::transaction(function () use ($branch, $data, $processedBy) {
            $ratePlan = $branch->ratePlans()
                ->where('rate_plans.id', (int) $data['rate_plan_id'])
                ->where('rate_plans.is_active', true)
                ->wherePivot('is_active', true)
                ->first();

            if (! $ratePlan) {
                throw ValidationException::withMessages([
                    'rate_plan_id' => ['The selected membership plan is not available for this branch.'],
                ]);
            }

            $member = $this->resolveMember($branch, $data);
            $member->branches()->syncWithoutDetaching([$branch->id]);

            $saleTotal = round((float) $ratePlan->pivot->price, 2);
            $managerProcessedSale = $processedBy->hasRole('manager');
            $managerCommissionRate = $managerProcessedSale
                ? round((float) ($ratePlan->pivot->manager_commission_rate ?? 0), 2)
                : 0.0;
            $managerCommissionAmount = $managerProcessedSale
                ? MemberSubscription::calculateCommissionAmount($saleTotal, $managerCommissionRate)
                : 0.0;
            $subscription = $member->sellMembershipPlan($ratePlan->id, $data['start_date'], [
                'branch_id' => $branch->id,
                'sold_price' => $saleTotal,
                'manager_id' => $managerProcessedSale ? $processedBy->id : null,
                'manager_commission_rate' => $managerCommissionRate,
                'manager_commission_amount' => $managerCommissionAmount,
                'manager_commission_status' => $managerProcessedSale
                    ? MemberSubscription::COMMISSION_STATUS_EARNED
                    : MemberSubscription::COMMISSION_STATUS_UNASSIGNED,
                'manager_commission_earned_at' => $managerProcessedSale ? $data['sold_at'] : null,
            ]);
            $payment = $this->resolvePayment($saleTotal, $data);

            $saleTransaction = SaleTransaction::create([
                'branch_id' => $branch->id,
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
                    'manager_commission' => [
                        'manager_id' => $managerProcessedSale ? $processedBy->id : null,
                        'manager_name' => $managerProcessedSale ? $processedBy->name : null,
                        'commission_rate' => $managerCommissionRate,
                        'commission_amount' => $managerCommissionAmount,
                        'status' => $subscription->manager_commission_status,
                    ],
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

            $this->branchCashLedgerService->syncSaleTransaction($saleTransaction);

            return $saleTransaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sellPtPackage(Branch $branch, array $data, User $processedBy): SaleTransaction
    {
        return DB::transaction(function () use ($branch, $data, $processedBy) {
            $ptProduct = $branch->ptProducts()
                ->where('pt_products.id', (int) $data['pt_product_id'])
                ->where('pt_products.is_active', true)
                ->wherePivot('is_active', true)
                ->first();

            if (! $ptProduct) {
                throw ValidationException::withMessages([
                    'pt_product_id' => ['The selected PT package is not available for this branch.'],
                ]);
            }

            $member = $this->resolveMember($branch, $data);
            $member->branches()->syncWithoutDetaching([$branch->id]);

            $soldPrice = round((float) $ptProduct->pivot->price, 2);
            $coachCommissionRate = round((float) ($ptProduct->pivot->coach_commission_rate ?? 40), 2);
            $payment = $this->resolvePayment($soldPrice, $data);

            $package = $member->memberPtPackages()->create([
                'branch_id' => $branch->id,
                'pt_product_id' => $ptProduct->id,
                'sold_price' => $soldPrice,
                'coach_commission_rate' => $coachCommissionRate,
                'coach_commission_amount' => MemberPtPackage::calculateCommissionAmount($soldPrice, $coachCommissionRate),
                'coach_id' => null,
                'total_sessions' => $ptProduct->session_count,
                'remaining_sessions' => $ptProduct->session_count,
                'assigned_at' => $data['assigned_at'],
                'expires_at' => $data['expires_at'] ?? null,
                'coach_commission_status' => MemberPtPackage::defaultCommissionStatus(null),
                'notes' => $data['notes'] ?? null,
                'created_by' => $processedBy->id,
            ]);

            $saleTransaction = SaleTransaction::create([
                'branch_id' => $branch->id,
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

            $this->branchCashLedgerService->syncSaleTransaction($saleTransaction);

            return $saleTransaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sellWalkIn(Branch $branch, array $data, User $processedBy): SaleTransaction
    {
        return DB::transaction(function () use ($branch, $data, $processedBy) {
            $ratePlan = null;

            if (! empty($data['rate_plan_id'])) {
                $ratePlan = $branch->ratePlans()
                    ->where('rate_plans.id', (int) $data['rate_plan_id'])
                    ->where('rate_plans.is_active', true)
                    ->wherePivot('is_active', true)
                    ->first();

                if (! $ratePlan) {
                    throw ValidationException::withMessages([
                        'rate_plan_id' => ['The selected walk-in plan is not available for this branch.'],
                    ]);
                }
            }

            $walkIn = WalkIn::create([
                'branch_id' => $branch->id,
                'rate_plan_id' => $ratePlan?->id,
                'served_by' => $processedBy->id,
                'name' => $data['customer_name'],
                'phone' => $data['customer_phone'] ?? null,
                'amount_paid' => round((float) $data['amount_paid'], 2),
                'payment_method' => $data['payment_method'],
                'visited_at' => $data['sold_at'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->branchCashLedgerService->syncWalkIn($walkIn);
            $payment = $this->resolvePayment((float) $walkIn->amount_paid, $data);

            return SaleTransaction::create([
                'branch_id' => $branch->id,
                'member_id' => null,
                'type' => SaleTransaction::TYPE_WALK_IN,
                'total' => round((float) $walkIn->amount_paid, 2),
                'payment_method' => $payment['payment_method'],
                'processed_by' => $processedBy->id,
                'sold_at' => $data['sold_at'],
                'customer_name' => $walkIn->name,
                'item_name' => $ratePlan?->name ?: 'Walk-in',
                'details' => [
                    'walk_in_id' => $walkIn->id,
                    'rate_plan_id' => $ratePlan?->id,
                    'phone' => $walkIn->phone,
                    'line_items' => [[
                        'name' => $ratePlan?->name ?: 'Walk-in',
                        'description' => $walkIn->phone,
                        'quantity' => 1,
                        'unit' => 'entry',
                        'unit_price' => round((float) $walkIn->amount_paid, 2),
                        'line_total' => round((float) $walkIn->amount_paid, 2),
                    ]],
                    'subtotal' => round((float) $walkIn->amount_paid, 2),
                    'payment' => $payment,
                    'notes' => $data['notes'] ?? null,
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveMember(Branch $branch, array $data): User
    {
        if (($data['member_mode'] ?? null) === 'existing') {
            $member = User::role('member')
                ->whereKey((int) $data['member_id'])
                ->whereHas('branches', fn ($query) => $query->where('branches.id', $branch->id))
                ->first();

            if (! $member) {
                throw ValidationException::withMessages([
                    'member_id' => ['The selected member could not be found for this branch.'],
                ]);
            }

            return $member;
        }

        $member = User::create([
            'name' => $data['customer_name'],
            'email' => $data['customer_email'] ?? null,
            'phone' => $data['customer_phone'] ?? null,
            'password' => Hash::make(Str::random(24)),
            'status' => User::STATUS_ACTIVE,
        ]);

        $member->assignRole('member');
        $member->branches()->sync([$branch->id]);
        $member->profile()->create([]);

        return $member;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{inventory_item_id:int, quantity:int}>
     */
    private function normalizeInventoryLines(array $data): Collection
    {
        $lines = collect(Arr::wrap($data['items'] ?? []))
            ->filter(fn ($line) => filled($line['inventory_item_id'] ?? null) || filled($line['quantity'] ?? null))
            ->map(function ($line): array {
                return [
                    'inventory_item_id' => (int) ($line['inventory_item_id'] ?? 0),
                    'quantity' => (int) ($line['quantity'] ?? 0),
                ];
            });

        if ($lines->isEmpty() && ! empty($data['inventory_item_id'])) {
            $lines = collect([[
                'inventory_item_id' => (int) $data['inventory_item_id'],
                'quantity' => (int) ($data['quantity'] ?? 0),
            ]]);
        }

        return $lines
            ->groupBy('inventory_item_id')
            ->map(function (Collection $group, int $inventoryItemId): array {
                return [
                    'inventory_item_id' => (int) $inventoryItemId,
                    'quantity' => (int) $group->sum('quantity'),
                ];
            })
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
