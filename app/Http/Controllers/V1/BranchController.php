<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use App\Traits\ActivityLogTrait;

class BranchController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Branch Index', only: ['index', 'show', 'getBranchList']),
            new Middleware('permission:Branch Create', only: ['store']),
            new Middleware('permission:Branch Update', only: ['update']),
            new Middleware('permission:Branch Delete', only: ['destroy']),
            new Middleware('permission:Branch Toggle Status', only: ['activate', 'deactivate', 'toggleStatus']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Branch::query();

            if ($request->has('search') ) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('city')) {
                $query->where('city', $request->city);
            }

            $query->orderBy('created_at', 'desc');
            $branches = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Branches retrieved successfully',
                'data' => $branches
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve branches',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateBranchRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $branch = Branch::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'Branch', "Created branch: {$branch->name} ({$branch->code})");

            return response()->json([
                'status' => 'success',
                'message' => 'Branch created successfully',
                'data' => $branch
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create branch',
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
            $branch = Branch::query()->find($id);

            if (!$branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Branch retrieved successfully',
                'data' => $branch
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve branch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBranchRequest $request, string $id)
    {
        try {
            $branch = Branch::query()->find($id);

            if (!$branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch not found',
                    'data' => []
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $branch->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'Branch', "Updated branch: {$branch->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Branch updated successfully',
                'data' => $branch
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update branch',
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
            $branch = Branch::query()->find($id);
            if (! $branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch not found',
                    'data' => [],
                ], 404);
            }

            // Prevent deletion if branch is referenced in users, stock transfers, or purchase orders
            $hasUsers = $branch->users()->exists();
            $hasTransfers = DB::table('stock_transfers')->where('from_branch_id', $id)->orWhere('to_branch_id', $id)->exists();
            $hasPOs = DB::table('purchase_orders')->where('branch_id', $id)->exists();

            if ($hasUsers || $hasTransfers || $hasPOs) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete branch because it is currently referenced by users or transactions.'
                ], 422);
            }

            $title = $branch->name;
            if (! Branch::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to soft delete branch',
                ], 500);
            }

            $this->logActivity('SOFT_DELETE', 'Branch', "Soft deleted branch: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Branch soft deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to soft delete branch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Activate the branch.
     */
    public function activate(string $id)
    {
        try {
            $branch = Branch::query()->find($id);

            if (!$branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch not found',
                ], 404);
            }

            if ($branch->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Branch is already active',
                    'data' => $branch
                ]);
            }

            $branch->update(['is_active' => true]);

            $this->logActivity('ACTIVATE', 'Branch', "Activated branch: {$branch->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Branch activated successfully',
                'data' => $branch
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate branch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Deactivate the branch.
     */
     public function deactivate(string $id)
    {
        try {
            $branch = Branch::query()->find($id);

            if (! $branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch not found',
                ], 404);
            }

            if (! $branch->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Branch is already inactive',
                    'data' => $branch
                ]);
            }

            $branch->update(['is_active' => false]);

            $this->logActivity('DEACTIVATE', 'Branch', "Deactivated branch: {$branch->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Branch deactivated successfully',
                'data' => $branch
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate branch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

     public function toggleStatus(string $id)
    {
        try {
            $branch = Branch::query()->find($id);

            if (!$branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch not found'
                ], 404);
            }

            $branch->is_active = !$branch->is_active;
            $branch->save();

            $this->logActivity('TOGGLE_STATUS', 'Branch', "Toggled branch status: {$branch->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Branch status updated successfully',
                'data' => [
                    'id' => $branch->id,
                    'is_active' => $branch->is_active
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle branch status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}

