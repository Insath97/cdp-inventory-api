<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Traits\ActivityLogTrait;
use App\Http\Requests\CreateSupplierProductsRequest;
use App\Http\Requests\UpdateSupplierProductsRequest;
use App\Services\SupplierProductService;
use App\Traits\TogglesActiveStatus;

class SupplierProductsController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    use TogglesActiveStatus;

     public static function middleware(): array
    {
        return [
            new Middleware('permission:SupplierProduct Index', only: ['index', 'show']),
            new Middleware('permission:SupplierProduct Create', only: ['store']),
            new Middleware('permission:SupplierProduct Update', only: ['update']),
            new Middleware('permission:SupplierProduct Delete', only: ['destroy']),
            new Middleware('permission:SupplierProduct Activate/Deactivate', only: ['activate', 'deactivate']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = SupplierProduct::query();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('unit_id')) {
                $query->where('unit_id', $request->unit_id);
            }

            if ($request->has('is_preferred')) {
                $query->where('is_preferred', $request->boolean('is_preferred'));
            }

            $supplierProducts = $query->paginate($perPage);

           return response()->json([
                'status' => 'success',
                'message' => 'Supplier products fetched successfully',
                'data' => $supplierProducts
            ]);

        }catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch supplier products',
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
    public function store(CreateSupplierProductsRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $supplierProduct = app(SupplierProductService::class)->ensureLinked(
                $data['supplier_id'],
                $data['product_id'],
                $data
            );

            DB::commit();

            if ($supplierProduct->wasRecentlyCreated) {
                $this->logActivity('CREATE', 'SupplierProduct', "Created supplier product: {$supplierProduct->id}");
            }

            return response()->json([
                'status' => 'success',
                'message' => $supplierProduct->wasRecentlyCreated
                    ? 'Supplier product created successfully'
                    : 'Supplier product already linked',
                'data' => $supplierProduct
            ], $supplierProduct->wasRecentlyCreated ? 201 : 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create supplier product',
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
            $supplierProduct = SupplierProduct::with(['supplier'])->find($id);

            if (!$supplierProduct) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier product not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier product retrieved successfully',
                'data' => $supplierProduct
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve supplier product',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
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
    public function update(UpdateSupplierProductsRequest $request, string $id)
    {
        try {
            $supplierProduct = SupplierProduct::query()->find($id);

            if (!$supplierProduct) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier product not found',
                    'data' => []
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $supplierProduct->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'SupplierProduct', "Updated supplier product: {$supplierProduct->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier product updated successfully',
                'data' => $supplierProduct
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update supplier product',
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
            $supplierProduct = SupplierProduct::query()->find($id);
            if (!$supplierProduct) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier product not found',
                    'data' => [],
                ], 404);
            }

            $title = $supplierProduct->id;
            if (!SupplierProduct::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete supplier product',
                ], 500);
            }

            $this->logActivity('DELETE', 'SupplierProduct', "Deleted supplier product: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier product deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete supplier product',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


    /**
     * Activate the supplier product.
     */
    public function activate(string $id)
    {
        return $this->setActiveState(SupplierProduct::class, $id, true, [
            'not_found' => 'Supplier product not found',
            'already' => 'Supplier product is already active',
            'success' => 'Supplier product activated successfully',
            'failed' => 'Failed to activate supplier product',
        ], [
            'already' => 'error',
            'data' => 'subset',
            'log' => function ($supplierProduct) {
                $this->logActivity('ACTIVATE', 'SupplierProduct', "Activated supplier product: {$supplierProduct->id}");
            },
        ]);
    }

    /**
     * Deactivate the supplier product.
     */
    public function deactivate(string $id)
    {
        return $this->setActiveState(SupplierProduct::class, $id, false, [
            'not_found' => 'Supplier product not found',
            'already' => 'Supplier product is already inactive',
            'success' => 'Supplier product deactivated successfully',
            'failed' => 'Failed to deactivate supplier product',
        ], [
            'data' => 'subset',
            'log' => function ($supplierProduct) {
                $this->logActivity('DEACTIVATE', 'SupplierProduct', "Deactivated supplier product: {$supplierProduct->id}");
            },
        ]);
    }
}
