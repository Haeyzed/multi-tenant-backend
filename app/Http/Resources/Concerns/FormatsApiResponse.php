<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Request;

/**
 * Applies the shared API envelope to JSON resources and resource collections.
 *
 * Successful resource responses expose `success`, `message`, `data`, `meta`, and
 * `errors`. Pagination metadata is merged under `meta` instead of Laravel's
 * default top-level `meta`/`links` pairing when a paginator is present.
 *
 * Concrete `with()` implementations declare envelope keys inline so Scramble can
 * document them accurately.
 */
trait FormatsApiResponse
{
    protected string $responseMessage = 'Operation completed successfully.';

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $responseMeta = null;

    /**
     * Set the human-readable success message included in the envelope.
     */
    public function withMessage(string $message): static
    {
        $this->responseMessage = $message;

        return $this;
    }

    /**
     * Attach additional metadata merged into the envelope `meta` object.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function withMeta(?array $meta): static
    {
        $this->responseMeta = $meta;

        return $this;
    }

    /**
     * Keep pagination details under the shared `meta` key.
     *
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array{meta: array<string, mixed>}
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        $meta = $default['meta'] ?? [];

        if (isset($default['links'])) {
            $meta['links'] = $default['links'];
        }

        if ($this->responseMeta !== null) {
            $meta = array_merge($meta, $this->responseMeta);
        }

        return [
            /**
             * Pagination metadata and optional custom meta fields.
             *
             * @var array<string, mixed>
             */
            'meta' => $meta,
        ];
    }
}
