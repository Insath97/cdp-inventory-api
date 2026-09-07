<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CreateReorderLevelRequest extends BaseFormRequest
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
            'product_id' => [
                'required',
                'exists:products,id',
                Rule::unique('reorder_levels')->where(function ($query) {
                    $variantId = $this->input('product_variant_id');
                    return $query->where('branch_id', $this->input('branch_id'))
                                 ->where(function ($q) use ($variantId) {
                                     if ($variantId === null) {
                                         $q->whereNull('product_variant_id');
                                     } else {
                                         $q->where('product_variant_id', $variantId);
                                     }
                                 });
                }),
            ],
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'branch_id' => 'required|exists:branches,id',
            'min_quantity' => 'required|numeric|min:0',
            'reorder_quantity' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'product_id.unique' => 'A reorder level configuration already exists for this product, variant, and branch combination.',
        ];
    }
}
