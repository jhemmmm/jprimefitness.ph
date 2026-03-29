<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SaleTransaction;
use App\Services\PosSaleService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;

class SalesController extends Controller
{
    public function __construct(private PosSaleService $posSaleService) {}

    public function index(): View
    {
        return view('panel.sales');
    }

    public function context(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $branch = $this->findAccessibleBranch((int) $data['branch']);

        return response()->json([
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'city' => $branch->city,
                'province' => $branch->province,
            ],
            'options' => $this->posSaleService->branchContext($branch),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch' => ['required', 'integer', 'exists:branches,id'],
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

        $branch = $this->findAccessibleBranch((int) $data['branch']);

        $history = SaleTransaction::query()
            ->with(['member:id,name', 'processedBy:id,name'])
            ->where('branch_id', $branch->id)
            ->when($request->type, fn ($query) => $query->where('type', $request->type))
            ->when($request->search, function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($inner) use ($search) {
                    $inner->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhere('payment_reference', 'like', "%{$search}%");
                });
            })
            ->when($request->date_from, fn ($query) => $query->whereDate('sold_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($query) => $query->whereDate('sold_at', '<=', $request->date_to))
            ->orderByDesc('sold_at')
            ->paginate(15)
            ->through(fn (SaleTransaction $transaction) => $this->transformTransaction($transaction))
            ->withQueryString();

        return response()->json([
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
            ],
            'transactions' => $history,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateStorePayload($request);
        $branch = $this->findAccessibleBranch((int) $data['branch_id']);
        $transaction = $this->posSaleService->processSale($branch, $data, auth()->user())
            ->load(['branch:id,name', 'member:id,name', 'processedBy:id,name']);

        return response()->json($this->transformTransaction($transaction), 201);
    }

    public function receipt(SaleTransaction $saleTransaction): View
    {
        return view('panel.sales.receipt', $this->receiptPayload($saleTransaction));
    }

    public function printReceipt(SaleTransaction $saleTransaction): Responsable
    {
        $fileName = 'sale-receipt-'.$saleTransaction->id.'.pdf';

        return Pdf::view('panel.sales.receipt', $this->receiptPayload($saleTransaction))
            ->driver('dompdf')
            ->format('a4')
            ->margins(8, 8, 8, 8)
            ->download($fileName);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStorePayload(Request $request): array
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'type' => ['required', Rule::in([
                SaleTransaction::TYPE_INVENTORY,
                SaleTransaction::TYPE_MEMBERSHIP,
                SaleTransaction::TYPE_PT_PACKAGE,
                SaleTransaction::TYPE_WALK_IN,
            ])],
            'payment_method' => ['required', Rule::in([
                ...SaleTransaction::supportedPaymentMethods(),
            ])],
            'sold_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'member_mode' => ['nullable', Rule::in(['existing', 'new'])],
            'member_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
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
            if (empty($validated['member_mode'])) {
                $errors['member_mode'][] = 'Choose whether this sale is for an existing or new member.';
            }

            if (($validated['member_mode'] ?? null) === 'existing' && empty($validated['member_id'])) {
                $errors['member_id'][] = 'Select a member for this sale.';
            }

            if (($validated['member_mode'] ?? null) === 'new' && empty($validated['customer_name'])) {
                $errors['customer_name'][] = 'Enter the new member name.';
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

    private function findAccessibleBranch(int $branchId): Branch
    {
        return Branch::query()
            ->when(! auth()->user()->hasRole('super admin'), fn ($query) => $query->whereIn('id', auth()->user()->branches()->pluck('branches.id')))
            ->findOrFail($branchId);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformTransaction(SaleTransaction $transaction): array
    {
        $details = $transaction->details ?? [];

        return [
            'id' => $transaction->id,
            'receipt_number' => $transaction->receiptNumber(),
            'branch_id' => $transaction->branch_id,
            'member_id' => $transaction->member_id,
            'type' => $transaction->type,
            'total' => round((float) $transaction->total, 2),
            'payment_method' => $transaction->payment_method,
            'payment_method_label' => SaleTransaction::paymentMethodLabel($transaction->payment_method),
            'amount_received' => $transaction->amountReceived(),
            'change_amount' => $transaction->changeAmount(),
            'payment_reference' => $transaction->paymentReference(),
            'processed_by' => $transaction->processedBy?->name,
            'sold_at' => $transaction->sold_at?->toISOString(),
            'customer_name' => $transaction->customer_name ?: $transaction->member?->name,
            'item_name' => $transaction->item_name,
            'details' => $details,
            'receipt_url' => route('panel.sales.receipt.print', $transaction),
            'source_url' => match ($transaction->type) {
                SaleTransaction::TYPE_MEMBERSHIP, SaleTransaction::TYPE_PT_PACKAGE => $transaction->member_id
                    ? route('panel.members.show', $transaction->member_id)
                    : null,
                default => null,
            },
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
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
        $this->findAccessibleBranch((int) $saleTransaction->branch_id);

        $saleTransaction->loadMissing([
            'branch:id,name,city,province,address',
            'member:id,name,email,phone',
            'processedBy:id,name',
        ]);

        return [
            'saleTransaction' => $saleTransaction,
            'receiptNumber' => $saleTransaction->receiptNumber(),
            'lineItems' => $this->receiptLineItems($saleTransaction),
            'payment' => [
                'amount_received' => $saleTransaction->amountReceived(),
                'change_amount' => $saleTransaction->changeAmount(),
                'reference' => $saleTransaction->paymentReference(),
            ],
        ];
    }
}
