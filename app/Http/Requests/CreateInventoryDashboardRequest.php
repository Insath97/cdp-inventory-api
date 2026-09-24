<?php

namespace App\Http\Requests;

class CreateInventoryDashboardRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:inventory_dashboards,code',
            'settings' => 'nullable|array',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }
}
