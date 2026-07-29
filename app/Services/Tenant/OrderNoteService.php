<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\OrderNoteType;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Order timeline notes.
 */
final class OrderNoteService
{
    /**
     * @return LengthAwarePaginator<int, OrderNote>
     */
    public function list(Order $order, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(OrderNote::class)
            ->where('order_id', $order->id)
            ->with('author')
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('type'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('type'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{type?: string, subject?: string|null, body: string}  $data
     */
    public function create(Order $order, array $data): OrderNote
    {
        return OrderNote::query()->create([
            'order_id' => $order->id,
            'type' => $data['type'] ?? OrderNoteType::General->value,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'created_by' => auth()->id(),
        ])->load('author');
    }

    /**
     * @param  array{type?: string, subject?: string|null, body?: string}  $data
     */
    public function update(Order $order, OrderNote $note, array $data): OrderNote
    {
        $this->assertBelongsToOrder($note, $order);
        $note->fill($data)->save();

        return $note->refresh()->load('author');
    }

    /**
     * Delete a note belonging to the given order.
     */
    public function delete(Order $order, OrderNote $note): void
    {
        $this->assertBelongsToOrder($note, $order);
        $note->delete();
    }

    /**
     * Ensure a note belongs to the given order, aborting with a 404 otherwise.
     */
    private function assertBelongsToOrder(OrderNote $note, Order $order): void
    {
        if ($note->order_id !== $order->id) {
            abort(404);
        }
    }
}
