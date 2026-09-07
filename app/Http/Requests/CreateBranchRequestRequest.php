<?php

namespace App\Http\Requests;

class CreateBranchRequestRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_no'   => 'required|string|max:255|unique:branch_requests,request_no',
            'branch_id'    => 'required|integer|exists:branches,id',
            'requested_by' => 'required|integer|exists:users,id',
            'request_date' => 'required|date',
            'total_items'  => 'required|integer|min:1',
            'status'       => 'required|string|in:pending,approved,rejected',
            'notes'        => 'nullable|string|max:500',
            'requested_products' => 'nullable|array',
        ];
    }
}
