<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateBranchRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_no'   => 'required|string|max:255|unique:branch_requests,request_no',
            'branch_id'    => 'required|integer|exists:branches,id',
            'requested_by' => 'required|integer|exists:users,id',
            'request_date' => 'required|date',
            'total_items'  => 'required|integer|min:1',
            'status'       => 'required|string|in:pending,approved,rejected',
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
