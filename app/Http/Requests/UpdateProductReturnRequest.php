<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProductReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->products)) {
            $this->merge([
                'products' => json_decode($this->products, true)
            ]);
        }
        if (empty($this->branch_name) && !empty($this->branch_id)) {
            $branch = \App\Models\Branch::find($this->branch_id);
            if ($branch) {
                $this->merge(['branch_name' => $branch->name]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'person_name' => 'sometimes|required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'branch_name' => 'sometimes|nullable|string|max:255',
            'branch_id' => 'nullable',
            'department_name' => 'nullable|string|max:255',
            'products' => 'sometimes|required|array',
            'products.*.product_id' => 'nullable',
            'products.*.product_sku' => 'nullable|string',
            'products.*.product_name' => 'nullable|string',
            'products.*.quantity' => 'required|numeric|min:1',
            'return_date' => 'sometimes|required|date',
            'remarks' => 'nullable|string',
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
