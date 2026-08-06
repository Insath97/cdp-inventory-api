<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGrnItemRequest;
use App\Http\Requests\UpdateGrnItemRequest;
use App\Models\GrnItem;
use App\Models\Grn;
use App\Models\Product;
use App\Models\ExpiryRecord;
use App\Services\ProductPriceService;
use App\Services\SupplierProductService;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class GrnItemController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:GrnItem Index|Grn Index', only: ['index', 'show']),
            new Middleware('permission:GrnItem Create|Grn Create', only: ['store']),
            new Middleware('permission:GrnItem Update|Grn Update', only: ['update']),
            new Middleware('permission:GrnItem Delete|Grn Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = GrnItem::with(['product', 'productVariant.product', 'unit', 'container']);

             if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->filled('grn_id')) {
                $query->where('grn_id', $request->grn_id);
            }

            if ($request->filled('purchase_order_item_id')) {
                $query->where('purchase_order_item_id', $request->purchase_order_item_id);
            }

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->filled('product_variant_id')) {
                $query->where('product_variant_id', $request->product_variant_id);
            }

            if ($request->filled('unit_id')) {
                $query->where('unit_id', $request->unit_id);
            }

            if ($request->filled('container_id')) {
                $query->where('container_id', $request->container_id);
            }

            $grnItems = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'GRN items fetched successfully',
                'data' => $grnItems,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch GRN items',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateGrnItemRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();

            $product = Product::find($data['product_id'] ?? null);
            if ($product && $product->is_pending_setup) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => "This product's setup is incomplete and cannot be received yet.",
                ], 422);
            }

            $grn = Grn::find($data['grn_id'] ?? null);
            if (empty($data['batch_number']) && $grn?->batch_number) {
                $data['batch_number'] = $grn->batch_number;
            }

            $grnItem = GrnItem::create($data);

            // Receiving this product from the GRN's supplier is what "using
            // a product under a supplier" means for direct-purchase GRNs
            // (no PO involved) — ensure the pivot link exists either way, and
            // keep its price current even if the link already existed (e.g.
            // created earlier by a PO with no price on it yet).
            if ($grn?->supplier_id && $grnItem->product_id) {
                if ($grnItem->unit_price) {
                    app(SupplierProductService::class)->syncPrice(
                        $grn->supplier_id,
                        $grnItem->product_id,
                        (float) $grnItem->unit_price
                    );
                } else {
                    app(SupplierProductService::class)->ensureLinked($grn->supplier_id, $grnItem->product_id);
                }
            }

            $qty = floatval($grnItem->quantity_received ?? 0);
            if ($qty > 0) {
                \App\Services\StockLedgerService::recordIn(
                    productId:       $grnItem->product_id,
                    variantId:       $grnItem->product_variant_id,
                    branchId:        $grn?->branch_id,
                    quantity:        $qty,
                    unitId:          $grnItem->unit_id,
                    referenceType:   Grn::class,
                    referenceId:     $grnItem->grn_id,
                    transactionDate: $grn?->received_date?->toDateString(),
                    createdBy:       Auth::id() ?? $grn?->received_by,
                );

                if ($grnItem->unit_price) {
                    ProductPriceService::recordPrice(
                        productId:  $grnItem->product_id,
                        unitPrice:  (float) $grnItem->unit_price,
                        sourceType: Grn::class,
                        sourceId:   $grnItem->grn_id,
                        date:       $grn?->received_date?->toDateString(),
                        createdBy:  Auth::id() ?? $grn?->received_by,
                    );
                }

                if ($grnItem->expiry_date) {
                    $alreadyExists = ExpiryRecord::where('grn_item_id', $grnItem->id)->exists();
                    if (! $alreadyExists) {
                        ExpiryRecord::create([
                            'grn_item_id'        => $grnItem->id,
                            'product_id'         => $grnItem->product_id,
                            'product_variant_id' => $grnItem->product_variant_id,
                            'branch_id'          => $grn?->branch_id,
                            'batch_number'       => $grnItem->batch_number ?: $grn?->batch_number,
                            'expiry_date'        => $grnItem->expiry_date,
                            'quantity'           => $qty,
                            'status'             => 'active',
                            'is_active'          => true,
                        ]);
                    }
                }
            }

            // Auto-generate PRN for short delivery
            $qtyOrdered = floatval($grnItem->quantity_ordered ?? 0);
            if ($qty < $qtyOrdered && $grn) {
                $shortfall = $qtyOrdered - $qty;
                $prn = \App\Models\PurchaseReturnNote::firstOrCreate(
                    ['grn_id' => $grn->id],
                    [
                        'supplier_id' => $grn->supplier_id,
                        'branch_id' => $grn->branch_id,
                        'created_by' => Auth::id() ?? $grn->received_by,
                        'prn_number' => 'PRN-' . date('YmdHis') . '-' . rand(1000, 9999),
                        'return_date' => now()->toDateString(),
                        'reason' => 'Short Delivery',
                        'status' => 'pending',
                    ]
                );

                \App\Models\PurchaseReturnNoteItem::updateOrCreate(
                    [
                        'purchase_return_note_id' => $prn->id,
                        'grn_item_id' => $grnItem->id,
                    ],
                    [
                        'product_id' => $grnItem->product_id,
                        'product_variant_id' => $grnItem->product_variant_id,
                        'unit_id' => $grnItem->unit_id,
                        'quantity_returned' => $shortfall,
                        'unit_price' => $grnItem->unit_price,
                        'reason' => 'Short Delivery',
                    ]
                );
            }

            DB::commit();

            $this->logActivity('CREATE', 'GrnItem', "Created GRN item: {$grnItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'GRN item created successfully',
                'data' => $grnItem->load([
                    'grn',
                    'purchaseOrderItem',
                    'product',
                    'productVariant',
                    'unit',
                    'container',
                ]),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create GRN item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $grnItem = GrnItem::with([
                'grn',
                'purchaseOrderItem',
                'product',
                'productVariant',
                'unit',
                'container',
            ])->find($id);

            if (! $grnItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'GRN item not found',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'GRN item retrieved successfully',
                'data' => $grnItem,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve GRN item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGrnItemRequest $request, string $id)
    {
        try {
            $grnItem = GrnItem::query()->find($id);

            if (! $grnItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'GRN item not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();

            $resolvedProductId = $data['product_id'] ?? $grnItem->product_id;
            $product = Product::find($resolvedProductId);
            if ($product && $product->is_pending_setup) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => "This product's setup is incomplete and cannot be received yet.",
                ], 422);
            }

            $grnItem->update($data);

            if ($grnItem->wasChanged('unit_price') && $grnItem->unit_price) {
                $grn = $grnItem->grn;
                ProductPriceService::recordPrice(
                    productId:  $grnItem->product_id,
                    unitPrice:  (float) $grnItem->unit_price,
                    sourceType: Grn::class,
                    sourceId:   $grnItem->grn_id,
                    date:       $grn?->received_date?->toDateString(),
                    createdBy:  Auth::id() ?? $grn?->received_by,
                );

                if ($grn?->supplier_id && $grnItem->product_id) {
                    app(SupplierProductService::class)->syncPrice(
                        $grn->supplier_id,
                        $grnItem->product_id,
                        (float) $grnItem->unit_price
                    );
                }
            }

            $qtyOrdered = floatval($grnItem->quantity_ordered ?? 0);
            $qtyReceived = floatval($grnItem->quantity_received ?? 0);
            $grn = $grnItem->grn;

            if ($qtyReceived < $qtyOrdered && $grn) {
                $shortfall = $qtyOrdered - $qtyReceived;
                $prn = \App\Models\PurchaseReturnNote::firstOrCreate(
                    ['grn_id' => $grn->id],
                    [
                        'supplier_id' => $grn->supplier_id,
                        'branch_id' => $grn->branch_id,
                        'created_by' => Auth::id() ?? $grn->received_by,
                        'prn_number' => 'PRN-' . date('YmdHis') . '-' . rand(1000, 9999),
                        'return_date' => now()->toDateString(),
                        'reason' => 'Short Delivery',
                        'status' => 'pending',
                    ]
                );

                \App\Models\PurchaseReturnNoteItem::updateOrCreate(
                    [
                        'purchase_return_note_id' => $prn->id,
                        'grn_item_id' => $grnItem->id,
                    ],
                    [
                        'product_id' => $grnItem->product_id,
                        'product_variant_id' => $grnItem->product_variant_id,
                        'unit_id' => $grnItem->unit_id,
                        'quantity_returned' => $shortfall,
                        'unit_price' => $grnItem->unit_price,
                        'reason' => 'Short Delivery',
                    ]
                );
            } else {
                $prnItem = \App\Models\PurchaseReturnNoteItem::where('grn_item_id', $grnItem->id)
                            ->where('reason', 'Short Delivery')
                            ->first();
                if ($prnItem) {
                    $prnId = $prnItem->purchase_return_note_id;
                    $prnItem->delete();
                    
                    if (\App\Models\PurchaseReturnNoteItem::where('purchase_return_note_id', $prnId)->count() === 0) {
                        \App\Models\PurchaseReturnNote::destroy($prnId);
                    }
                }
            }

            DB::commit();

            $this->logActivity('UPDATE', 'GrnItem', "Updated GRN item: {$grnItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'GRN item updated successfully',
                'data' => $grnItem->load([
                    'grn',
                    'purchaseOrderItem',
                    'product',
                    'productVariant',
                    'unit',
                    'container',
                ]),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update GRN item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $grnItem = GrnItem::query()->find($id);

            if (! $grnItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'GRN item not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            // 1. Reverse the stock ledger entry for this item
            $qty = floatval($grnItem->quantity_received ?? 0);
            $grn = $grnItem->grn;
            if ($qty > 0) {
                \App\Services\StockLedgerService::recordOut(
                    productId:       $grnItem->product_id,
                    variantId:       $grnItem->product_variant_id,
                    branchId:        $grn?->branch_id,
                    quantity:        $qty,
                    unitId:          $grnItem->unit_id,
                    referenceType:   Grn::class,
                    referenceId:     $grnItem->grn_id,
                    transactionDate: now()->toDateString(),
                    createdBy:       Auth::id(),
                );
            }

            ExpiryRecord::where('grn_item_id', $grnItem->id)->delete();

            $prnItems = \App\Models\PurchaseReturnNoteItem::where('grn_item_id', $grnItem->id)->get();
            foreach ($prnItems as $prnItem) {
                $prnId = $prnItem->purchase_return_note_id;
                $prnItem->delete();
                if (\App\Models\PurchaseReturnNoteItem::where('purchase_return_note_id', $prnId)->count() === 0) {
                    \App\Models\PurchaseReturnNote::destroy($prnId);
                }
            }

            // 4. Now safe to delete the GRN item
            $grnItem->delete();

            DB::commit();

            $this->logActivity('DELETE', 'GrnItem', "Deleted GRN item: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'GRN item deleted successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete GRN item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


     public function activate(string $id)
    {
        try {
            $grnItem = GrnItem::query()->find($id);

            if (! $grnItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'GRN item not found',
                ], 404);
            }

            if ($grnItem->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'GRN item is already active',
                    'data' => [
                        'id' => $grnItem->id,
                        'is_active' => $grnItem->is_active,
                    ],
                ]);
            }

            $grnItem->update(['is_active' => true]);

            Log::info('GRN item activated', [
                'user_id' => Auth::id(),
                'grn_item_id' => $grnItem->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'GRN item activated successfully',
                'data' => [
                    'id' => $grnItem->id,
                    'is_active' => $grnItem->is_active,
                ]
            ]);
        } catch (
            \Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate GRN item',
                'error' => $th->getMessage(),
            ], 500);
        }
    }


    public function deactivate(string $id)
    {
        try {
            $grnItem = GrnItem::query()->find($id);

            if (! $grnItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'GRN item not found',
                ], 404);
            }

            if (! $grnItem->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'GRN item is already inactive',
                    'data' => [
                        'id' => $grnItem->id,
                        'is_active' => $grnItem->is_active,
                    ],
                ]);
            }

            $grnItem->update(['is_active' => false]);

            Log::info('GRN item deactivated', [
                'user_id' => Auth::id(),
                'grn_item_id' => $grnItem->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'GRN item deactivated successfully',
                'data' => [
                    'id' => $grnItem->id,
                    'is_active' => $grnItem->is_active,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate GRN item',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
