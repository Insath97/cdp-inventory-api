<?php

namespace App\Http\Requests;

class UpdateSupplierBankAccountRequest extends BaseFormRequest
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
        $routeParam = $this->route('supplier_bank_account');
        $id = is_object($routeParam) ? $routeParam->id : $routeParam;

        return [
            'id' => 'required|exists:supplier_bank_accounts,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'bank_name' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
            'branch_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255|unique:supplier_bank_accounts,account_number,' . $id,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500'
        ];
    }
}
