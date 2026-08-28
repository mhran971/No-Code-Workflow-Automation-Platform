<?php

namespace Modules\Customers\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Customers\Http\Requests\StoreCustomerRequest;
use Modules\Customers\Http\Requests\UpdateCustomerRequest;
use Modules\Customers\Http\Resources\CustomerResource;
use Modules\Customers\Services\CustomerService;
use Modules\Workflows\Http\Requests\ListWorkflowInstancesRequest;
use Modules\Workflows\Models\WorkflowInstance;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->get('per_page', 15);
        $customers = $this->customerService->listForTenant((int) auth()->user()->tenant_id, $perPage);

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $customer = $this->customerService->create((int) auth()->user()->tenant_id, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    public function show(int $id): CustomerResource|JsonResponse
    {
        $customer = $this->customerService->getForTenant($id, (int) auth()->user()->tenant_id);

        if (! $customer) {
            return response()->json(['message' => 'Customer not found or access denied.'], 404);
        }

        return new CustomerResource($customer);
    }

    public function update(UpdateCustomerRequest $request, int $id): CustomerResource|JsonResponse
    {
        $customer = $this->customerService->getForTenant($id, (int) auth()->user()->tenant_id);

        if (! $customer) {
            return response()->json(['message' => 'Customer not found or access denied.'], 404);
        }

        try {
            $customer = $this->customerService->update($customer, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return new CustomerResource($customer);
    }

    public function destroy(int $id): JsonResponse
    {
        $customer = $this->customerService->getForTenant($id, (int) auth()->user()->tenant_id);

        if (! $customer) {
            return response()->json(['message' => 'Customer not found or access denied.'], 404);
        }

        $this->customerService->delete($customer);

        return response()->json(['message' => 'Customer deleted successfully.']);
    }

    /**
     * List workflow instances linked to this customer (most recent first).
     *
     * Mirrors WorkflowInstanceController::index — same filters, same paginated
     * response shape — but scoped to a customer instead of a workflow.
     */
    public function instances(ListWorkflowInstancesRequest $request, int $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $customer = $this->customerService->getForTenant($id, $tenantId);

        if (! $customer) {
            return response()->json(['message' => 'Customer not found or access denied.'], 404);
        }

        $validated = $request->validated();

        $query = WorkflowInstance::query()
            ->where('customer_id', $customer->id)
            ->where('tenant_id', $tenantId);

        if (array_key_exists('status', $validated)) {
            $query->where('status', $validated['status']);
        }

        if (array_key_exists('started_from', $validated)) {
            $query->whereDate('started_at', '>=', $validated['started_from']);
        }

        if (array_key_exists('started_to', $validated)) {
            $query->whereDate('started_at', '<=', $validated['started_to']);
        }

        if (array_key_exists('finished_from', $validated)) {
            $query->whereDate('finished_at', '>=', $validated['finished_from']);
        }

        if (array_key_exists('finished_to', $validated)) {
            $query->whereDate('finished_at', '<=', $validated['finished_to']);
        }

        $instances = $query->latest()->paginate(20);

        return response()->json($instances);
    }
}
