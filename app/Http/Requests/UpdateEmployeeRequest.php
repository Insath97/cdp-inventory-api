<?php

namespace App\Http\Requests;

class UpdateEmployeeRequest extends BaseFormRequest
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
        $id = $this->route('employee');
        return [
            'full_name' => 'sometimes|string|max:255',
            'employee_code' => 'sometimes|required|string|max:50|unique:employees,employee_code,' . $id,
            'email' => 'sometimes|email|max:255|unique:employees,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
        ];
    }
}