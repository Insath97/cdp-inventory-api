<?php

namespace App\Http\Requests;

class CreateDamageRecordRequest extends BaseFormRequest
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
        return [
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'serial_number' => 'nullable|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'reported_by' => 'nullable|exists:users,id',
            'approved_by' => 'nullable|exists:users,id',
            'damage_number' => 'required|string|max:255|unique:damaged_records,damage_number',
            'damage_date' => 'required|date',
            'quantity' => 'required|numeric|min:0',
            'reason' => 'required|string|max:1000',
            'status' => 'required|string|in:reported,approved,cancelled',
        ];
    }
}
