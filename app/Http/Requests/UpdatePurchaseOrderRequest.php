<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePurchaseOrderRequest extends FormRequest
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
        $routeParam = $this->route('purchase_order');
        $id = is_object($routeParam) ? $routeParam->id : $routeParam;

        return [
        'supplier_id'=> 'required|exists:suppliers,id',
        'branch_id'=> 'required|exists:branches,id',
        'created_by'=> 'required|exists:users,id',
        'approved_by'=> 'nullable|exists:users,id',
        'po_number'=> 'required|string|unique:purchase_orders,po_number,'.$id,
        'order_date'=> 'required|date',

        'expected_delivery_date'=>'nullable|date|after_or_equal:order_date',
        'actual_delivery_date'=>'nullable|date|after_or_equal:order_date',
        'status'=> 'required|in:draft,pending,approved,cancelled,received,completed',
        'subtotal'=> 'required|numeric|min:0',
        'tax_amount'=> 'required|numeric|min:0',
        'discount_amount'=> 'required|numeric|min:0',
        'shipping_cost'=> 'required|numeric|min:0',
        'total_amount'=> 'required|numeric|min:0',
        'amount_paid'=> 'required|numeric|min:0',
        'amount_due'=> 'required|numeric|min:0',
        'notes'=> 'nullable|string',
        'payment_method'=> 'nullable|string|in:Cash,Credit,Online',
        'delivery_address'=> 'nullable|string',
        'approved_at'=> 'nullable|date',
        'is_default' => 'boolean',
        'items' => ['sometimes', 'array', 'min:1'],
        'items.*.id' => ['nullable', 'integer'],
        'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
        'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
        'items.*.quantity_received' => ['nullable', 'integer', 'min:0'],
        'items.*.quantity_pending' => ['nullable', 'integer', 'min:0'],
        'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'items.*.line_total' => ['required', 'numeric', 'min:0'],
        'items.*.notes' => ['nullable', 'string'],
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
        });

        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $fieldErrors,
        ], 422));
    }
}
