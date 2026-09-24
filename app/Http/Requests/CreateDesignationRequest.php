<?php

namespace App\Http\Requests;

class CreateDesignationRequest extends BaseFormRequest
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
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:designations,code|max:10',
            'department_id' => 'required|exists:departments,id',
            'level' => 'required|in:entry,mid,senior,lead,executive,Manager,Director',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
