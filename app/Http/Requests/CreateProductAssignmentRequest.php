<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'person_name' => 'nullable|string',
            'group_name' => 'nullable|string',
            'branch_name' => 'nullable|string',
            'department_name' => 'nullable|string',
            'product_id' => 'nullable|exists:products,id',
            'product_variant_id' => 'nullable|exists:products,id',
            'grn_item_serial_id' => 'nullable|exists:grn_item_serials,id',
            'user_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'product_sku' => 'nullable|string',
            'product_name' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
            'issue_date' => 'required|date',
            'remarks' => 'nullable|string',
        ];
    }

    /**
     * A serial-based assignment identifies the unit via grn_item_serial_id
     * (quantity is always 1, forced server-side); a legacy quantity-based
     * assignment identifies the product via product_id/product_variant_id
     * and needs an explicit quantity. Exactly one of these two shapes must
     * be present.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasSerial = $this->filled('grn_item_serial_id');
            $hasProduct = $this->filled('product_id') || $this->filled('product_variant_id');

            if (!$hasSerial && !$hasProduct) {
                $validator->errors()->add('product_id', 'Select a product or a specific serial number.');
            }
            if (!$hasSerial && !$this->filled('quantity')) {
                $validator->errors()->add('quantity', 'Quantity is required for non-serial assignments.');
            }
        });
    }
}
