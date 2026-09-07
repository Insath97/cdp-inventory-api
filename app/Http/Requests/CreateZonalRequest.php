<?php

namespace App\Http\Requests;

class CreateZonalRequest extends BaseFormRequest
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
            'code' => 'required|string|unique:zonals,code|max:10',
            'province_id' => 'required|exists:provinces,id',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
