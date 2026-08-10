<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProductAssignmentRequest extends FormRequest
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
            'person_name' => 'sometimes|required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'branch_name' => 'sometimes|required|string|max:255',
            'department_name' => 'nullable|string|max:255',
            'product_id' => 'nullable|integer',
            'product_sku' => 'nullable|string|max:50',
            'product_name' => 'nullable|string|max:255',
            'quantity' => 'sometimes|required|integer|min:1',
            'issue_date' => 'sometimes|required|date',
            'remarks' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
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
