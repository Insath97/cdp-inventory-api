<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateBranchRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('branch_request') ?? $this->route('id');
        return [
            'request_no'   => 'sometimes|string|max:255|unique:branch_requests,request_no,' . $id,
            'branch_id'    => 'sometimes|integer|exists:branches,id',
            'requested_by' => 'sometimes|integer|exists:users,id',
            'request_date' => 'sometimes|date',
            'total_items'  => 'sometimes|integer|min:1',
            'status'       => 'sometimes|string|in:pending,approved,fulfilled,rejected',
            'notes'        => 'nullable|string|max:500',
            'requested_products' => 'nullable|array',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errorMessages = $validator->errors();
        $fieldErrors = collect($errorMessages->getMessages())
            ->map(function ($messages, $field) {
                return [
                    'field' => $field,
                    'messages' => $messages,
                ];
            })
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
