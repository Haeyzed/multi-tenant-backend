<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Base JSON resource that wraps payloads in the shared API envelope.
 *
 * Concrete resources extend this class so Scramble and clients always receive
 * `{ success, message, data, meta, errors }` for single-resource responses.
 */
class Resource extends JsonResource
{
    use FormatsApiResponse;

    /**
     * @var string
     */
    public static $wrap = 'data';

    /**
     * Envelope fields merged alongside the wrapped `data` key.
     *
     * Keys are declared inline so Scramble can document the shared envelope.
     *
     * @return array{success: true, message: string, errors: null, meta?: array<string, mixed>|null}
     */
    public function with(Request $request): array
    {
        if ($this->resource instanceof AbstractPaginator || $this->resource instanceof AbstractCursorPaginator) {
            return [
                /**
                 * Whether the request completed successfully.
                 *
                 * @example true
                 */
                'success' => true,
                /**
                 * Human-readable status message for the operation.
                 */
                'message' => $this->responseMessage,
                /**
                 * Error details; always null on successful resource responses.
                 */
                'errors' => null,
            ];
        }

        return [
            /**
             * Whether the request completed successfully.
             *
             * @example true
             */
            'success' => true,
            /**
             * Human-readable status message for the operation.
             */
            'message' => $this->responseMessage,
            /**
             * Error details; always null on successful resource responses.
             */
            'errors' => null,
            /**
             * Optional metadata; null when none was attached.
             *
             * @var array<string, mixed>|null
             */
            'meta' => $this->responseMeta,
        ];
    }

    /**
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): ResourceCollection
    {
        return new ResourceCollection($resource, static::class);
    }
}
