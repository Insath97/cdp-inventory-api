<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseFormRequest extends FormRequest
{
    /**
     * Cross-field rule for a line discount that may be a percentage or a flat
     * amount: a percentage cannot exceed 100, and an amount cannot exceed the
     * line subtotal (which would otherwise make the line total negative).
     * Shared by the GRN item create/update requests.
     */
    protected function discountValueRule(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail): void {
            $value = (float) $value;
            if ($value <= 0) {
                return;
            }

            if ($this->input('discount_type') === 'amount') {
                $subtotal = (float) $this->input('quantity_received', 0) * (float) $this->input('unit_price', 0);
                if ($value > $subtotal + 0.01) {
                    $fail('The discount amount cannot be greater than the line total of ' . number_format($subtotal, 2) . '.');
                }

                return;
            }

            if ($value > 100) {
                $fail('The discount percentage cannot be greater than 100.');
            }
        };
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
