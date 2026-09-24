<?php

namespace App\Http\Requests;

class CreateBranchRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'branch_code' => 'required|string|max:100|unique:branches,branch_code',
            'email' => 'nullable|email|max:255',
            'contact_number' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'is_main_branch' => 'sometimes|boolean',
        ];
    }
}
