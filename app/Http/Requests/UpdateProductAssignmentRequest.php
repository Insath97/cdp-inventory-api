<?php

namespace App\Http\Requests;

class UpdateProductAssignmentRequest extends BaseFormRequest
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
            'person_name' => 'sometimes|required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'branch_name' => 'sometimes|required|string|max:255',
            'department_name' => 'nullable|string|max:255',
            'product_id' => 'nullable|integer',
            'product_sku' => 'nullable|string|max:50',
            'product_name' => 'nullable|string|max:255',
            'quantity' => 'sometimes|required|integer|min:1',
            'issue_date' => 'sometimes|required|date',
            'remarks' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ];
    }
}
