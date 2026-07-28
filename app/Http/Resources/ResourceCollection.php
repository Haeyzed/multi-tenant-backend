<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection as JsonResourceCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Base resource collection that wraps list payloads in the shared API envelope.
 *
 * Used by {@see Resource::collection()} so index endpoints return paginated or
 * plain collections under `data` with envelope `success`/`message`/`meta`/`errors`.
 */
class ResourceCollection extends JsonResourceCollection
{
    use FormatsApiResponse;

    /**
     * @var string
     */
    public static $wrap = 'data';

    /**
     * @param  mixed  $resource
     * @param  class-string|null  $collects
     */
    public function __construct($resource, ?string $collects = null)
    {
        $this->collects = $collects;

        parent::__construct($resource);
    }

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
}
