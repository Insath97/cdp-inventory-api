<?php

namespace App\Http\Requests;

class UpdateReportingManagerRequest extends BaseFormRequest
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
        $routeParam = $this->route('reporting_manager');
        $id = is_object($routeParam) ? $routeParam->id : $routeParam;

        return [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:reporting_managers,username,' . $id,
            'email' => 'required|string|email|max:255|unique:reporting_managers,email,' . $id,
            'password' => 'required|string|min:8',
            'role'=> 'required|string|exists:roles,name',
            'reporting_manager_id' => 'nullable|exists:reporting_managers,id',
            'is_active' => 'boolean',
            'can_login' => 'boolean',
            'phone' => 'nullable|string|max:20',
            'is_default' => 'boolean',
        ];
    }
}
