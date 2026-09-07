<?php

namespace App\Http\Requests;

class CreateCheckInRequest extends BaseFormRequest
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
                'check_in_no' => 'required|uuid|unique:check_ins,check_in_no',
                'branch_id' => 'required|exists:branches,id',
                'container_id' => 'nullable|exists:containers,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|numeric|min:0',
                'date' => 'required|date',
                'supplier_id' => 'required|exists:suppliers,id',
                'ref_no' => 'required|string',
                'status' => 'required|in:pending,completed,canceled',
                'is_active' => 'boolean',
                'description' => 'nullable|string',
            ];
    }

}
?>
