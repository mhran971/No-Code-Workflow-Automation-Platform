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
}
