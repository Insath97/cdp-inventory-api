<?php

namespace App\Http\Requests;

class CreateExpiryRecordRequest extends BaseFormRequest
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
            'grn_item_id' => 'nullable|exists:grn_items,id',
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'branch_id' => 'required|exists:branches,id',
            'batch_number' => 'nullable|string|max:255',
            'expiry_date' => 'required|date',
            'quantity' => 'required|numeric|min:0',
            'status' => 'required|string|in:active,expired,disposed',
        ];
    }
}
