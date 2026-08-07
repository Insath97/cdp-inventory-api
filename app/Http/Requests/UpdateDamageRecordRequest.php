<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDamageRecordRequest extends FormRequest
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
        $routeParam = $this->route('damage_record');
        $id = is_object($routeParam) ? $routeParam->id : $routeParam;
        return [
            'product_id' => 'sometimes|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'branch_id' => 'sometimes|exists:branches,id',
            'reported_by' => 'nullable|exists:users,id',
            'approved_by' => 'nullable|exists:users,id',
            'damage_number' => 'sometimes|required|string|max:255|unique:damaged_records,damage_number,' . $id,
            'damage_date' => 'sometimes|date',
            'quantity' => 'sometimes|numeric|min:0',
            'reason' => 'sometimes|string|max:1000',
            'status' => 'sometimes|string|in:reported,approved,cancelled',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
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

        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
