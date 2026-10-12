<?php

namespace App\Http\Requests;

class UpdatePaymentRequest extends BaseFormRequest
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
            'amount' => 'sometimes|numeric|min:0.01',
            'payment_method' => 'sometimes|string|in:cash,bank_transfer,cheque',
            'reference_number' => 'nullable|string|max:255',
            'status' => 'sometimes|string|in:pending,completed,cancelled',
            'notes' => 'nullable|string',
            'bill_image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ];
    }
}
