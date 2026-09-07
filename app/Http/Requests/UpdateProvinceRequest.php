<?php

namespace App\Http\Requests;

class UpdateProvinceRequest extends BaseFormRequest
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
        $id = $this->route('province');
        return [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:10|unique:provinces,code,' . $id,
            'is_active' => 'sometimes|boolean',
        ];
    }
}
