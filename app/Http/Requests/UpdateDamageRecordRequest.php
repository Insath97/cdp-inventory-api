<?php

namespace App\Http\Requests;

class UpdateDamageRecordRequest extends BaseFormRequest
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
        $routeParam = $this->route('damage_record');
        $id = is_object($routeParam) ? $routeParam->id : $routeParam;
        return [
            'product_id' => 'sometimes|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'serial_number' => 'nullable|string|max:255',
            'branch_id' => 'sometimes|exists:branches,id',
            'reported_by' => 'nullable|exists:users,id',
            'approved_by' => 'nullable|exists:users,id',
            'damage_number' => 'sometimes|required|string|max:255|unique:damaged_records,damage_number,' . $id,
            'damage_date' => 'sometimes|date',
            'quantity' => 'sometimes|numeric|min:0',
            'reason' => 'sometimes|string|max:1000',
            'status' => 'sometimes|string|in:reported,approved,cancelled',
        ];
    }
}
