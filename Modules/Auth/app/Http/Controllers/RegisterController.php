<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Routing\Controller;
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
    public function __invoke(RegisterTenantRequest $request)
    {
        $user = $this->registrationService->register($request->validated());

        return (new RegisterSuccessResource($user))
            ->response()
            ->setStatusCode(201);
    }
}
