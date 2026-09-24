<?php

namespace App\Http\Requests;

class CreateCheckOutRequest extends BaseFormRequest
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
            'container_id' => 'required|exists:containers,id',
            'branch_id'    => 'required|exists:branches,id',
            'product_id'   => 'required|exists:products,id',
            'quantity'     => 'required|numeric|min:0',
            'checked_out_at' => 'required|date',
            'status'         => 'nullable|in:pending,completed,canceled',
            'is_active'      => 'boolean',
            'description'  => 'nullable|string',
        ];
    }

}
?>
