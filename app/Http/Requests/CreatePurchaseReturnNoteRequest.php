<?php

namespace App\Http\Requests;

class CreatePurchaseReturnNoteRequest extends BaseFormRequest
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
            'supplier_id' => 'required|exists:suppliers,id',
            'branch_id' => 'required|exists:branches,id',
            'created_by' => 'nullable|exists:users,id',
            'prn_number' => 'required|string|max:255|unique:purchase_return_notes,prn_number',
            'return_date' => 'required|date',
            'reason' => 'required|string',
            'status' => 'sometimes|required|string',
            'items' => 'sometimes|array|min:1',
            'items.*.grn_item_id' => 'nullable|exists:grn_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.unit_id' => 'nullable|exists:units,id',
            'items.*.quantity_returned' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string',
        ];
    }
}
