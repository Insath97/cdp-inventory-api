<?php

namespace App\Http\Requests;

class UpdateDesignationRequest extends BaseFormRequest
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
        $id = $this->route('designation');
        return [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:10|unique:designations,code,' . $id,
            'department_id' => 'sometimes|exists:departments,id',
            'level' => 'sometimes|in:entry,mid,senior,lead,executive,Manager,Director',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
