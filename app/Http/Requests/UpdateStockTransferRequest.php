<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeParam = $this->route('stock_transfer');
        $id = is_object($routeParam) ? $routeParam->id : $routeParam;

        $rules = [
            'transfer_type'    => 'required|in:branch_to_branch',
            'transfer_number'  => 'required|string|unique:stock_transfers,transfer_number,' . $id,
            'transfer_date'    => 'required|date',
            'notes'            => 'nullable|string',

            'from_branch_id'   => [
                'nullable',
                'exists:branches,id',
                function ($attribute, $value, $fail) {
                    $type = $this->input('transfer_type');
                    $needsBranch = in_array($type, ['branch_to_branch', 'branch_to_employee']);
                    if ($needsBranch && empty($value)) {
                        $fail('A source branch is required for this transfer type.');
                    }
                },
            ],
            'to_branch_id'     => [
                'nullable',
                'exists:branches,id',
                function ($attribute, $value, $fail) {
                    $type = $this->input('transfer_type');
                    $needsBranch = in_array($type, ['branch_to_branch', 'employee_to_branch']);
                    if ($needsBranch && empty($value)) {
                        $fail('A destination branch is required for this transfer type.');
                    }
                },
            ],

            'from_employee_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $type = $this->input('transfer_type');
                    $needsEmployee = in_array($type, ['employee_to_employee', 'employee_to_branch']);
                    if ($needsEmployee && empty($value)) {
                        $fail('A source employee is required for this transfer type.');
                    }
                },
            ],
            'to_employee_id'   => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $type = $this->input('transfer_type');
                    $needsEmployee = in_array($type, ['employee_to_employee', 'branch_to_employee']);
                    if ($needsEmployee && empty($value)) {
                        $fail('A destination employee is required for this transfer type.');
                    }
                },
            ],

            'requested_by' => 'required|exists:users,id',
            'approved_by'  => 'nullable|exists:users,id',
        ];

        $user = auth('api')->user();
        if ($user && ($user->hasRole('Super Admin') || $user->hasRole('SUPER ADMIN') || $user->hasRole('Admin') || $user->hasRole('ADMIN'))) {
            $rules['status'] = 'required|in:draft,approved,in_transit,received,cancelled';
        } else {
            $rules['status'] = 'required|in:draft,cancelled';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'transfer_type.required' => 'Transfer type is required.',
            'transfer_type.in'       => 'Invalid transfer type selected.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errorMessages = $validator->errors();

        $fieldErrors = collect($errorMessages->getMessages())->map(function ($messages, $field) {
            return [
                'field'    => $field,
                'messages' => $messages,
            ];
        });

        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Validation failed',
            'errors'  => $fieldErrors,
        ], 422));
    }
}
