<?php

namespace App\Http\Requests;

class UpdateCheckOutRequest extends BaseFormRequest
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
            'container_id' => 'sometimes|required|exists:containers,id',
            'product_id' => 'sometimes|required|exists:products,id',
            'quantity' => 'sometimes|required|numeric|min:0',
            'checked_out_at' => 'sometimes|required|date',
            'status' => 'sometimes|nullable|in:pending,completed,canceled',
            'is_active' => 'sometimes|boolean',
            'description' => 'sometimes|nullable|string',
        ];
    }

}
?>
