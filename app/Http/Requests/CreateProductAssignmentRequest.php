<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Adjust as needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'assignment_code' => 'sometimes|nullable|string',
            'person_name' => 'required|string',
            'group_name' => 'nullable|string',
            'branch_name' => 'required|string',
            'department_name' => 'nullable|string',
            'product_id' => 'nullable|exists:products,id',
            'product_variant_id' => 'nullable|exists:products,id',
            'product_sku' => 'nullable|string',
            'product_name' => 'nullable|string',
            'issue_date' => 'required|date',
            'remarks' => 'nullable|string',
        ];
    }
}
