<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use App\Traits\ActivityLogTrait;

class EmployeeController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Employee Index', only: ['index', 'show']),
            new Middleware('permission:Employee Create', only: ['store']),
            new Middleware('permission:Employee Update', only: ['update']),
            new Middleware('permission:Employee Delete', only: ['destroy']),
            new Middleware('permission:Employee Toggle Status', only: ['activate', 'deactivate', 'toggleStatus']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Employee::query();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $query->orderBy('created_at', 'desc');
            $employees = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Employees retrieved successfully',
                'data' => $employees
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve employees',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a list of employees for dropdown/select options.
     */
    public function getList()
    {
        try {
            $employees = Employee::where('is_active', true)
                ->orderBy('full_name')
                ->get(['id', 'employee_code', 'full_name', 'email']);

            return response()->json([
                'status' => 'success',
                'message' => 'Employees retrieved successfully',
                'data' => $employees
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve employees',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateEmployeeRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            if (empty($data['employee_code'])) {
                $data['employee_code'] = Employee::generateCode();
            }
            $employee = Employee::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'Employee', "Created employee: {$employee->full_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Employee created successfully',
                'data' => $employee
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create employee',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $employee = Employee::query()->find($id);

            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Employee not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Employee retrieved successfully',
                'data' => $employee
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve employee',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, string $id)
    {
        try {
            $employee = Employee::query()->find($id);

            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Employee not found',
                    'data' => []
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $employee->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'Employee', "Updated employee: {$employee->full_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Employee updated successfully',
                'data' => $employee
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update employee',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $employee = Employee::query()->find($id);
            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Employee not found',
                    'data' => [],
                ], 404);
            }

            $title = $employee->full_name;
            if (!Employee::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to soft delete employee',
                ], 500);
            }

            $this->logActivity('SOFT_DELETE', 'Employee', "Soft deleted employee: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Employee soft deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to soft delete employee',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Activate the employee.
     */
    public function activate(string $id)
    {
        try {
            $employee = Employee::find($id);

            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Employee not found',
                    'data' => [],
                ], 404);
            }

            $employee->update(['is_active' => true]);

            $this->logActivity('ACTIVATE', 'Employee', "Activated employee: {$employee->full_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Employee activated successfully',
                'data' => $employee,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate employee',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Deactivate the employee.
     */
    public function deactivate(string $id)
    {
        try {
            $employee = Employee::find($id);

            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Employee not found',
                    'data' => [],
                ], 404);
            }

            $employee->update(['is_active' => false]);

            $this->logActivity('DEACTIVATE', 'Employee', "Deactivated employee: {$employee->full_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Employee deactivated successfully',
                'data' => $employee,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate employee',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the employee status.
     */
    public function toggleStatus(string $id)
    {
        try {
            $employee = Employee::find($id);

            if (!$employee) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Employee not found',
                    'data' => [],
                ], 404);
            }

            $employee->update(['is_active' => !$employee->is_active]);

            $this->logActivity('TOGGLE_STATUS', 'Employee', "Toggled status for employee: {$employee->full_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Employee status toggled successfully',
                'data' => $employee,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle employee status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}