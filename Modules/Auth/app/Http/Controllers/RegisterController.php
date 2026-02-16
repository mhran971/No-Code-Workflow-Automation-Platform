<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Http\Requests\RegisterTenantRequest;
use Modules\Auth\Http\Resources\RegisterSuccessResource;
use Modules\Auth\Services\TenantRegistrationService;

class RegisterController extends Controller
{
    public function __construct(
        protected TenantRegistrationService $registrationService
    ) {}

    /**
     * Register a new tenant and user (business owner).
     *
     * @unauthenticated
     */
    public function __invoke(RegisterTenantRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = $this->registrationService->register($request->validated());

            DB::commit();

            return (new RegisterSuccessResource($user))
                ->response()
                ->setStatusCode(201);
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }
}
