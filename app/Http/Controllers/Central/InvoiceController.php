<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Central\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\InvoiceResource;
use App\Http\Resources\Central\PaymentResource;
use App\Http\Resources\ResourceCollection;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Central\InvoiceService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\Request;

#[Group('Invoices')]
class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * @operationId listTenantInvoices
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function index(Request $request, Tenant $tenant): ResourceCollection
    {
        abort_unless($this->userCan(Permission::InvoicesView), 403);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 15)));

        return InvoiceResource::collection($this->invoices->listForTenant($tenant, $perPage))
            ->withMessage('Invoices retrieved successfully.');
    }

    /**
     * @operationId showInvoice
     */
    #[PathParameter('invoice', description: 'Invoice ID.', type: 'integer', example: 1)]
    public function show(Invoice $invoice): InvoiceResource
    {
        abort_unless($this->userCan(Permission::InvoicesView), 403);

        return (new InvoiceResource($this->invoices->find($invoice)))
            ->withMessage('Invoice retrieved successfully.');
    }

    /**
     * @operationId listTenantPayments
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function payments(Request $request, Tenant $tenant): ResourceCollection
    {
        abort_unless($this->userCan(Permission::InvoicesView), 403);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 15)));

        return PaymentResource::collection($this->invoices->listPaymentsForTenant($tenant, $perPage))
            ->withMessage('Payments retrieved successfully.');
    }

    private function userCan(Permission $permission): bool
    {
        return request()->user()?->can($permission->value) ?? false;
    }
}
