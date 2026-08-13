<?php

namespace Modules\Customers\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Customers\Http\Requests\StoreCustomerFieldRequest;
use Modules\Customers\Http\Requests\UpdateCustomerFieldRequest;
use Modules\Customers\Http\Resources\CustomerFieldResource;
use Modules\Customers\Repositories\CustomerFieldRepository;
use Modules\Customers\Services\CustomerFieldService;

class CustomerFieldController extends Controller
{
    public function __construct(
        protected CustomerFieldService $customerFieldService,
        protected CustomerFieldRepository $customerFieldRepository,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $fields = $this->customerFieldService->listForTenant((int) auth()->user()->tenant_id);

        return CustomerFieldResource::collection($fields);
    }

    public function store(StoreCustomerFieldRequest $request): JsonResponse
    {
        $field = $this->customerFieldService->create((int) auth()->user()->tenant_id, $request->validated());

        return (new CustomerFieldResource($field))->response()->setStatusCode(201);
    }

    public function update(UpdateCustomerFieldRequest $request, int $customerField): CustomerFieldResource|JsonResponse
    {
        $field = $this->customerFieldRepository->findForTenant($customerField, (int) auth()->user()->tenant_id);

        if (! $field) {
            return response()->json(['message' => 'Customer field not found.'], 404);
        }

        $field = $this->customerFieldService->update($field, $request->validated());

        return new CustomerFieldResource($field);
    }

    public function destroy(int $customerField): JsonResponse
    {
        $field = $this->customerFieldRepository->findForTenant($customerField, (int) auth()->user()->tenant_id);

        if (! $field) {
            return response()->json(['message' => 'Customer field not found.'], 404);
        }

        $this->customerFieldService->delete($field);

        return response()->json(['message' => 'Customer field deleted successfully.']);
    }
}
