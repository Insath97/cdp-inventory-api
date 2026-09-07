<?php

namespace App\Http\Requests;

class CreateProductReturnRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->products)) {
            $this->merge([
                'products' => json_decode($this->products, true)
            ]);
        }
        if (empty($this->branch_name) && !empty($this->branch_id)) {
            $branch = \App\Models\Branch::find($this->branch_id);
            if ($branch) {
                $this->merge(['branch_name' => $branch->name]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'person_name' => 'required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'branch_id' => 'required|integer|exists:branches,id',
            'department_name' => 'nullable|string|max:255',
            'products' => 'required|array',
            'products.*.product_id' => 'nullable',
            'products.*.product_sku' => 'nullable|string',
            'products.*.product_name' => 'nullable|string',
            'products.*.quantity' => 'required|numeric|min:1',
            'return_date' => 'required|date',
            'remarks' => 'nullable|string',
        ];
    }
}
