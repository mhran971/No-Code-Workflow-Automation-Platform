<?php

namespace Modules\Customers\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Customers\Enums\CustomerLinkingField;
use Modules\Customers\Http\Requests\UpdateCustomerSettingsRequest;
use Modules\Customers\Http\Resources\CustomerSettingsResource;
use Modules\Customers\Services\CustomerSettingsService;

class CustomerSettingsController extends Controller
{
    public function __construct(
        protected CustomerSettingsService $customerSettingsService
    ) {}

    public function show(): CustomerSettingsResource
    {
        $settings = $this->customerSettingsService->getForTenant((int) auth()->user()->tenant_id);

        return new CustomerSettingsResource($settings);
    }

    public function update(UpdateCustomerSettingsRequest $request): CustomerSettingsResource|JsonResponse
    {
        try {
            $settings = $this->customerSettingsService->updateForTenant(
                (int) auth()->user()->tenant_id,
                CustomerLinkingField::from($request->validated('linking_field'))
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return new CustomerSettingsResource($settings);
    }
}
