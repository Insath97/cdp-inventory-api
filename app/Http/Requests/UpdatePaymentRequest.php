<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('payment') ?? $this->route('id');

        return [
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'grn_number' => 'nullable|string|max:255',
            'supplier_bank_account_id' => 'nullable|exists:supplier_bank_accounts,id',
            'paid_by' => 'nullable|exists:users,id',
            'payment_number' => 'sometimes|required|string|max:255|unique:payments,payment_number,' . $id,
            'payment_date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0',
            'payment_method' => 'sometimes|string|in:cash,bank_transfer,cheque',
            'reference_number' => 'nullable|string|max:255',
            'status' => 'sometimes|string|in:pending,completed,cancelled',
            'notes' => 'nullable|string',
            'bill_image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
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
