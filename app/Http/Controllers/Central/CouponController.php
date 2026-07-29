<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\IndexCouponRequest;
use App\Http\Requests\Central\StoreCouponRequest;
use App\Http\Requests\Central\UpdateCouponRequest;
use App\Http\Resources\Central\CouponResource;
use App\Http\Resources\ResourceCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Central\Coupon;
use App\Services\Central\CouponService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Coupons')]
class CouponController extends Controller
{
    public function __construct(private CouponService $coupons) {}

    /**
     * @operationId listCoupons
     */
    public function index(IndexCouponRequest $request): ResourceCollection
    {
        return CouponResource::collection($this->coupons->list($request->perPage()))
            ->withMessage('Coupons retrieved successfully.');
    }

    /**
     * @operationId createCoupon
     */
    #[DocsResponse(status: 201, description: 'Coupon created.', type: 'array{success: true, message: string, data: CouponResource, meta: null, errors: null}')]
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = $this->coupons->create($request->couponData());

        return ApiResponse::success(
            data: (new CouponResource($coupon))->resolve(),
            message: 'Coupon created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showCoupon
     */
    #[PathParameter('coupon', description: 'Coupon ID.', type: 'integer', example: 1)]
    public function show(Coupon $coupon): CouponResource
    {
        $this->authorize('view', $coupon);

        return (new CouponResource($this->coupons->find($coupon)))
            ->withMessage('Coupon retrieved successfully.');
    }

    /**
     * @operationId updateCoupon
     */
    #[PathParameter('coupon', description: 'Coupon ID.', type: 'integer', example: 1)]
    public function update(UpdateCouponRequest $request, Coupon $coupon): CouponResource
    {
        return (new CouponResource($this->coupons->update($coupon, $request->couponData())))
            ->withMessage('Coupon updated successfully.');
    }

    /**
     * @operationId deleteCoupon
     */
    #[PathParameter('coupon', description: 'Coupon ID.', type: 'integer', example: 1)]
    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->authorize('delete', $coupon);

        $this->coupons->delete($coupon);

        return ApiResponse::success(message: 'Coupon deleted successfully.');
    }
}
