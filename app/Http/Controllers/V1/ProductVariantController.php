<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Http\Requests\CreateProductVariantRequest;
use App\Http\Requests\UpdateProductVariantRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;
use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\TogglesActiveStatus;

class ProductVariantController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    use TogglesActiveStatus;

    /**
     * Define the middleware for permissions.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Product Variant Index', only: ['index', 'show']),
            new Middleware('permission:Product Variant Create', only: ['store']),
            new Middleware('permission:Product Variant Update', only: ['update']),
            new Middleware('permission:Product Variant Delete', only: ['destroy']),
            new Middleware('permission:Product Variant Toggle Status', only: ['toggleStatus', 'activate', 'deactivate']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ProductVariant::with(['product']);

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_default')) {
                $query->where('is_default', $request->boolean('is_default'));
            }

            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('variant_name')) {
                $query->where('variant_name', 'like', '%' . $request->variant_name . '%');
            }

            if ($request->has('color')) {
                $query->where('color', 'like', '%' . $request->color . '%');
            }

            if ($request->has('size')) {
                $query->where('size', $request->size);
            }

            $variants = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Product variants fetched successfully',
                'data' => $variants
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch product variants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateProductVariantRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $variant = ProductVariant::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'ProductVariant', "Created product variant: {$variant->sku}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant created successfully',
                'data' => $variant->load('product')
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product variant',
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
            $variant = ProductVariant::with(['product'])->find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant retrieved successfully',
                'data' => $variant
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product variant',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductVariantRequest $request, string $id)
    {
        try {
            $variant = ProductVariant::query()->find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found',
                    'data' => []
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $variant->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'ProductVariant', "Updated product variant: {$variant->sku}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant updated successfully',
                'data' => $variant->load('product')
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update product variant',
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
            $variant = ProductVariant::query()->find($id);
            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found',
                    'data' => [],
                ], 404);
            }

            $sku = $variant->sku;
            if (!$ProductVariant = ProductVariant::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to soft delete product variant',
                ], 500);
            }

            $this->logActivity('SOFT_DELETE', 'ProductVariant', "Soft deleted product variant: {$sku}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant soft deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to soft delete product variant',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of the variant.
     */
    public function toggleStatus(string $id)
    {
        return $this->setActiveState(ProductVariant::class, $id, null, [
            'not_found' => 'Product variant not found',
            'success' => 'Product variant status updated successfully',
            'failed' => 'Failed to toggle product variant status',
        ], [
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($variant) {
                Log::info('Product variant status toggled', [
                    'user_id' => Auth::id(),
                    'variant_id' => $variant->id,
                    'new_status' => $variant->is_active
                ]);
            },
        ]);
    }

    /**
     * Activate the variant.
     */
    public function activate(string $id)
    {
        return $this->setActiveState(ProductVariant::class, $id, true, [
            'not_found' => 'Product variant not found',
            'already' => 'Product variant is already active',
            'success' => 'Product variant activated successfully',
            'failed' => 'Failed to activate product variant',
        ], [
            'already' => 'error',
            'with' => 'product',
            'log' => function ($variant) {
                $this->logActivity('ACTIVATE', 'ProductVariant', "Activated product variant: {$variant->sku}");
            },
        ]);
    }

    /**
     * Deactivate the variant.
     */
    public function deactivate(string $id)
    {
        return $this->setActiveState(ProductVariant::class, $id, false, [
            'not_found' => 'Product variant not found',
            'already' => 'Product variant is already inactive',
            'success' => 'Product variant deactivated successfully',
            'failed' => 'Failed to deactivate product variant',
        ], [
            'already' => 'error',
            'with' => 'product',
            'log' => function ($variant) {
                $this->logActivity('DEACTIVATE', 'ProductVariant', "Deactivated product variant: {$variant->sku}");
            },
        ]);
    }
}
