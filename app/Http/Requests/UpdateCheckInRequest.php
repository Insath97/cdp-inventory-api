<?php

namespace App\Http\Requests;

class UpdateCheckInRequest extends BaseFormRequest
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
        $routeParam = $this->route('check_in');
        $id = is_object($routeParam) ? $routeParam->id : $routeParam;

        return [
                'check_in_no' => 'sometimes|required|uuid|unique:check_ins,check_in_no,' . $id,
                'branch_id' => 'sometimes|required|exists:branches,id',
                'container_id' => 'sometimes|required|exists:containers,id',
                'product_id' => 'sometimes|required|exists:products,id',
                'quantity' => 'sometimes|required|numeric|min:0',
                'date' => 'sometimes|required|date',
                'supplier_id' => 'sometimes|required|exists:suppliers,id',
                'ref_no' => 'sometimes|required|string',
                'status' => 'sometimes|required|in:pending,completed,canceled',
                'is_active' => 'sometimes|boolean',
                'description' => 'sometimes|nullable|string',
            ];
    }

}
?>
