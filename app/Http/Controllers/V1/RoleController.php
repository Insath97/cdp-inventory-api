<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;
use App\Traits\TogglesActiveStatus;

class RoleController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    use TogglesActiveStatus;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Role Index', only: ['index', 'show']),
            new Middleware('permission:Role Create', only: ['store']),
            new Middleware('permission:Role Update', only: ['update', 'activate', 'deactivate']),
            new Middleware('permission:Role Delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = Role::with('permissions');

            // Search
            if ($request->has('search') && $request->search != '') {
                $query->where('name', 'LIKE', "%{$request->search}%");
            }

            if ($request->has('is_protected')) {
                  $query->where('is_protected', $request->boolean('is_protected'));
            }

            if ($request->has('guard_name')) {
                $query->where('guard_name', $request->guard_name);
            }

            $roles = $query->paginate($perPage);

            if ($roles->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No roles found',
                    'data' => []
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Roles retrieved successfully',
                'data' => $roles
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve roles',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function create()
    {
    }

    public function store(CreateRoleRequest $request)
    {
        try {
            $data = $request->validated();

            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => 'api',
                'is_protected' => $data['is_protected'] ?? false,
            ]);

            if (isset($data['permissions']) && count($data['permissions']) > 0) {
                $permissions = Permission::whereIn('id', $data['permissions'])->get();
                $role->syncPermissions($permissions);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Role created successfully',
                'data' => $role->load('permissions')
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create role',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $role = Role::with('permissions')->find($id);

            if (!$role) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Role not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Role retrieved successfully',
                'data' => $role
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve role',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function edit(string $id)
    {
        //
    }

    public function update(UpdateRoleRequest $request, string $id)
    {
        try {
            $data = $request->validated();

            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Role not found',
                    'data' => []
                ], 404);
            }

            if (isset($data['name'])) {
                $role->update(['name' => $data['name']]);
            }

            if (isset($data['is_protected'])) {
                $role->update(['is_protected' => $data['is_protected']]);
            }

            if (isset($data['permissions'])) {
                $permissions = Permission::whereIn('id', $data['permissions'])->get();
                $role->syncPermissions($permissions);
            }

            $role->load('permissions');

            return response()->json([
                'status' => 'success',
                'message' => 'Role updated successfully',
                'data' => $role
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update role',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Role not found',
                    'data' => []
                ], 404);
            }

            if ($role->is_protected) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete protected system role: ' . $role->name,
                    'data' => [
                        'role_name' => $role->name,
                        'protected' => true
                    ]
                ], 422);
            }

            if ($role->users()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete role. It is assigned to one or more users.',
                    'data' => [
                        'assigned_users_count' => $role->users()->count()
                    ]
                ], 422);
            }

            $role->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Role deleted successfully'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete role',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getAvailableRoles()
    {
        try {
            $user = auth('api')->user();

            $query = Role::query();

            $isSuperAdmin = $user && ($user->hasRole('Super Admin') || $user->hasRole('SUPER ADMIN'));

            // If caller is NOT a Super Admin, hide 'Super Admin' from the selectable list
            if (!$isSuperAdmin) {
                $query->whereNotIn('name', ['Super Admin', 'SUPER ADMIN']);
            }

            $query->where('guard_name', 'api');

            $roles = $query->select('id', 'name', 'guard_name')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Roles retrieved successfully',
                'data' => $roles
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve roles',
                'error' => $th->getMessage()
            ], 500);
        }
    }


     public function activate(string $id)
    {
        return $this->setActiveState(Role::class, $id, true, [
            'not_found' => 'Role not found',
            'already' => 'Role is already active',
            'success' => 'Role activated successfully',
            'failed' => 'Failed to activate role',
        ], [
            'already' => 'error',
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($role) {
                Log::info('Role activated', [
                    'user_id' => Auth::id(),
                    'role_id' => $role->id,
                ]);
            },
        ]);
    }


     public function deactivate(string $id)
    {
        try {
            $role = Role::query()->find($id);

            if (! $role) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Role not found',
                    'data' => [],
                ], 404);
            }

            if (! $role->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Role is already inactive',
                    'data' => [
                        'id' => $role->id,
                        'is_active' => (bool) $role->is_active,
                    ],
                ]);
            }

            $role->update(['is_active' => 0]);

            $this->logActivity('DEACTIVATE', 'Role', "Deactivated role: {$role->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Role deactivated successfully',
                'data' => [
                    'id' => $role->id,
                    'is_active' => (bool) $role->is_active,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate role',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
