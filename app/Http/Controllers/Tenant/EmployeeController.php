<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexEmployeeRequest;
use App\Http\Requests\Tenant\StoreEmployeeRequest;
use App\Http\Requests\Tenant\UpdateEmployeeRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\EmployeeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Employee;
use App\Services\Tenant\EmployeeService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Employees')]
class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $employees) {}

    /**
     * @operationId listEmployees
     */
    public function index(IndexEmployeeRequest $request): ResourceCollection
    {
        return EmployeeResource::collection($this->employees->list($request->perPage()))
            ->withMessage('Employees retrieved successfully.');
    }

    /**
     * @operationId createEmployee
     */
    #[DocsResponse(status: 201, description: 'Employee created.', type: 'array{success: true, message: string, data: EmployeeResource, meta: null, errors: null}')]
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employees->create($request->employeeData());

        return ApiResponse::success(
            data: (new EmployeeResource($employee->load('user')))->resolve(),
            message: 'Employee created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showEmployee
     */
    #[PathParameter('employee', description: 'Employee ID.', type: 'integer', example: 1)]
    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        return (new EmployeeResource($this->employees->find($employee)))
            ->withMessage('Employee retrieved successfully.');
    }

    /**
     * @operationId updateEmployee
     */
    #[PathParameter('employee', description: 'Employee ID.', type: 'integer', example: 1)]
    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        return (new EmployeeResource($this->employees->update($employee, $request->employeeData())))
            ->withMessage('Employee updated successfully.');
    }

    /**
     * @operationId deleteEmployee
     */
    #[PathParameter('employee', description: 'Employee ID.', type: 'integer', example: 1)]
    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);
        $this->employees->delete($employee);

        return ApiResponse::success(message: 'Employee deleted successfully.');
    }
}
