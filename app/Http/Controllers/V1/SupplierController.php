<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Supplier;
use App\Http\Requests\CreateSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SupplierController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Supplier Index', only: ['index', 'show']),
            new Middleware('permission:Supplier Create', only: ['store']),
            new Middleware('permission:Supplier Update', only: ['update']),
            new Middleware('permission:Supplier Delete', only: ['destroy']),
        ];
    }

     /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
         try {
            $perPage = $request->get('per_page', 15);
            $query = Supplier::query();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('supplier_name')) {
                $query->where('supplier_name', 'like', '%' . $request->supplier_name . '%');
            }

            if ($request->has('supplier_code')) {
                $query->where('supplier_code', 'like', '%' . $request->supplier_code . '%');
            }

            $suppliers = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Suppliers fetched successfully',
                'data' => $suppliers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateSupplierRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $supplier = Supplier::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'Supplier', "Created supplier: {$supplier->supplier_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier created successfully',
                'data' => $supplier
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $supplier = Supplier::query()->find($id);

            if (!$supplier) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier not found'
                ], 404);
            }

            Log::info('Supplier viewed', [
                'user_id' => Auth::id(),
                'supplier_id' => $supplier->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier retrieved successfully',
                'data' => $supplier
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to retrieve supplier', [
                'user_id' => Auth::id(),
                'error' => $th->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve supplier',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request, string $id)
    {
         try {
            $supplier = Supplier::query()->find($id);

            if (!$supplier) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier not found'
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $supplier->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'Supplier', "Updated supplier: {$supplier->supplier_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier updated successfully',
                'data' => $supplier
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
             return response()->json([
                'status' => 'error',
                'message' => 'Failed to update supplier',
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
            $supplier = Supplier::query()->find($id);
            if (! $supplier) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier not found',
                    'data' => [],
                ], 404);
            }

            $title = $supplier->supplier_name;
            if (! Supplier::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete supplier',
                ], 500);
            }

            $this->logActivity('DELETE', 'Supplier', "Deleted supplier: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete supplier',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


    public function toggleStatus(string $id)
    {
        try {
            $supplier = Supplier::query()->find($id);

            if (!$supplier) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier not found'
                ], 404);
            }

            $supplier->is_active = !$supplier->is_active;
            $supplier->save();

            Log::info('Supplier status toggled', [
                'user_id' => Auth::id(),
                'supplier_id' => $supplier->id,
                'new_status' => $supplier->is_active
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier status updated successfully',
                'data' => [
                    'id' => $supplier->id,
                    'is_active' => $supplier->is_active
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle supplier status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

     public function activate(string $id)
    {
        try {
            $supplier = Supplier::query()->find($id);

            if (! $supplier) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier not found',
                ], 404);
            }

            if ($supplier->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier is already active',
                ], 422);
            }

            $supplier->update(['is_active' => true]);

            Log::info('Supplier activated', [
                'user_id' => Auth::id(),
                'supplier_id' => $supplier->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier activated successfully',
                'data' => [
                    'id' => $supplier->id,
                    'is_active' => $supplier->is_active,
                ]
            ]);
        } catch (
            \Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate supplier',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function deactivate(string $id)
    {
        try {
            $supplier = Supplier::query()->find($id);

            if (! $supplier) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier not found',
                ], 404);
            }

            if (! $supplier->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier is already inactive',
                ], 422);
            }

            $supplier->update(['is_active' => false]);

            Log::info('Supplier deactivated', [
                'user_id' => Auth::id(),
                'supplier_id' => $supplier->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier deactivated successfully',
                'data' => [
                    'id' => $supplier->id,
                    'is_active' => $supplier->is_active,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate supplier',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
