<?php

namespace App\Http\Requests;

class CreateEmployeeRequest extends BaseFormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            // Optional — left blank, the controller generates EMP-0001 style codes
            'employee_code' => 'nullable|string|max:50|unique:employees,employee_code',
            'email' => 'required|email|unique:employees,email|max:255',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
        ];
    }
}