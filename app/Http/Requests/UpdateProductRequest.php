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
        if ($this->has('name')) {
            $this->merge([
                'slug' => \Illuminate\Support\Str::slug($this->name),
            ]);
        }
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
        ];

        $rules['product_type'] = 'nullable|string|in:IT,Admin';

        return $rules;
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
