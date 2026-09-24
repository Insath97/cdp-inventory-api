<?php

namespace App\Http\Requests;

class UpdatePurchaseOrderItemRequest extends BaseFormRequest
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
        $id = $this->route('id');

        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],

            // Quantities
            'quantity_ordered' => ['required', 'integer', 'min:1'],
            'quantity_received' => ['nullable', 'integer', 'min:0'],
            'quantity_pending' => ['nullable', 'integer', 'min:0'],

            // Pricing
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_total' => ['required', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string'],
        ];
    }
}
