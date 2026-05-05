<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\KioskPayment;
use App\Models\MemberProfile;
use App\Models\MemberSubscription;
use App\Models\SaleTransaction;
use App\Models\SystemActivity;
use App\Services\MemberActivationService;
use App\Services\MembershipQrService;
use App\Services\PosSaleService;
use App\Services\SystemActivityService;
use App\Support\SaleTransactionPresenter;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;

class SalesController extends Controller
{
    /**
     * Create a new sales controller instance.
     *
     * @return void
     */
    public function __construct(
        private MemberActivationService $memberActivationService,
        private MembershipQrService $membershipQrService,
        private PosSaleService $posSaleService,
        private SystemActivityService $systemActivityService,
    ) {}

    /**
     * Display the sales page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.sales', [
            'businessProfile' => BusinessProfile::current(),
        ]);
    }

    /**
     * Return sales page context data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function context(): JsonResponse
    {
        return response()->json([
            'options' => $this->posSaleService->context(),
        ]);
    }

    /**
     * Return sales transaction history.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::in([
                SaleTransaction::TYPE_INVENTORY,
                SaleTransaction::TYPE_MEMBERSHIP,
                SaleTransaction::TYPE_PT_PACKAGE,
                SaleTransaction::TYPE_WALK_IN,
            ])],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $history = SaleTransaction::query()
            ->with(['member:id,name', 'processedBy:id,name'])
            ->when($request->type, fn ($query) => $query->where('type', $request->type))
            ->when($request->search, function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($inner) use ($search) {
                    $inner->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%");
                });
            })
            ->when($request->date_from, fn ($query) => $query->whereDate('sold_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($query) => $query->whereDate('sold_at', '<=', $request->date_to))
            ->orderByDesc('sold_at')
            ->paginate(15)
            ->through(fn (SaleTransaction $transaction) => $this->transformTransaction($transaction))
            ->withQueryString();

        return response()->json([
            'transactions' => $history,
        ]);
    }

    /**
     * Create a sales transaction.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateStorePayload($request);
        $transaction = $this->posSaleService->processSale($data, auth()->user())
            ->load(['member:id,name', 'processedBy:id,name']);

        return response()->json($this->transformTransaction($transaction), 201);
    }

    /**
     * Download a sales receipt.
     *
     * @return \Illuminate\Contracts\Support\Responsable
     */
    public function receipt(SaleTransaction $saleTransaction): Responsable
    {
        $fileName = 'sale-receipt-'.$saleTransaction->id.'.pdf';

        return Pdf::view('panel.sales.receipt', $this->receiptPayload($saleTransaction))
            ->driver('dompdf')
            ->format('a4')
            ->margins(8, 8, 8, 8)
            ->download($fileName);
    }

