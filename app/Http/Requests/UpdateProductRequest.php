<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
class UpdateProductRequest extends BaseFormRequest
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
    
    protected function prepareForValidation()
    {
        if ($this->has('product_name')) {
            $this->merge([
                'slug' => $this->generateSlug($this->product_name),
            ]);
        }
    }

    /**
     * Str::slug() strips non-Latin scripts (Tamil, Sinhala, ...) down to an
     * empty string, so it can't be used alone here — fall back to a
     * unicode-safe slug that keeps the original text's letters/numbers.
     */
    private function generateSlug(string $name): string
    {
        $slug = \Illuminate\Support\Str::slug($name);
        if ($slug !== '') {
            return $slug;
        }

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($name)), '-');
    }

    public function rules(): array
    {
        $routeParam = $this->route('product');
        $id = is_object($routeParam) ? $routeParam->id : $routeParam;
        
        $user = auth('api')->user();

        $rules = [
            'brand_id' => 'nullable|exists:brands,id',
            'main_category_id' => 'nullable|exists:main_categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'measurement_id' => 'nullable|exists:measurement_units,id',
            'unit_id' => 'nullable|exists:units,id',
            'container_id' => 'nullable|exists:containers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'product_code' => 'sometimes|required|string|max:255|unique:products,product_code,' . $id,
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $id,
            'barcode' => 'nullable|string|max:255|unique:products,barcode,' . $id,
            'id_number' => 'nullable|string|max:255',
            'product_name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug,' . $id,
            'description' => 'nullable|string',
            'is_variant' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_pending_setup' => 'nullable|boolean',
            'track_serial_numbers' => 'boolean',
        ];

        $rules['product_type'] = 'nullable|string|in:IT,Admin';

        return $rules;
    }

}
