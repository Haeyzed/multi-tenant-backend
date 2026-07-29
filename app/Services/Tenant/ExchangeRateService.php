<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ExchangeRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Exchange rate storage and currency conversion.
 */
final class ExchangeRateService
{
    /**
     * @return LengthAwarePaginator<int, ExchangeRate>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(ExchangeRate::class)
            ->allowedFilters(
                AllowedFilter::exact('currency_from'),
                AllowedFilter::exact('currency_to'),
                AllowedFilter::exact('source'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('currency_from'),
                AllowedSort::field('currency_to'),
                AllowedSort::field('effective_at'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-effective_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     currency_from: string,
     *     currency_to: string,
     *     rate: numeric-string|float|int,
     *     effective_at: string,
     *     source?: string|null
     * }  $data
     */
    public function upsert(array $data): ExchangeRate
    {
        $from = strtoupper($data['currency_from']);
        $to = strtoupper($data['currency_to']);
        $effectiveAt = Carbon::parse($data['effective_at']);

        /** @var ExchangeRate $rate */
        $rate = ExchangeRate::query()->updateOrCreate(
            [
                'currency_from' => $from,
                'currency_to' => $to,
                'effective_at' => $effectiveAt,
            ],
            [
                'rate' => $data['rate'],
                'source' => $data['source'] ?? null,
            ],
        );

        return $rate->refresh();
    }

    /**
     * @param  array{
     *     currency_from?: string,
     *     currency_to?: string,
     *     rate?: numeric-string|float|int,
     *     effective_at?: string,
     *     source?: string|null
     * }  $data
     */
    public function update(ExchangeRate $exchangeRate, array $data): ExchangeRate
    {
        $from = strtoupper($data['currency_from'] ?? $exchangeRate->currency_from);
        $to = strtoupper($data['currency_to'] ?? $exchangeRate->currency_to);
        $effectiveAt = isset($data['effective_at'])
            ? Carbon::parse($data['effective_at'])
            : $exchangeRate->effective_at;

        if (
            $from !== $exchangeRate->currency_from
            || $to !== $exchangeRate->currency_to
            || ! $effectiveAt->equalTo($exchangeRate->effective_at)
        ) {
            ExchangeRate::query()
                ->whereKey($exchangeRate->id)
                ->delete();

            return $this->upsert([
                'currency_from' => $from,
                'currency_to' => $to,
                'rate' => $data['rate'] ?? $exchangeRate->rate,
                'effective_at' => $effectiveAt->toDateTimeString(),
                'source' => $data['source'] ?? $exchangeRate->source,
            ]);
        }

        $exchangeRate->fill([
            'rate' => $data['rate'] ?? $exchangeRate->rate,
            'source' => array_key_exists('source', $data) ? $data['source'] : $exchangeRate->source,
        ])->save();

        return $exchangeRate->refresh();
    }

    public function convert(int $amount, string $from, string $to, ?Carbon $at = null): int
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $rate = $this->findRate($from, $to, $at ?? now());

        if ($rate === null) {
            throw ValidationException::withMessages([
                'currency' => ["No exchange rate found for {$from} to {$to}."],
            ]);
        }

        return (int) round($amount * (float) $rate->rate);
    }

    private function findRate(string $from, string $to, Carbon $at): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->where('currency_from', $from)
            ->where('currency_to', $to)
            ->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->first();
    }
}
