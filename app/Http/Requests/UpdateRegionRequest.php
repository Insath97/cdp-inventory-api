<?php

namespace App\Http\Requests;

class UpdateRegionRequest extends BaseFormRequest
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
        $id = $this->route('region');
        return [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:10|unique:regions,code,' . $id,
            'zonal_id' => 'sometimes|exists:zonals,id',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
