<?php

namespace App\Http\Requests;

class UpdateInventoryDashboardRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('inventory_dashboard') ?? $this->route('id');

        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:inventory_dashboards,code,' . $id,
            'settings' => 'nullable|array',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }
}
