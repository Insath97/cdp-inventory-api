<?php

namespace App\Http\Requests;

class UpdateStockLedgerRequest extends BaseFormRequest
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
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'branch_id' => 'required|exists:branches,id',
            'reference_type' => 'required|string|in:grn,prn,transfer_in,transfer_out,stock_take,damage',
            'reference_id' => 'required|integer|min:1',
            'transaction_date' => 'required|date',
            'quantity_in' => 'required|numeric|min:0',
            'quantity_out' => 'required|numeric|min:0',
            'balance' => 'required|numeric',
            'unit_id' => 'required|exists:units,id',
            'created_by' => 'required|exists:users,id',
        ];
    }
}
