<?php

namespace Modules\Team\app\Http\Requests;


use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Input JSON: (Authorization handled by Middleware/Policy)
     *
     * @return bool
     */
    public function authorize()
    {
        // Assuming any authenticated user can create a team in their tenant
        // You can add specific policy checks here later
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * Input JSON: { "name": "string", "description": "string|null" }
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Team name is required.',
            'name.max' => 'Team name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }

}
