<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Central\FeatureFlagService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate routes behind a platform feature flag (default enabled when unset).
 *
 * Usage: middleware('feature:features.erp.warehouses')
 */
final class EnsureFeatureFlag
{
    public function __construct(private FeatureFlagService $flags) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        if (! $this->flags->enabled($flag)) {
            abort(403, "Feature [{$flag}] is disabled.");
        }

        return $next($request);
    }
}
