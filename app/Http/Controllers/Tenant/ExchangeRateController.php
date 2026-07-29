<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexExchangeRateRequest;
use App\Http\Requests\Tenant\StoreExchangeRateRequest;
use App\Http\Requests\Tenant\UpdateExchangeRateRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ExchangeRateResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ExchangeRate;
use App\Services\Tenant\ExchangeRateService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Exchange Rates')]
class ExchangeRateController extends Controller
{
    public function __construct(private ExchangeRateService $exchangeRates) {}

    /**
     * @operationId listExchangeRates
     */
    public function index(IndexExchangeRateRequest $request): ResourceCollection
    {
        return ExchangeRateResource::collection($this->exchangeRates->list($request->perPage()))
            ->withMessage('Exchange rates retrieved successfully.');
    }

    /**
     * @operationId createExchangeRate
     */
    #[DocsResponse(status: 201, description: 'Exchange rate created.', type: 'array{success: true, message: string, data: ExchangeRateResource, meta: null, errors: null}')]
    public function store(StoreExchangeRateRequest $request): JsonResponse
    {
        $rate = $this->exchangeRates->upsert($request->exchangeRateData());

        return ApiResponse::success(
            data: (new ExchangeRateResource($rate))->resolve(),
            message: 'Exchange rate saved successfully.',
            status: 201,
        );
    }

    /**
     * @operationId updateExchangeRate
     */
    #[PathParameter('exchange_rate', description: 'Exchange rate ID.', type: 'integer', example: 1)]
    public function update(UpdateExchangeRateRequest $request, ExchangeRate $exchangeRate): ExchangeRateResource
    {
        return (new ExchangeRateResource($this->exchangeRates->update($exchangeRate, $request->exchangeRateData())))
            ->withMessage('Exchange rate updated successfully.');
    }
}
