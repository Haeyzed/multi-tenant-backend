<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\CreditNoteStatus;
use App\Enums\Tenant\SalesInvoiceStatus;
use App\Events\Tenant\Erp\CreditNoteIssued;
use App\Models\Tenant;
use App\Models\Tenant\CreditNote;
use App\Models\Tenant\Product;
use App\Models\Tenant\SalesInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Credit note lifecycle against sales invoices.
 */
final class CreditNoteService
{
    /**
     * @return LengthAwarePaginator<int, CreditNote>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(CreditNote::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('sales_invoice_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('total'),
                AllowedSort::field('issued_at'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('salesInvoice'),
                AllowedInclude::relationship('customer'),
                AllowedInclude::relationship('items'),
            )
            ->defaultSort('-created_at')
            ->with(['salesInvoice', 'customer', 'items'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     sales_invoice_id: int,
     *     reason?: string|null,
     *     notes?: string|null,
     *     items: list<array{product_id?: int|null, description: string, quantity: int, unit_price: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): CreditNote
    {
        return DB::transaction(function () use ($data): CreditNote {
            /** @var SalesInvoice $invoice */
            $invoice = SalesInvoice::query()->findOrFail($data['sales_invoice_id']);

            if ($invoice->status === SalesInvoiceStatus::Void) {
                throw ValidationException::withMessages([
                    'sales_invoice_id' => ['Credit notes cannot be created against void invoices.'],
                ]);
            }

            $lines = $this->buildLines($data['items']);
            $subtotal = array_sum(array_column($lines, 'line_total'));
            $tax = $this->calculateTax($subtotal, $invoice);

            /** @var CreditNote $creditNote */
            $creditNote = CreditNote::query()->create([
                'number' => 'CN-'.Str::upper(Str::random(10)),
                'sales_invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id,
                'customer_id' => $invoice->customer_id,
                'status' => CreditNoteStatus::Draft,
                'currency' => $invoice->currency,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $subtotal + $tax,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $creditNote->items()->create($line);
            }

            return $this->find($creditNote->refresh());
        });
    }

    public function find(CreditNote $creditNote): CreditNote
    {
        return $creditNote->loadMissing(['salesInvoice', 'customer', 'items.product', 'order']);
    }

    public function delete(CreditNote $creditNote): void
    {
        $this->assertStatus($creditNote, CreditNoteStatus::Draft);
        $creditNote->delete();
    }

    /**
     * @throws Throwable
     */
    public function issue(CreditNote $creditNote): CreditNote
    {
        $this->assertStatus($creditNote, CreditNoteStatus::Draft);

        if ($creditNote->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Credit note must have at least one item before issuing.'],
            ]);
        }

        return DB::transaction(function () use ($creditNote): CreditNote {
            /** @var Tenant $tenant */
            $tenant = tenant();

            $creditNote->update([
                'status' => CreditNoteStatus::Issued,
                'issued_at' => now(),
            ]);

            $creditNote = $this->find($creditNote->refresh());

            event(new CreditNoteIssued($creditNote, (string) $tenant->getTenantKey()));

            return $creditNote;
        });
    }

    public function void(CreditNote $creditNote): CreditNote
    {
        $this->assertStatus($creditNote, CreditNoteStatus::Issued);

        $creditNote->update([
            'status' => CreditNoteStatus::Void,
            'voided_at' => now(),
        ]);

        return $this->find($creditNote->refresh());
    }

    /**
     * @param  list<array{product_id?: int|null, description: string, quantity: int, unit_price: int}>  $items
     * @return list<array{product_id?: int|null, description: string, quantity: int, unit_price: int, line_total: int}>
     */
    private function buildLines(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['A credit note must include at least one item.'],
            ]);
        }

        $lines = [];

        foreach ($items as $index => $item) {
            if (($item['quantity'] ?? 0) < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                ]);
            }

            if (($item['unit_price'] ?? 0) < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_price" => ['Unit price cannot be negative.'],
                ]);
            }

            if (isset($item['product_id'])) {
                Product::query()->findOrFail($item['product_id']);
            }

            $quantity = $item['quantity'];
            $unitPrice = $item['unit_price'];

            $lines[] = [
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ];
        }

        return $lines;
    }

    private function calculateTax(int $subtotal, SalesInvoice $invoice): int
    {
        if ($subtotal === 0 || $invoice->subtotal === 0 || $invoice->tax === 0) {
            return 0;
        }

        return (int) round($subtotal * ($invoice->tax / $invoice->subtotal));
    }

    private function assertStatus(CreditNote $creditNote, CreditNoteStatus $expected): void
    {
        if ($creditNote->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Credit note must be in {$expected->value} status."],
            ]);
        }
    }
}
