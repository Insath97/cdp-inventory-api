<?php

namespace App\Http\Requests;

class CreateProductVariantRequest extends BaseFormRequest
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
            'variant_name' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'sku' => 'required|string|max:255|unique:product_variants,sku',
            'code' => 'nullable|string|max:255',
            'barcode' => 'required|string|max:255|unique:product_variants,barcode',
            'image' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:255',
            'material' => 'nullable|string|max:255',
            'style' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'brand_id' => 'nullable|exists:brands,id',
            'main_category_id' => 'nullable|exists:main_categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'measurement_id' => 'nullable|exists:measurement_units,id',
            'unit_id' => 'nullable|exists:units,id',
            'container_id' => 'nullable|exists:containers,id',
        ];
    }
}
