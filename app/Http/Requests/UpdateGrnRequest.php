<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateGrnRequest extends FormRequest
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
        $routeParam = $this->route('grn');
        $id = is_object($routeParam) ? $routeParam->id : $routeParam;

        return [
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'branch_id' => 'required|exists:branches,id',
            'received_by' => 'required|exists:users,id',
            'grn_number' => 'required|string|max:255|unique:grns,grn_number,' . $id,
            'batch_number' => 'nullable|string|max:255',
            'received_date' => 'required|date',
            'notes' => 'nullable|string',
            'supplier_bill_number' => 'nullable|string',
            'bill_image' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value && !is_string($value) && !($value instanceof \Illuminate\Http\UploadedFile)) {
                        $fail('The ' . $attribute . ' must be a valid file or existing image path.');
                    }
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        $ext = strtolower($value->getClientOriginalExtension());
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                            $fail('The ' . $attribute . ' must be a file of type: jpg, jpeg, png, pdf.');
                        }
                        if ($value->getSize() > 5120 * 1024) {
                            $fail('The ' . $attribute . ' must not be greater than 5MB.');
                        }
                    }
                }
            ],
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