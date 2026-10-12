<?php

namespace App\Http\Requests;

class CreatePurchaseReturnNoteItemRequest extends BaseFormRequest
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
            'purchase_return_note_id' => 'required|exists:purchase_return_notes,id',
            'grn_item_id' => 'required|exists:grn_items,id',
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'unit_id' => 'required|exists:units,id',
            'quantity_returned' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
        ];
    }
}
