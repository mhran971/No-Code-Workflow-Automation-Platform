<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Rules\PasswordStrengthRule;

class RegisterTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'business_type' => ['required', Rule::enum(BusinessType::class)],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                new PasswordStrengthRule,
            ],
            'password_confirmation' => ['required', 'string'],
            // 'captcha_token' => ['required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        // $validator->after(function ($validator) {
        //     if ($validator->errors()->isNotEmpty()) {
        //         return;
        //     }

        //     if (! $this->verifyCaptcha()) {
        //         $validator->errors()->add('captcha_token', 'CAPTCHA verification failed. Please try again.');
        //     }
        // });
    }

    /**
     * Verify the CAPTCHA token with Google reCAPTCHA API.
     */
    protected function verifyCaptcha(): bool
    {
        $secret = config('services.recaptcha.secret');

        if (empty($secret)) {
            return false;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $this->input('captcha_token'),
            'remoteip' => $this->ip(),
        ]);

        $body = $response->json();

        return isset($body['success']) && $body['success'] === true;
    }
}
