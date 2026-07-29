<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CreditCustomerWalletRequest;
use App\Http\Requests\Tenant\DebitCustomerWalletRequest;
use App\Http\Requests\Tenant\EarnCustomerLoyaltyPointsRequest;
use App\Http\Requests\Tenant\RedeemCustomerLoyaltyPointsRequest;
use App\Http\Resources\Tenant\CustomerWalletLedgerResource;
use App\Http\Resources\Tenant\CustomerWalletResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Customer;
use App\Policies\Tenant\CustomerWalletPolicy;
use App\Services\Tenant\CustomerWalletService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Customer Wallets')]
class CustomerWalletController extends Controller
{
    public function __construct(
        private CustomerWalletService $wallets,
        private CustomerWalletPolicy $walletPolicy,
    ) {}

    /**
     * @operationId showCustomerWallet
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    public function show(Customer $customer): CustomerWalletResource
    {
        $user = auth()->user();
        abort_unless($user !== null && $this->walletPolicy->view($user, $customer), 403);

        return (new CustomerWalletResource($this->wallets->find($customer)))
            ->withMessage('Customer wallet retrieved successfully.');
    }

    /**
     * @operationId creditCustomerWallet
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Wallet credited.', type: 'array{success: true, message: string, data: CustomerWalletLedgerResource, meta: null, errors: null}')]
    public function credit(CreditCustomerWalletRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->walletData();
        $ledger = $this->wallets->credit($customer, $data['amount'], $data['notes'] ?? null);

        return ApiResponse::success(
            data: (new CustomerWalletLedgerResource($ledger))->resolve(),
            message: 'Customer wallet credited successfully.',
            status: 201,
        );
    }

    /**
     * @operationId debitCustomerWallet
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Wallet debited.', type: 'array{success: true, message: string, data: CustomerWalletLedgerResource, meta: null, errors: null}')]
    public function debit(DebitCustomerWalletRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->walletData();
        $ledger = $this->wallets->debit($customer, $data['amount'], $data['notes'] ?? null);

        return ApiResponse::success(
            data: (new CustomerWalletLedgerResource($ledger))->resolve(),
            message: 'Customer wallet debited successfully.',
            status: 201,
        );
    }

    /**
     * @operationId earnCustomerLoyaltyPoints
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Loyalty points earned.', type: 'array{success: true, message: string, data: CustomerWalletLedgerResource, meta: null, errors: null}')]
    public function earnPoints(EarnCustomerLoyaltyPointsRequest $request, Customer $customer): JsonResponse
    {
        $ledger = $this->wallets->earnPoints(
            $customer,
            (int) $request->validated('points'),
            $request->validated('notes'),
        )->loadMissing('wallet');

        return ApiResponse::success(
            data: (new CustomerWalletLedgerResource($ledger))->resolve(),
            message: 'Loyalty points earned successfully.',
            status: 201,
        );
    }

    /**
     * @operationId redeemCustomerLoyaltyPoints
     */
    #[PathParameter('customer', description: 'Customer ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Loyalty points redeemed.', type: 'array{success: true, message: string, data: CustomerWalletLedgerResource, meta: null, errors: null}')]
    public function redeemPoints(RedeemCustomerLoyaltyPointsRequest $request, Customer $customer): JsonResponse
    {
        $ledger = $this->wallets->redeemPoints(
            $customer,
            (int) $request->validated('points'),
            $request->validated('notes'),
        )->loadMissing('wallet');

        return ApiResponse::success(
            data: (new CustomerWalletLedgerResource($ledger))->resolve(),
            message: 'Loyalty points redeemed successfully.',
            status: 201,
        );
    }
}
