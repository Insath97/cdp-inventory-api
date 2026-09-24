<?php

namespace App\Http\Requests;

class UpdateSupplierProductsRequest extends BaseFormRequest
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
            'unit_id' => 'required|exists:units,id',
            'supply_quantity' => 'required|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'is_preferred' => 'boolean',
        ];
    }
}
