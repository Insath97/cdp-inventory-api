<?php

namespace App\Http\Requests;

class UpdateBranchRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('branch');
        return [
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'branch_code' => 'sometimes|string|max:100|unique:branches,branch_code,' . $id,
            'email' => 'nullable|email|max:255',
            'contact_number' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'is_main_branch' => 'sometimes|boolean',
        ];
    }
}
