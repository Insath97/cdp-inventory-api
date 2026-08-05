<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePurchaseReturnNoteItemRequest;
use App\Http\Requests\UpdatePurchaseReturnNoteItemRequest;
use App\Models\PurchaseReturnNoteItem;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PurchaseReturnNoteItemController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:PurchaseReturnNoteItem Index', only: ['index', 'show']),
            new Middleware('permission:PurchaseReturnNoteItem Create', only: ['store']),
            new Middleware('permission:PurchaseReturnNoteItem Update', only: ['update']),
            new Middleware('permission:PurchaseReturnNoteItem Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = PurchaseReturnNoteItem::with(['product', 'productVariant.product', 'unit']);

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->filled('purchase_return_note_id')) {
                $query->where('purchase_return_note_id', $request->purchase_return_note_id);
            }

            if ($request->filled('grn_item_id')) {
                $query->where('grn_item_id', $request->grn_item_id);
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

            $purchaseReturnNoteItems = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note items fetched successfully',
                'data' => $purchaseReturnNoteItems,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch purchase return note items',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreatePurchaseReturnNoteItemRequest $request)
    {
        try {
            DB::beginTransaction();

            $prnItem = PurchaseReturnNoteItem::create($request->validated());

            DB::commit();

            $this->logActivity('CREATE', 'PurchaseReturnNoteItem', "Created PRN item: {$prnItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note item created successfully',
                'data' => $prnItem->load([
                    'purchaseReturnNote',
                    'grnItem',
                    'product',
                    'productVariant',
                    'unit',
                ]),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create purchase return note item',
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
            $prnItem = PurchaseReturnNoteItem::with([
                'purchaseReturnNote',
                'grnItem',
                'product',
                'productVariant',
                'unit',
            ])->find($id);

            if (! $prnItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase return note item not found',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note item retrieved successfully',
                'data' => $prnItem,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve purchase return note item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePurchaseReturnNoteItemRequest $request, string $id)
    {
        try {
            $prnItem = PurchaseReturnNoteItem::query()->find($id);

            if (! $prnItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase return note item not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            $prnItem->update($request->validated());

            DB::commit();

            $this->logActivity('UPDATE', 'PurchaseReturnNoteItem', "Updated PRN item: {$prnItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note item updated successfully',
                'data' => $prnItem->load([
                    'purchaseReturnNote',
                    'grnItem',
                    'product',
                    'productVariant',
                    'unit',
                ]),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update purchase return note item',
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
            $prnItem = PurchaseReturnNoteItem::query()->find($id);

            if (! $prnItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase return note item not found',
                    'data' => [],
                ], 404);
            }

            if (! PurchaseReturnNoteItem::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete purchase return note item',
                ], 500);
            }

            $this->logActivity('DELETE', 'PurchaseReturnNoteItem', "Deleted PRN item: {$prnItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note item deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete purchase return note item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Activate the specified resource.
     */
    public function activate(string $id)
    {
        try {
            $prnItem = PurchaseReturnNoteItem::query()->find($id);

            if (! $prnItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase return note item not found',
                    'data' => [],
                ], 404);
            }

            if ($prnItem->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Purchase return note item already active',
                    'data' => [
                        'id' => $prnItem->id,
                        'is_active' => (bool) $prnItem->is_active,
                    ],
                ]);
            }

            $prnItem->update(['is_active' => 1]);

            $this->logActivity('ACTIVATE', 'PurchaseReturnNoteItem', "Activated PRN item: {$prnItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note item activated successfully',
                'data' => [
                    'id' => $prnItem->id,
                    'is_active' => (bool) $prnItem->is_active,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate purchase return note item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Deactivate the specified resource.
     */
    public function deactivate(string $id)
    {
        try {
            $prnItem = PurchaseReturnNoteItem::query()->find($id);

            if (! $prnItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase return note item not found',
                    'data' => [],
                ], 404);
            }

            if (! $prnItem->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Purchase return note item already inactive',
                    'data' => [
                        'id' => $prnItem->id,
                        'is_active' => (bool) $prnItem->is_active,
                    ],
                ]);
            }

            $prnItem->update(['is_active' => 0]);

            $this->logActivity('DEACTIVATE', 'PurchaseReturnNoteItem', "Deactivated PRN item: {$prnItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note item deactivated successfully',
                'data' => [
                    'id' => $prnItem->id,
                    'is_active' => (bool) $prnItem->is_active,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate purchase return note item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }



}
