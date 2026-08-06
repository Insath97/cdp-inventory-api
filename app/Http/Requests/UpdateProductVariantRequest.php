<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProductVariantRequest extends FormRequest
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
        $id = $this->route('product_variant');

        return [
            'product_id' => 'sometimes|required|exists:products,id',
            'variant_name' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'sku' => 'sometimes|required|string|max:255|unique:product_variants,sku,' . $id,
            'code' => 'nullable|string|max:255',
            'barcode' => 'sometimes|required|string|max:255|unique:product_variants,barcode,' . $id,
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
