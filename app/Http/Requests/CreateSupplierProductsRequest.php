<?php

namespace App\Http\Requests;

class CreateSupplierProductsRequest extends BaseFormRequest
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
            'supplier_id' => 'required|exists:suppliers,id',
            'product_id' => 'required|exists:products,id',
            'unit_id' => 'nullable|exists:units,id',
            'supply_quantity' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'is_preferred' => 'boolean',
        ];
    }
}
