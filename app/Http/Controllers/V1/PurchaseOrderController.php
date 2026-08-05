<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ProductVariant;
use App\Http\Requests\CreatePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\ActivityLogTrait;
use App\Services\SupplierProductService;


class PurchaseOrderController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

     public static function middleware(): array
    {
        return [
            new Middleware('permission:PurchaseOrder Index', only: ['index', 'show']),
            new Middleware('permission:PurchaseOrder Create', only: ['store']),
            new Middleware('permission:PurchaseOrder Update', only: ['update']),
            new Middleware('permission:PurchaseOrder Delete', only: ['destroy']),
            new Middleware('permission:PurchaseOrder Activate/Deactivate', only: ['activate', 'deactivate']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try{
            $perPage = $request->get('per_page', 15);

            $query = PurchaseOrder::query()->with(['branch', 'supplier', 'creator', 'approver', 'items.variant.product']);

            $user = Auth::user();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_default')) {
                $query->where('is_default', $request->boolean('is_default'));
            }

            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->has('product_id')) {
                $query->whereHas('items.variant', function ($q) use ($request) {
                    $q->where('product_id', $request->product_id);
                });
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('created_by')) {
                $query->where('created_by', $request->created_by);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $statusCountsQuery = DB::table('purchase_orders');
            if (!empty($subordinateIds)) {
                if ($isAdminOrReportingManager) {
                    $statusCountsQuery->where(function ($sq) use ($subordinateIds, $user) {
                        $sq->whereIn('created_by', $subordinateIds);
                        if ($user && $user->branch_id) {
                            $sq->orWhere('branch_id', $user->branch_id);
                        }
                    });
                } else {
                    $statusCountsQuery->where('created_by', $user->id);
                }
            }
            $statusCounts = $statusCountsQuery
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $purchaseOrders = $query->paginate($perPage);
            $purchaseOrdersArr = $purchaseOrders->toArray();
            $purchaseOrdersArr['status_counts'] = $statusCounts;

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase orders fetched successfully',
                'data' => $purchaseOrdersArr
            ]);

        }catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch purchase orders',
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
    public function store(CreatePurchaseOrderRequest $request)
    {
         try {
            DB::beginTransaction();

            $data = $request->validated();
            $items = $data['items'] ?? [];
            unset($data['items']);

            $purchaseOrder = PurchaseOrder::create($data);

            if (!empty($items)) {
                foreach ($items as &$item) {
                    if (! array_key_exists('quantity_received', $item)) {
                        $item['quantity_received'] = 0;
                    }
                    if (! array_key_exists('quantity_pending', $item)) {
                        $item['quantity_pending'] = $item['quantity_ordered'];
                    }
                }
                $purchaseOrder->items()->createMany($items);
                unset($item);

                $this->linkSupplierProducts($purchaseOrder, $items);
            }

            DB::commit();
            $purchaseOrder->load(['branch', 'supplier', 'creator', 'approver', 'items.variant.product']);

            $this->logActivity('CREATE', 'PurchaseOrder', "Created purchase order: {$purchaseOrder->id}");

            // Notify the creator, approver and procurement/inventory teams so the
            // purchase order surfaces in their dashboard notifications. Wrapped in
            // its own try/catch so a notification failure never fails PO creation.
            try {
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Purchase Order Created',
                    'message' => 'Purchase order ' . ($purchaseOrder->po_number ?? $purchaseOrder->id) . ' has been created and is awaiting approval.',
                    'type' => 'purchase_order_created',
                    'module' => 'purchase-orders',
                    'priority' => 'medium',
                    'reference_id' => $purchaseOrder->id,
                    'reference_type' => PurchaseOrder::class,
                    'url' => '/purchase-orders/' . $purchaseOrder->id,
                ]);

                $recipientService = app(\App\Services\NotificationRecipientService::class);
                $targets = $recipientService->mergeCollections(
                    $purchaseOrder->creator ? collect([$purchaseOrder->creator]) : collect(),
                    $purchaseOrder->approver ? collect([$purchaseOrder->approver]) : collect(),
                    $recipientService->procurement(),
                    $recipientService->inventoryManagers()
                );

                foreach ($targets as $user) {
                    $user->notify($notification);
                }

                // Also notify the creator's reporting manager, if one resolves
                // and isn't already covered by the groups above.
                $reportingManager = $recipientService->reportingManagerOf(
                    $purchaseOrder->creator,
                    ['PurchaseOrder Create', 'PurchaseOrder Update']
                );
                if ($reportingManager && !$targets->contains('id', $reportingManager->id)) {
                    $reportingManager->notify($notification);
                }

                // Also send a copy to the fixed order-notification mailbox
                // configured in .env (ORDER_NOTIFICATION_EMAIL).
                $orderNotificationEmail = config('mail.order_notification_address');
                if (! empty($orderNotificationEmail)) {
                    \Illuminate\Support\Facades\Mail::to($orderNotificationEmail)
                        ->send(new \App\Mail\PurchaseOrderCreatedMail($purchaseOrder));
                }
            } catch (\Throwable $notifyError) {
                \Illuminate\Support\Facades\Log::error('Failed to send purchase order created notification: ' . $notifyError->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order created successfully',
                'data' => $purchaseOrder
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Failed to create purchase order: " . $th->getMessage(), [
                'exception' => $th,
                'request' => $request->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create purchase order',
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
            $purchaseOrder = PurchaseOrder::with(['branch', 'supplier', 'creator', 'approver', 'items.variant.product'])->find($id);

            if (!$purchaseOrder) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order retrieved successfully',
                'data' => $purchaseOrder
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve purchase order',
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
    public function update(UpdatePurchaseOrderRequest $request, string $id)
    {
         try {
            $purchaseOrder = PurchaseOrder::query()->find($id);

            if (!$purchaseOrder) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                    'data' => []
                ], 404);
            }

            if (in_array($purchaseOrder->status, ['cancelled', 'received', 'completed'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot modify status or details of a completed, received, or cancelled purchase order.'
                ], 422);
            }

            $oldStatus = $purchaseOrder->status;
            $data = $request->validated();
            unset($data['created_by']);

            if (isset($data['status']) && $data['status'] === 'approved') {
                $data['approved_by'] = Auth::id();
            }

            if (isset($data['status']) && $data['status'] === 'cancelled') {
                $hasGrns = DB::table('grns')->where('purchase_order_id', $id)->exists();
                if ($hasGrns) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot cancel purchase order that has associated Goods Received Notes (GRNs).'
                    ], 422);
                }
            }

            if (isset($data['items'])) {
                $itemsData = $data['items'];
                unset($data['items']);
                $purchaseOrder->update($data);

                $keepIds = [];
                foreach ($itemsData as $item) {
                    $item['purchase_order_id'] = $purchaseOrder->id;
                    if (!isset($item['quantity_pending'])) {
                        $item['quantity_pending'] = ($item['quantity_ordered'] ?? 0) - ($item['quantity_received'] ?? 0);
                    }
                    if (!empty($item['id'])) {
                        $poItem = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)->find($item['id']);
                        if ($poItem) {
                            $poItem->update($item);
                            $keepIds[] = $poItem->id;
                        }
                    } else {
                        $newItem = PurchaseOrderItem::create($item);
                        $keepIds[] = $newItem->id;
                    }
                }
                PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)->whereNotIn('id', $keepIds)->delete();

                $this->linkSupplierProducts($purchaseOrder, $itemsData);
            } else {
                $purchaseOrder->update($data);
            }

            DB::commit();

            $purchaseOrder->refresh()->load(['branch', 'supplier', 'creator', 'approver', 'items.variant.product']);

            // Send notification to PO creator if PO status changed to approved
            if (isset($data['status']) && $data['status'] === 'approved' && $oldStatus !== 'approved') {
                try {
                    $creator = $purchaseOrder->creator;
                    if ($creator) {
                        $notification = new \App\Notifications\InventoryAlertNotification([
                            'title' => 'Purchase Order Approved',
                            'message' => 'Your Purchase Order ' . ($purchaseOrder->po_number ?? $purchaseOrder->id) . ' has been approved by ' . (Auth::user()->name ?? 'Reporting Manager') . '.',
                            'type' => 'purchase_order_approved',
                            'module' => 'purchase-orders',
                            'priority' => 'high',
                            'reference_id' => $purchaseOrder->id,
                            'reference_type' => PurchaseOrder::class,
                            'url' => '/purchase-orders/' . $purchaseOrder->id,
                        ]);
                        $creator->notify($notification);
                    }
                } catch (\Throwable $notifyError) {
                    \Illuminate\Support\Facades\Log::error('Failed to send purchase order approved notification: ' . $notifyError->getMessage());
                }
            }

            $this->logActivity('UPDATE', 'PurchaseOrder', "Updated purchase order: {$purchaseOrder->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order updated successfully',
                'data' => $purchaseOrder->load(['branch', 'supplier', 'creator', 'approver', 'items.variant.product'])
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update purchase order',
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
            $purchaseOrder = PurchaseOrder::query()->find($id);
            if (!$purchaseOrder) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                    'data' => [],
                ], 404);
            }

            $hasGrns = DB::table('grns')->where('purchase_order_id', $id)->exists();
            if ($hasGrns) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete purchase order that has associated Goods Received Notes (GRNs).'
                ], 422);
            }

            $title = $purchaseOrder->id;
            if (!PurchaseOrder::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to soft delete purchase order',
                ], 500);
            }

            $this->logActivity('SOFT_DELETE', 'PurchaseOrder', "Soft deleted purchase order: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order soft deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to soft delete purchase order',
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
            $product = PurchaseOrder::query()->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                ], 404);
            }

            if ($product->status !== 'cancelled') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order is already active',
                    'data' => [
                        'id' => $product->id,
                        'status' => $product->status,
                    ]
                ], 422);
            }

            $product->update(['status' => 'draft']);

            $this->logActivity('ACTIVATE', 'PurchaseOrder', "Activated purchase order: {$product->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order activated successfully',
                'data' => [
                    'id' => $product->id,
                    'status' => $product->status,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate product',
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
            $product = PurchaseOrder::query()->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order not found',
                ], 404);
            }

            if ($product->status === 'cancelled') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase order is already inactive',
                    'data' => [
                        'id' => $product->id,
                        'status' => $product->status,
                    ]
                ], 422);
            }

            // Prevent cancelling PO if GRNs exist
            $hasGrns = DB::table('grns')->where('purchase_order_id', $id)->exists();
            if ($hasGrns) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot cancel purchase order that has associated Goods Received Notes (GRNs).'
                ], 422);
            }

            $product->update(['status' => 'cancelled']);

            $this->logActivity('DEACTIVATE', 'PurchaseOrder', "Deactivated purchase order: {$product->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase order deactivated successfully',
                'data' => [
                    'id' => $product->id,
                    'status' => $product->status,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate purchase order',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Auto-link the PO's supplier to every product referenced by its items,
     * creating a supplier_products record (with an auto-resolved unit_id) for
     * any pair that doesn't already exist. Items only carry variant_id, so
     * product_id is resolved through the variant.
     */
    protected function linkSupplierProducts(PurchaseOrder $purchaseOrder, array $items): void
    {
        $variantIds = collect($items)->pluck('variant_id')->filter()->unique();
        if ($variantIds->isEmpty()) {
            return;
        }

        $productIdsByVariant = ProductVariant::whereIn('id', $variantIds)->pluck('product_id', 'id');
        $service = app(SupplierProductService::class);

        foreach ($items as $item) {
            $productId = $productIdsByVariant->get($item['variant_id'] ?? null);
            if (!$productId) {
                continue;
            }

            $service->ensureLinked($purchaseOrder->supplier_id, $productId, [
                'supply_quantity' => $item['quantity_ordered'] ?? 1,
            ]);
        }
    }
}
