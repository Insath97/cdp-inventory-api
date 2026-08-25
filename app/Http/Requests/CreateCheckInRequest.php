<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateCheckInRequest extends FormRequest
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
                'check_in_no' => 'required|uuid|unique:check_ins,check_in_no',
                'branch_id' => 'required|exists:branches,id',
                'container_id' => 'nullable|exists:containers,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|numeric|min:0',
                'date' => 'required|date',
                'supplier_id' => 'required|exists:suppliers,id',
                'ref_no' => 'required|string',
                'status' => 'required|in:pending,completed,canceled',
                'is_active' => 'boolean',
                'description' => 'nullable|string',
            ];
    }

    protected function failedValidation(Validator $validator)
    {
        $fieldErrors = collect($validator->errors()->getMessages())
            ->map(fn($messages, $field) => ['field' => $field, 'messages' => $messages])
            ->values();

        $message = $fieldErrors->count() > 1
            ? 'There are multiple validation errors. Please review the form and correct the issues.'
            : 'There is an issue with the input for ' . $fieldErrors->first()['field'] . '.';

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
?>
