<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStockTransferItemRequest;
use App\Http\Requests\UpdateStockTransferItemRequest;
use App\Models\StockTransferItem;
use App\Models\StockTransfer;
use App\Models\StockLedger;
use App\Services\StockLedgerService;
use App\Exceptions\InsufficientStockException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;

class StockTransferItemController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:StockTransferItem Index', only: ['index', 'show']),
            new Middleware('permission:StockTransferItem Create', only: ['store']),
            new Middleware('permission:StockTransferItem Update', only: ['update']),
            new Middleware('permission:StockTransferItem Delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = StockTransferItem::query();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->filled('stock_transfer_id')) {
                $query->where('stock_transfer_id', $request->stock_transfer_id);
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

            $stockTransferItems = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock transfer items fetched successfully',
                'data' => $stockTransferItems,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stock transfer items',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function store(CreateStockTransferItemRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // A serial-tracked row identifies one physical unit — same
            // auto-fill + qty=1 lock as Product Assignment's serial flow.
            if (!empty($data['grn_item_serial_id'])) {
                $serial = \App\Models\GrnItemSerial::find($data['grn_item_serial_id']);
                if (!$serial) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Serial number not found.',
                    ], 404);
                }
                $data['product_variant_id'] = $serial->product_variant_id;
                $data['serial_number'] = $serial->serial_number;
                $data['quantity_requested'] = 1;
            }

            // Validate against the source branch's current balance up front,
            // at save time — regardless of the transfer's status. Waiting
            // until the transfer is actually dispatched to discover the
            // requested quantity can't be fulfilled is too late for the user.
            $transfer = StockTransfer::find($data['stock_transfer_id'] ?? null);
            if ($transfer && in_array($transfer->transfer_type, ['branch_to_branch', 'branch_to_employee']) && $transfer->from_branch_id) {
                $requestedQty = floatval($data['quantity_requested'] ?? 0);
                if ($requestedQty > 0) {
                    StockLedgerService::assertSufficientStock($data['product_id'], $transfer->from_branch_id, $requestedQty);
                }
            }

            $stockTransferItem = StockTransferItem::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'StockTransferItem', "Created stock transfer item: {$stockTransferItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock transfer item created successfully',
                'data' => $stockTransferItem,
            ], 201);
        } catch (InsufficientStockException $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create stock transfer item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $stockTransferItem = StockTransferItem::with(['stockTransfer', 'product', 'productVariant', 'unit'])->find($id);

            if (! $stockTransferItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock transfer item not found',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Stock transfer item retrieved successfully',
                'data' => $stockTransferItem,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock transfer item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function update(UpdateStockTransferItemRequest $request, string $id)
    {
        try {
            $stockTransferItem = StockTransferItem::query()->find($id);

            if (! $stockTransferItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock transfer item not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            // Capture old values before update
            $oldQtySent = floatval($stockTransferItem->quantity_sent ?? $stockTransferItem->quantity_requested ?? 0);
            $oldProductId = $stockTransferItem->product_id;
            $oldVariantId = $stockTransferItem->product_variant_id;

            $data = $request->validated();
            if (!empty($data['grn_item_serial_id'])) {
                $serial = \App\Models\GrnItemSerial::find($data['grn_item_serial_id']);
                if (!$serial) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Serial number not found.',
                    ], 404);
                }
                $data['product_variant_id'] = $serial->product_variant_id;
                $data['serial_number'] = $serial->serial_number;
                $data['quantity_requested'] = 1;
            }

            $stockTransferItem->update($data);

            // Check if this item's parent transfer has already been posted to ledger
            $transfer = $stockTransferItem->stockTransfer;
            $sentStatuses = ['approved', 'in_transit', 'received'];

            if ($transfer && in_array($transfer->status, $sentStatuses)) {
                $hasLedger = StockLedger::where('reference_type', StockTransfer::class)
                    ->where('reference_id', $transfer->id)
                    ->exists();

                if ($hasLedger) {
                    $newQtySent = floatval($stockTransferItem->quantity_sent ?? $stockTransferItem->quantity_requested ?? 0);
                    $newProductId = $stockTransferItem->product_id;
                    $newVariantId = $stockTransferItem->product_variant_id;

                    $fromBranchId = in_array($transfer->transfer_type, ['branch_to_branch', 'branch_to_employee'])
                        ? $transfer->from_branch_id : null;

                    if ($fromBranchId && ($oldQtySent != $newQtySent || $oldProductId != $newProductId)) {
                        $createdBy = Auth::id() ?? $transfer->approved_by ?? $transfer->requested_by;

                        // Reverse old OUT
                        if ($oldQtySent > 0) {
                            StockLedgerService::recordIn(
                                productId:       $oldProductId,
                                variantId:       $oldVariantId,
                                branchId:        $fromBranchId,
                                quantity:        $oldQtySent,
                                unitId:          $stockTransferItem->unit_id,
                                referenceType:   StockTransfer::class . '_ItemEdit_Reversal',
                                referenceId:     $transfer->id,
                                transactionDate: now()->toDateString(),
                                createdBy:       $createdBy,
                            );
                        }

                        // Re-post new OUT
                        if ($newQtySent > 0) {
                            StockLedgerService::assertSufficientStock($newProductId, $fromBranchId, $newQtySent);
                            StockLedgerService::recordOut(
                                productId:       $newProductId,
                                variantId:       $newVariantId,
                                branchId:        $fromBranchId,
                                quantity:        $newQtySent,
                                unitId:          $stockTransferItem->unit_id,
                                referenceType:   StockTransfer::class,
                                referenceId:     $transfer->id,
                                transactionDate: now()->toDateString(),
                                createdBy:       $createdBy,
                            );
                        }
                    }
                }
            }

            DB::commit();

            $this->logActivity('UPDATE', 'StockTransferItem', "Updated stock transfer item: {$stockTransferItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock transfer item updated successfully',
                'data' => $stockTransferItem,
            ]);
        } catch (InsufficientStockException $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock transfer item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $stockTransferItem = StockTransferItem::query()->find($id);

            if (! $stockTransferItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock transfer item not found',
                    'data' => [],
                ], 404);
            }

            if (! StockTransferItem::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete stock transfer item',
                ], 500);
            }

            $this->logActivity('DELETE', 'StockTransferItem', "Deleted stock transfer item: {$stockTransferItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock transfer item deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete stock transfer item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
