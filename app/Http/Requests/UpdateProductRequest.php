<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProductRequest extends FormRequest
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
            'id_number' => 'nullable|string|max:255',
            'product_name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug,' . $id,
            'description' => 'nullable|string',
            'is_variant' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_pending_setup' => 'nullable|boolean',
            'track_serial_numbers' => 'boolean',

            'variants' => 'nullable|array',
            'variants.*.variant_name' => 'nullable|string|max:255',
            'variants.*.supplier_id' => 'nullable|exists:suppliers,id',
            'variants.*.code' => 'nullable|string|max:255',
            'variants.*.color' => 'nullable|string|max:255',
            'variants.*.size' => 'nullable|string|max:255',
            'variants.*.material' => 'nullable|string|max:255',
            'variants.*.style' => 'nullable|string|max:255',
            'variants.*.description' => 'nullable|string',
            'variants.*.is_default' => 'boolean',
            'variants.*.is_active' => 'boolean',
            'variants.*.brand_id' => 'nullable|exists:brands,id',
            'variants.*.main_category_id' => 'nullable|exists:main_categories,id',
            'variants.*.sub_category_id' => 'nullable|exists:sub_categories,id',
            'variants.*.measurement_id' => 'nullable|exists:measurement_units,id',
            'variants.*.unit_id' => 'nullable|exists:units,id',
            'variants.*.container_id' => 'nullable|exists:containers,id',
        ];

        $rules['product_type'] = 'nullable|string|in:IT,Admin';

        return $rules;
    }

    /**
     * sku/barcode uniqueness needs to ignore each variant's own row (by its
     * own `id`, if it already exists), which the declarative `unique` rule
     * can't express per-array-item — so it's checked manually here instead.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $variants = $this->input('variants', []);
            if (!is_array($variants)) {
                return;
            }

            $seenSkus = [];
            $seenBarcodes = [];
            foreach ($variants as $i => $variant) {
                $variantId = $variant['id'] ?? null;
                foreach (['sku', 'barcode'] as $field) {
                    $value = $variant[$field] ?? null;
                    if (empty($value)) {
                        $validator->errors()->add("variants.$i.$field", "The $field field is required.");
                        continue;
                    }

                    $seen = $field === 'sku' ? $seenSkus : $seenBarcodes;
                    if (in_array($value, $seen, true)) {
                        $validator->errors()->add("variants.$i.$field", "This $field is used by another variant in this request.");
                    }

                    $exists = \App\Models\ProductVariant::where($field, $value)
                        ->when($variantId, fn ($q) => $q->where('id', '!=', $variantId))
                        ->exists();
                    if ($exists) {
                        $validator->errors()->add("variants.$i.$field", "This $field has already been taken.");
                    }

                    if ($field === 'sku') {
                        $seenSkus[] = $value;
                    } else {
                        $seenBarcodes[] = $value;
                    }
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        $errorMessages = $validator->errors();
        $fieldErrors = collect($errorMessages->getMessages())->map(function ($messages, $field) {
            return [
                'field' => $field,
                'messages' => $messages,
            ];
        })->values();

        $message = $fieldErrors->count() > 1
            ? 'There are multiple validation errors. Please review the form and correct the issues.'
            : 'There is an issue with the input for ' . $fieldErrors->first()['field'] . '.';

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