    /**
     * Return the merged feed of pending counter payments (kiosk walk-ins
     * and on-site member registrations).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pendingPayments(): JsonResponse
    {
        $now = Carbon::now();

        $walkIns = KioskPayment::query()
            ->whereNull('paymongo_payment_intent_id')
            ->where('status', KioskPayment::STATUS_PENDING)
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn (KioskPayment $payment) => $this->serializePendingWalkIn($payment))
            ->all();

        $memberships = MemberSubscription::query()
            ->with(['member.profile', 'ratePlan'])
            ->where('status', MemberSubscription::STATUS_PAUSED)
            ->where('pending_payment_method', MemberSubscription::PENDING_PAYMENT_ON_SITE)
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn (MemberSubscription $subscription) => $this->serializePendingMembership($subscription))
            ->all();

        $payments = collect(array_merge($walkIns, $memberships))
            ->sortBy('created_at')
            ->values()
            ->all();

        return response()->json(['payments' => $payments]);
    }

    /**
     * Confirm an on-site membership registration payment.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmPendingMembership(Request $request, MemberSubscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(SaleTransaction::supportedPaymentMethods())],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureMembershipPendingOnSite($subscription);

        $actor = auth()->user();

        $transaction = DB::transaction(function () use ($subscription, $data, $actor) {
            $activated = $this->memberActivationService->activate($subscription, [
                'source' => 'panel_pending_payment_confirmation',
                'payment_method' => $data['payment_method'],
            ]);

            return $this->posSaleService->recordOnSiteMembershipSale(
                $activated,
                $actor,
                $data['payment_method'],
                $data['payment_reference'] ?? null,
            );
        });

        $transaction->load(['member:id,name', 'processedBy:id,name']);

        return response()->json(SaleTransactionPresenter::panelArray($transaction), 201);
    }

    /**
     * Cancel an on-site membership registration awaiting payment.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelPendingMembership(MemberSubscription $subscription): JsonResponse
    {
        $this->ensureMembershipPendingOnSite($subscription);

        $subscription->update([
            'status' => MemberSubscription::STATUS_CANCELLED,
            'pending_payment_method' => null,
        ]);

        $actor = auth()->user();

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
            $subscription->id,
            'cancelled',
            [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'plan_name' => $subscription->ratePlan?->name,
            ],
            ['source' => 'panel_pending_payment_cancellation'],
            $actor?->id,
            $actor?->name,
            now(),
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Return a membership sale QR code payload.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function membershipQr(SaleTransaction $saleTransaction): JsonResponse
    {
        abort_unless($saleTransaction->type === SaleTransaction::TYPE_MEMBERSHIP, 404);

        $subscriptionId = (int) data_get($saleTransaction->details, 'subscription_id');
        abort_unless($subscriptionId > 0, 404);

        $subscription = MemberSubscription::query()
            ->whereKey($subscriptionId)
            ->where('user_id', $saleTransaction->member_id)
            ->firstOrFail();

        return response()->json($this->membershipQrService->modalPayload($subscription));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStorePayload(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([
                SaleTransaction::TYPE_INVENTORY,
                SaleTransaction::TYPE_MEMBERSHIP,
                SaleTransaction::TYPE_PT_PACKAGE,
                SaleTransaction::TYPE_WALK_IN,
            ])],
            'payment_method' => ['required', Rule::in(SaleTransaction::supportedPaymentMethods())],
            'sold_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'member_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'pt_product_id' => ['nullable', 'integer', 'exists:pt_products,id'],
            'start_date' => ['nullable', 'date'],
            'assigned_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:assigned_at'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        $errors = [];

        if ($validated['type'] === SaleTransaction::TYPE_INVENTORY) {
            $items = collect($validated['items'] ?? [])
                ->filter(fn ($item) => filled($item['inventory_item_id'] ?? null) || filled($item['quantity'] ?? null))
                ->values();

            if ($items->isEmpty() && ! empty($validated['inventory_item_id'])) {
                $items = collect([[
                    'inventory_item_id' => $validated['inventory_item_id'],
                    'quantity' => $validated['quantity'] ?? null,
                ]]);
            }

            if ($items->isEmpty()) {
                $errors['items'][] = 'Add at least one inventory item to sell.';
            }

            foreach ($items as $index => $item) {
                if (empty($item['inventory_item_id'])) {
                    $errors["items.{$index}.inventory_item_id"][] = 'Select an inventory item to sell.';
                }

                if (empty($item['quantity'])) {
                    $errors["items.{$index}.quantity"][] = 'Enter the quantity to sell.';
                }
            }
        }

        if (in_array($validated['type'], [SaleTransaction::TYPE_MEMBERSHIP, SaleTransaction::TYPE_PT_PACKAGE], true)) {
            if (empty($validated['member_id'])) {
                $errors['member_id'][] = 'Select a member for this sale.';
            }
        }

        if ($validated['type'] === SaleTransaction::TYPE_MEMBERSHIP) {
            if (empty($validated['rate_plan_id'])) {
                $errors['rate_plan_id'][] = 'Select a membership plan.';
            }

            if (empty($validated['start_date'])) {
                $errors['start_date'][] = 'Select the membership start date.';
            }
        }

        if ($validated['type'] === SaleTransaction::TYPE_PT_PACKAGE) {
            if (empty($validated['pt_product_id'])) {
                $errors['pt_product_id'][] = 'Select a PT package.';
            }

            if (empty($validated['assigned_at'])) {
                $errors['assigned_at'][] = 'Select the PT package sale date.';
            }
        }

        if ($validated['type'] === SaleTransaction::TYPE_WALK_IN) {
            if (empty($validated['customer_name'])) {
                $errors['customer_name'][] = 'Enter the walk-in customer name.';
            }

            if (! array_key_exists('amount_paid', $validated) || $validated['amount_paid'] === null || $validated['amount_paid'] === '') {
                $errors['amount_paid'][] = 'Enter the walk-in payment amount.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $validated['sold_at'] = $validated['sold_at'] ?? now()->toDateTimeString();

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformTransaction(SaleTransaction $transaction): array
    {
        return SaleTransactionPresenter::panelArray($transaction);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function receiptLineItems(SaleTransaction $saleTransaction): array
    {
        $details = $saleTransaction->details ?? [];
        $lineItems = Arr::wrap($details['line_items'] ?? []);

        if ($lineItems !== []) {
            return array_map(function (array $lineItem): array {
                return [
                    'name' => $lineItem['name'] ?? 'Item',
                    'description' => $lineItem['description'] ?? null,
                    'quantity' => (float) ($lineItem['quantity'] ?? 1),
                    'unit' => $lineItem['unit'] ?? null,
                    'unit_price' => round((float) ($lineItem['unit_price'] ?? 0), 2),
                    'line_total' => round((float) ($lineItem['line_total'] ?? 0), 2),
                ];
            }, $lineItems);
        }

        return [[
            'name' => $saleTransaction->item_name ?: 'Sale',
            'description' => null,
            'quantity' => 1,
            'unit' => null,
            'unit_price' => round((float) $saleTransaction->total, 2),
            'line_total' => round((float) $saleTransaction->total, 2),
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptPayload(SaleTransaction $saleTransaction): array
    {
        $saleTransaction->loadMissing([
            'member:id,name,email,phone',
            'processedBy:id,name',
        ]);

        $details = $saleTransaction->details ?? [];
        $discount = data_get($details, 'discount');
        $subtotal = data_get($details, 'subtotal');

        return [
            'saleTransaction' => $saleTransaction,
            'businessProfile' => BusinessProfile::current(),
            'receiptNumber' => $saleTransaction->receiptNumber(),
            'lineItems' => $this->receiptLineItems($saleTransaction),
            'subtotal' => $subtotal !== null ? round((float) $subtotal, 2) : round((float) $saleTransaction->total, 2),
            'discount' => $discount ? [
                'type' => (string) data_get($discount, 'type'),
                'percent' => (int) data_get($discount, 'percent'),
                'amount' => round((float) data_get($discount, 'amount'), 2),
            ] : null,
            'payment' => [
                'amount_received' => $saleTransaction->amountReceived(),
                'change_amount' => $saleTransaction->changeAmount(),
                'reference' => $saleTransaction->paymentReference(),
            ],
        ];
    }

    private function ensureMembershipPendingOnSite(MemberSubscription $subscription): void
    {
        if ($subscription->status !== MemberSubscription::STATUS_PAUSED
            || $subscription->pending_payment_method !== MemberSubscription::PENDING_PAYMENT_ON_SITE) {
            abort(409, 'This registration is no longer pending on-site payment.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePendingWalkIn(KioskPayment $payment): array
    {
        $base = $payment->base_amount !== null
            ? round((float) $payment->base_amount, 2)
            : round((float) $payment->amount, 2);

        return [
            'kind' => 'walk_in',
            'key' => 'walk-in:'.$payment->reference,
            'name' => $payment->name,
            'contact' => $payment->phone,
            'item_label' => 'Walk-in',
            'detail' => $payment->reference,
            'base_amount' => $base,
            'amount' => round((float) $payment->amount, 2),
            'discount_type' => $payment->discount_type,
            'discount_percent' => $payment->discount_type !== null ? KioskPayment::DISCOUNT_PERCENT : 0,
            'created_at' => $payment->created_at?->toISOString(),
            'confirm_url' => route('panel.kiosk-payments.confirm', $payment->reference),
            'cancel_url' => route('panel.kiosk-payments.cancel', $payment->reference),
            'requires_payment_method' => false,
            'subject_url' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePendingMembership(MemberSubscription $subscription): array
    {
        $member = $subscription->member;
        $ratePlan = $subscription->ratePlan;
        $hasDiscount = $member?->profile?->hasDiscount() ?? false;
        $originalPrice = round((float) ($ratePlan?->price ?? 0), 2);
        $soldPrice = round((float) $subscription->sold_price, 2);
        $startDate = $subscription->start_date?->toDateString();

        return [
            'kind' => 'membership',
            'key' => 'membership:'.$subscription->id,
            'name' => $member?->name,
            'contact' => $member?->phone ?: $member?->email,
            'item_label' => $ratePlan?->name ?: 'Membership',
            'detail' => $startDate ? 'Starts '.$startDate : null,
            'base_amount' => $originalPrice,
            'amount' => $soldPrice,
            'discount_type' => $hasDiscount ? $member->profile->discount_type : null,
            'discount_percent' => $hasDiscount ? MemberProfile::DISCOUNT_PERCENT : 0,
            'created_at' => $subscription->created_at?->toISOString(),
            'confirm_url' => route('panel.sales.pending-memberships.confirm', $subscription->id),
            'cancel_url' => route('panel.sales.pending-memberships.cancel', $subscription->id),
            'requires_payment_method' => true,
            'subject_url' => $member ? route('panel.members.show', $member->id) : null,
        ];
    }
}
