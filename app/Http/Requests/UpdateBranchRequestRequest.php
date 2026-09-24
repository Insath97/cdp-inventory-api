<?php

namespace App\Http\Requests;

class UpdateBranchRequestRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('branch_request') ?? $this->route('id');
        return [
            'request_no'   => 'sometimes|string|max:255|unique:branch_requests,request_no,' . $id,
            'branch_id'    => 'sometimes|integer|exists:branches,id',
            'requested_by' => 'sometimes|integer|exists:users,id',
            'request_date' => 'sometimes|date',
            'total_items'  => 'sometimes|integer|min:1',
            'status'       => 'sometimes|string|in:pending,approved,fulfilled,rejected',
            'notes'        => 'nullable|string|max:500',
            'requested_products' => 'nullable|array',
        ];
    }
}
