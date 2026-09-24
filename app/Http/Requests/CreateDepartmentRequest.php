<?php

namespace App\Http\Requests;

class CreateDepartmentRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:departments,code|max:50',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'head_id' => 'nullable|exists:employees,id|unique:departments,head_id',
        ];
    }
}
