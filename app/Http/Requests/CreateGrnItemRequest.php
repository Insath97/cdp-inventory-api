<?php

namespace App\Http\Requests;

class CreateGrnItemRequest extends BaseFormRequest
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
            'grn_id' => 'required|exists:grns,id',
            'purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'unit_id' => 'required|exists:units,id',
            'container_id' => 'nullable|exists:containers,id',
            'quantity_ordered' => 'required|numeric|min:0',
            'quantity_received' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,amount',
            'discount_value' => ['nullable', 'numeric', 'min:0', $this->discountValueRule()],
            'expiry_date' => 'nullable|date',
            'batch_number' => 'nullable|string|max:255',
            'serial_numbers' => 'nullable|array',
            'serial_numbers.*' => 'nullable|string|max:255',
        ];
    }
}
