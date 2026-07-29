<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\ChannelType;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\PosSessionStatus;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Order;
use App\Models\Tenant\PosSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * POS register sessions and channel-bound sales.
 */
final class PosSessionService
{
    public function __construct(private OrderService $orders) {}

    /**
     * @return LengthAwarePaginator<int, PosSession>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(PosSession::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('channel_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('status'),
                AllowedSort::field('opened_at'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->with(['channel', 'opener', 'closer'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{channel_id: int, opening_float?: int, notes?: string|null}  $data
     *
     * @throws Throwable
     */
    public function open(array $data): PosSession
    {
        /** @var Channel $channel */
        $channel = Channel::query()->findOrFail($data['channel_id']);

        if (! $channel->is_active) {
            throw ValidationException::withMessages([
                'channel_id' => ['The selected channel is inactive.'],
            ]);
        }

        if ($channel->type !== ChannelType::Pos) {
            throw ValidationException::withMessages([
                'channel_id' => ['POS sessions require a channel of type pos.'],
            ]);
        }

        $openExists = PosSession::query()
            ->where('channel_id', $channel->id)
            ->where('status', PosSessionStatus::Open)
            ->exists();

        if ($openExists) {
            throw ValidationException::withMessages([
                'channel_id' => ['This POS channel already has an open session.'],
            ]);
        }

        return PosSession::query()->create([
            'number' => 'POS-'.Str::upper(Str::random(10)),
            'channel_id' => $channel->id,
            'opened_by' => auth()->id(),
            'status' => PosSessionStatus::Open,
            'opening_float' => $data['opening_float'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'opened_at' => now(),
        ])->load(['channel', 'opener']);
    }

    /**
     * Load a POS session with its channel, opener, and closer relations, plus its order count.
     */
    public function find(PosSession $session): PosSession
    {
        return $session->loadMissing(['channel', 'opener', 'closer'])->loadCount('orders');
    }

    /**
     * @param  array{closing_float?: int|null, notes?: string|null}  $data
     */
    public function close(PosSession $session, array $data = []): PosSession
    {
        $this->assertOpen($session);

        $session->update([
            'status' => PosSessionStatus::Closed,
            'closed_by' => auth()->id(),
            'closing_float' => $data['closing_float'] ?? null,
            'notes' => $data['notes'] ?? $session->notes,
            'closed_at' => now(),
        ]);

        return $this->find($session->refresh());
    }

    /**
     * @param  array{
     *     customer_id: int,
     *     tax_id?: int|null,
     *     warehouse_id?: int|null,
     *     notes?: string|null,
     *     items: list<array{product_id: int, quantity: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function sale(PosSession $session, array $data): Order
    {
        $this->assertOpen($session);

        $session->loadMissing('channel');

        return DB::transaction(function () use ($session, $data): Order {
            $payload = array_merge($data, [
                'channel_id' => $session->channel_id,
                'pos_session_id' => $session->id,
                'warehouse_id' => $data['warehouse_id'] ?? $session->channel->warehouse_id,
                'status' => OrderStatus::Confirmed->value,
            ]);

            return $this->orders->create($payload);
        });
    }

    /**
     * Ensure a POS session is open.
     *
     * @throws ValidationException if the session is not open
     */
    private function assertOpen(PosSession $session): void
    {
        if ($session->status !== PosSessionStatus::Open) {
            throw ValidationException::withMessages([
                'status' => ['POS session must be open.'],
            ]);
        }
    }
}
