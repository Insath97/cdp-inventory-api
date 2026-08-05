<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PermissionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Permission Index', only: ['index', 'show']),
            new Middleware('permission:Permission Create', only: ['store']),
            new Middleware('permission:Permission Update', only: ['update']),
            new Middleware('permission:Permission Delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 1000);

            $query = Permission::query();

            // Search
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('guard_name', 'LIKE', "%{$search}%")
                        ->orWhere('group_name', 'LIKE', "%{$search}%");
                });
            }

            // Filter by guard name
            if ($request->has('guard_name')) {
                $query->where('guard_name', $request->guard_name);
            }

            // Filter by group name
            if ($request->has('group_name')) {
                $query->where('group_name', $request->group_name);
            }

            $query->orderBy('group_name', 'asc')->orderBy('name', 'asc');

            if ($perPage === 'all' || intval($perPage) >= 1000) {
                $permissions = $query->get();
            } else {
                $permissions = $query->paginate($perPage);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Permissions retrieved successfully',
                'data' => $permissions
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve permissions',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function create()
    {
    }

    public function store(CreatePermissionRequest $request)
    {
        try {
            $data = $request->validated();

            $data['guard_name'] = 'api';

            $permission = Permission::create($data);

            // Auto-grant new permission to Super Admin role
            $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'Super Admin')->first();
            if ($superAdminRole) {
                $superAdminRole->givePermissionTo($permission);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Permission created successfully',
                'data' => $permission
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create permission',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $permission = Permission::find($id);

            if (!$permission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permission not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Permission retrieved successfully',
                'data' => $permission
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve permission',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function edit(string $id)
    {
    }

    public function update(UpdatePermissionRequest $request, string $id)
    {
        try {
            $data = $request->validated();

            $permission = Permission::find($id);

            if (!$permission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permission not found',
                    'data' => []
                ], 404);
            }

            if (isset($data['guard_name'])) {
                unset($data['guard_name']);
            }

            $permission->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Permission updated successfully',
                'data' => $permission
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update permission',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $permission = Permission::find($id);

            if (!$permission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permission not found',
                    'data' => []
                ], 404);
            }

            // Check if permission is assigned to any role
            if ($permission->roles()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete permission. It is assigned to one or more roles.',
                    'data' => [
                        'assigned_roles_count' => $permission->roles()->count()
                    ]
                ], 422);
            }

            $permission->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Permission deleted successfully'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete permission',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    public function getPermissionList()
    {
        try {
            $permissions = Permission::select('id', 'name', 'group_name')
                ->orderBy('group_name', 'asc')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Permissions retrieved successfully',
                'data' => $permissions
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve permissions',
                'error' => $th->getMessage()
            ], 500);
        }
    }


     public function activate(string $id)
    {
        try {
            $permission = Permission::query()->find($id);

            if (! $permission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permission not found',
                ], 404);
            }

            if ($permission->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permission is already active',
                ], 422);
            }

            $permission->update(['is_active' => true]);

            Log::info('Permission activated', [
                'user_id' => Auth::id(),
                'permission_id' => $permission->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Permission activated successfully',
                'data' => [
                    'id' => $permission->id,
                    'is_active' => $permission->is_active,
                ]
            ]);
        } catch (
            \Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate permission',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
