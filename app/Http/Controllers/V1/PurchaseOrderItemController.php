<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PurchaseOrderItem;
use App\Http\Requests\CreatePurchaseOrderItemRequest;
use App\Http\Requests\UpdatePurchaseOrderItemRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\ActivityLogTrait;


class PurchaseOrderItemController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

     public static function middleware(): array
    {
        return [
            new Middleware('permission:PurchaseOrderItem Index', only: ['index', 'show']),
            new Middleware('permission:PurchaseOrderItem Create', only: ['store']),
            new Middleware('permission:PurchaseOrderItem Update', only: ['update']),
            new Middleware('permission:PurchaseOrderItem Delete', only: ['destroy']),
            new Middleware('permission:PurchaseOrderItem Activate/Deactivate', only: ['activate', 'deactivate']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
    {
        try{
            $perPage = $request->get('per_page', 15);

            $query = PurchaseOrderItem::query();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_default')) {
                $query->where('is_default', $request->boolean('is_default'));
            }

            if ($request->has('purchase_order_id')) {
                $query->where('purchase_order_id', $request->purchase_order_id);
            }

            if ($request->has('variant_id')) {
                $query->where('variant_id', $request->variant_id);
            }

            if ($request->has('created_by')) {
                $query->where('created_by', $request->created_by);
            }

            $purchaseOrders = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order items fetched successfully',
                'data' => $purchaseOrders
            ]);

        }catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch purchase order items',
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
    public function store(CreatePurchaseOrderItemRequest $request)
    {
         try {
            DB::beginTransaction();

            $data = $request->validated();
            $purchaseOrderItem = PurchaseOrderItem::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'PurchaseOrderItem', "Created purchase order item: {$purchaseOrderItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order item created successfully',
                'data' => $purchaseOrderItem
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create purchase order item',
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
            $purchaseOrderItem = PurchaseOrderItem::with(['purchaseOrder', 'productVariant'])->find($id);

            if (!$purchaseOrderItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order item not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order item retrieved successfully',
                'data' => $purchaseOrderItem
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve purchase order item',
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
    public function update(UpdatePurchaseOrderItemRequest $request, string $id)
    {
         try {
            $purchaseOrderItem = PurchaseOrderItem::query()->find($id);

            if (!$purchaseOrderItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order item not found',
                    'data' => []
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $purchaseOrderItem->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'PurchaseOrderItem', "Updated purchase order item: {$purchaseOrderItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order item updated successfully',
                'data' => $purchaseOrderItem
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update purchase order item',
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
            $purchaseOrderItem = PurchaseOrderItem::query()->find($id);
            if (!$purchaseOrderItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order item not found',
                    'data' => [],
                ], 404);
            }

            $title = $purchaseOrderItem->id;
            if (!PurchaseOrderItem::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete purchase order item',
                ], 500);
            }

            $this->logActivity('DELETE', 'PurchaseOrderItem', "Deleted purchase order item: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order item deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete purchase order item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


    /**
     * Activate the product.
     */
    public function activate(string $id)
    {
        try {
            $purchaseOrderItem = PurchaseOrderItem::query()->find($id);

            if (!$purchaseOrderItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order item not found',
                ], 404);
            }

            if ($purchaseOrderItem->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order item is already active',
                ], 422);
            }

            $purchaseOrderItem->update(['is_active' => true]);

            $this->logActivity('ACTIVATE', 'PurchaseOrderItem', "Activated purchase order item: {$purchaseOrderItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order item activated successfully',
                'data' => [
                    'id' => $purchaseOrderItem->id,
                    'is_active' => $purchaseOrderItem->is_active,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate purchase order item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Deactivate the product.
     */
    public function deactivate(string $id)
    {
        try {
            $purchaseOrderItem = PurchaseOrderItem::query()->find($id);

            if (!$purchaseOrderItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order item not found',
                ], 404);
            }

            if (!$purchaseOrderItem->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Purchase order item is already inactive',
                    'data' => [
                        'id' => $purchaseOrderItem->id,
                        'is_active' => $purchaseOrderItem->is_active,
                    ]
                ]);
            }

            $purchaseOrderItem->update(['is_active' => false]);

            $this->logActivity('DEACTIVATE', 'PurchaseOrderItem', "Deactivated purchase order item: {$purchaseOrderItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order item deactivated successfully',
                'data' => [
                    'id' => $purchaseOrderItem->id,
                    'is_active' => $purchaseOrderItem->is_active,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate purchase order item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }


}
