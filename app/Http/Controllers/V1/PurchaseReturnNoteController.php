<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePurchaseReturnNoteRequest;
use App\Http\Requests\UpdatePurchaseReturnNoteRequest;
use App\Models\PurchaseReturnNote;
use App\Models\PurchaseReturnNoteItem;
use App\Models\GrnItem;
use App\Models\StockLedger;
use App\Services\StockLedgerService;
use App\Exceptions\InsufficientStockException;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class PurchaseReturnNoteController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:PurchaseReturnNote Index', only: ['index', 'show']),
            new Middleware('permission:PurchaseReturnNote Create', only: ['store']),
            new Middleware('permission:PurchaseReturnNote Update', only: ['update']),
            new Middleware('permission:PurchaseReturnNote Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = PurchaseReturnNote::query()->withCount('items');

            $user = Auth::user();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->filled('grn_id')) {
                $query->where('grn_id', $request->grn_id);
            }

            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->filled('created_by')) {
                $query->where('created_by', $request->created_by);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $purchaseReturnNotes = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return notes fetched successfully',
                'data' => $purchaseReturnNotes,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch purchase return notes',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreatePurchaseReturnNoteRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $itemsData = $data['items'] ?? [];
            unset($data['items']);

            if (empty($data['created_by'])) {
                $data['created_by'] = Auth::id() ?? 1;
            }

            $grnId = $data['grn_id'] ?? null;
            foreach ($itemsData as $itemData) {
                $qty = floatval($itemData['quantity_returned'] ?? 0);
                if ($qty <= 0) continue;

                if ($grnId) {
                    $grnItem = GrnItem::where('grn_id', $grnId)
                        ->where('product_id', $itemData['product_id'])
                        ->when($itemData['product_variant_id'] ?? null, fn($q, $v) => $q->where('product_variant_id', $v))
                        ->first();

                    if ($grnItem) {
                        $alreadyReturned = PurchaseReturnNoteItem::whereHas('purchaseReturnNote', function ($q) use ($grnId) {
                            $q->where('grn_id', $grnId);
                        })->where('product_id', $itemData['product_id'])->sum('quantity_returned');

                        $maxAllowed = floatval($grnItem->quantity_received);
                        $isShortDelivery = (isset($data['reason']) && in_array(strtolower($data['reason']), ['short delivery', 'auto-generated for short delivery'])) || 
                                           (isset($itemData['reason']) && in_array(strtolower($itemData['reason']), ['short delivery', 'auto-generated for short delivery']));
                        if ($isShortDelivery) {
                            $maxAllowed = floatval($grnItem->quantity_ordered);
                        }
                        $availableToReturn = max(0, $maxAllowed - floatval($alreadyReturned));

                        if ($qty > $availableToReturn) {
                            DB::rollBack();
                            return response()->json([
                                'status' => 'error',
                                'message' => "Returned quantity ({$qty}) exceeds available received quantity ({$availableToReturn}) in GRN.",
                            ], 422);
                        }
                    }
                }
            }

            $prn = PurchaseReturnNote::create($data);

            foreach ($itemsData as $itemData) {
                $itemData['purchase_return_note_id'] = $prn->id;
                if (empty($itemData['grn_item_id'])) {
                    $grnItem = GrnItem::where('grn_id', $prn->grn_id)
                        ->where('product_id', $itemData['product_id'])
                        ->when($itemData['product_variant_id'] ?? null, fn($q, $v) => $q->where('product_variant_id', $v))
                        ->first();
                    if ($grnItem) {
                        $itemData['grn_item_id'] = $grnItem->id;
                    }
                }
                if (!isset($itemData['reason']) && in_array(strtolower($prn->reason ?? ''), ['short delivery', 'auto-generated for short delivery'])) {
                    $itemData['reason'] = 'Short Delivery';
                }
                PurchaseReturnNoteItem::create($itemData);
            }

            if (in_array($prn->status, ['approved', 'dispatched'])) {
                $prn->load('items');
                $createdBy = $prn->created_by ?? Auth::id();

                foreach ($prn->items as $item) {
                    $qty = floatval($item->quantity_returned ?? 0);
                    if ($qty <= 0 || in_array(strtolower($item->reason ?? ''), ['short delivery', 'auto-generated for short delivery'])) continue;

                    StockLedgerService::assertSufficientStock($item->product_id, $prn->branch_id, $qty);

                    StockLedgerService::recordOut(
                        productId:       $item->product_id,
                        variantId:       $item->product_variant_id,
                        branchId:        $prn->branch_id,
                        quantity:        $qty,
                        unitId:          $item->unit_id,
                        referenceType:   PurchaseReturnNote::class,
                        referenceId:     $prn->id,
                        transactionDate: $prn->return_date?->toDateString(),
                        createdBy:       $createdBy,
                    );
                }
            }

            DB::commit();

            try {
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'PRN Created',
                    'message' => 'New Purchase Return Note ' . ($prn->prn_number ?? $prn->id) . ' has been created and is awaiting approval.',
                    'type' => 'prn_created',
                    'module' => 'purchase-returns',
                    'priority' => 'medium',
                    'reference_id' => $prn->id,
                    'reference_type' => PurchaseReturnNote::class,
                    'url' => '/purchase-returns/' . $prn->id,
                ]);

                $recipientService = app(NotificationRecipientService::class);
                $targets = $recipientService->usersByBranchRoles($prn->branch, ['ADMIN', 'SUPER ADMIN', 'REPORTING MANAGER']);
                if ($targets->isEmpty()) {
                    $targets = $recipientService->usersByRoles(['ADMIN', 'SUPER ADMIN']);
                }

                foreach ($targets as $targetUser) {
                    $targetUser->notify($notification);
                }

                $reportingManager = $recipientService->reportingManagerOf(Auth::user(), ['PurchaseReturnNote Update']);
                if ($reportingManager && !$targets->contains('id', $reportingManager->id)) {
                    $reportingManager->notify($notification);
                }
            } catch (\Throwable $notifyErr) {
                Log::error('Failed to send PRN creation notification: ' . $notifyErr->getMessage());
            }

            $this->logActivity('CREATE', 'PurchaseReturnNote', "Created PRN: {$prn->prn_number}");

            return response()->json([
                'status'  => 'success',
                'message' => 'Purchase return note created successfully',
                'data'    => $prn->load(['grn', 'supplier', 'branch', 'creator', 'items']),
            ], 201);
        } catch (InsufficientStockException $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Failed to create purchase return note: ' . $th->getMessage() . "\n" . $th->getTraceAsString());

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create purchase return note: ' . $th->getMessage(),
                'error'   => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $prn = PurchaseReturnNote::with(['grn', 'supplier', 'branch', 'creator', 'items.product', 'items.productVariant.product', 'items.unit'])->find($id);

            if (! $prn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase return note not found',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note retrieved successfully',
                'data' => $prn,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve purchase return note',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePurchaseReturnNoteRequest $request, string $id)
    {
        try {
            $prn = PurchaseReturnNote::query()->find($id);

            if (! $prn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase return note not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $itemsData = $data['items'] ?? null;
            unset($data['items']);
            unset($data['created_by']);
            if (isset($data['status']) && in_array($data['status'], ['approved', 'dispatched'])) {
                $data['approved_by'] = Auth::id();
            }

            $previousStatus = $prn->status;
            $prn->update($data);

            if ($itemsData !== null) {
                $grnId = $prn->grn_id;
                foreach ($itemsData as $itemData) {
                    $qty = floatval($itemData['quantity_returned'] ?? 0);
                    if ($qty <= 0) continue;

                    if ($grnId) {
                        $grnItem = GrnItem::where('grn_id', $grnId)
                            ->where('product_id', $itemData['product_id'])
                            ->when($itemData['product_variant_id'] ?? null, fn($q, $v) => $q->where('product_variant_id', $v))
                            ->first();

                        if ($grnItem) {
                            $alreadyReturned = PurchaseReturnNoteItem::whereHas('purchaseReturnNote', function ($q) use ($grnId, $prn) {
                                $q->where('grn_id', $grnId)->where('id', '!=', $prn->id);
                            })->where('product_id', $itemData['product_id'])->sum('quantity_returned');

                            $maxAllowed = floatval($grnItem->quantity_received);
                            $isShortDelivery = (isset($data['reason']) && in_array(strtolower($data['reason']), ['short delivery', 'auto-generated for short delivery'])) || 
                                               in_array(strtolower($prn->reason ?? ''), ['short delivery', 'auto-generated for short delivery']) || 
                                               (isset($itemData['reason']) && in_array(strtolower($itemData['reason']), ['short delivery', 'auto-generated for short delivery']));
                            if ($isShortDelivery) {
                                $maxAllowed = floatval($grnItem->quantity_ordered);
                            }
                            $availableToReturn = max(0, $maxAllowed - floatval($alreadyReturned));

                            if ($qty > $availableToReturn) {
                                \Illuminate\Support\Facades\Log::error("PRN Update Manual Validation Failed: Returned quantity ({$qty}) exceeds available received quantity ({$availableToReturn}) for item {$itemData['product_id']}");
                                DB::rollBack();
                                return response()->json([
                                    'status' => 'error',
                                    'message' => "Returned quantity ({$qty}) exceeds available received quantity ({$availableToReturn}) in GRN.",
                                ], 422);
                            }
                        }
                    }
                }

                PurchaseReturnNoteItem::where('purchase_return_note_id', $prn->id)->delete();
                foreach ($itemsData as $itemData) {
                    $itemData['purchase_return_note_id'] = $prn->id;
                    if (empty($itemData['grn_item_id'])) {
                        $grnItem = GrnItem::where('grn_id', $prn->grn_id)
                            ->where('product_id', $itemData['product_id'])
                            ->when($itemData['product_variant_id'] ?? null, fn($q, $v) => $q->where('product_variant_id', $v))
                            ->first();
                        if ($grnItem) {
                            $itemData['grn_item_id'] = $grnItem->id;
                        }
                    }
                    if (!isset($itemData['reason']) && in_array(strtolower($prn->reason ?? ''), ['short delivery', 'auto-generated for short delivery'])) {
                        $itemData['reason'] = 'Short Delivery';
                    }
                    PurchaseReturnNoteItem::create($itemData);
                }
            }

            // Post stock OUT when transitioning to 'approved' or 'dispatched'
            if (!in_array($previousStatus, ['approved', 'dispatched']) && in_array($prn->status, ['approved', 'dispatched'])) {
                $alreadyPosted = StockLedger::where('reference_type', PurchaseReturnNote::class)->where('reference_id', $prn->id)->exists();
                if (!$alreadyPosted) {
                    $prn->load('items');
                    $createdBy = Auth::id() ?? $prn->created_by;

                    foreach ($prn->items as $item) {
                        $qty = floatval($item->quantity_returned ?? 0);
                        if ($qty <= 0 || in_array(strtolower($item->reason ?? ''), ['short delivery', 'auto-generated for short delivery'])) continue;

                        StockLedgerService::assertSufficientStock($item->product_id, $prn->branch_id, $qty);

                        StockLedgerService::recordOut(
                            productId:       $item->product_id,
                            variantId:       $item->product_variant_id,
                            branchId:        $prn->branch_id,
                            quantity:        $qty,
                            unitId:          $item->unit_id,
                            referenceType:   PurchaseReturnNote::class,
                            referenceId:     $prn->id,
                            transactionDate: $prn->return_date?->toDateString(),
                            createdBy:       $createdBy,
                        );
                    }
                }
            }

            DB::commit();

            if (in_array($prn->status, ['approved', 'dispatched']) && $previousStatus !== $prn->status) {
                try {
                    $creator = User::find($prn->created_by);
                    if ($creator) {
                        $approvalNotification = new \App\Notifications\InventoryAlertNotification([
                            'title' => 'PRN Approved',
                            'message' => 'Your Purchase Return Note ' . ($prn->prn_number ?? $prn->id) . ' has been approved by ' . (Auth::user()->name ?? 'Reporting Manager') . '.',
                            'type' => 'prn_approved',
                            'module' => 'purchase-returns',
                            'priority' => 'high',
                            'reference_id' => $prn->id,
                            'reference_type' => PurchaseReturnNote::class,
                            'url' => '/purchase-returns/' . $prn->id,
                        ]);
                        $creator->notify($approvalNotification);
                    }
                } catch (\Throwable $notifyError) {
                    Log::error('Failed to send PRN approved notification: ' . $notifyError->getMessage());
                }
            }

            $this->logActivity('UPDATE', 'PurchaseReturnNote', "Updated PRN: {$prn->prn_number}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note updated successfully',
                'data' => $prn->load(['grn', 'supplier', 'branch', 'creator', 'items']),
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
                'message' => 'Failed to update purchase return note',
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
            $prn = PurchaseReturnNote::query()->find($id);

            if (! $prn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase return note not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            $prn->load('items');
            $hasStockOut = StockLedger::where('reference_type', PurchaseReturnNote::class)->where('reference_id', $prn->id)->exists();

            if ($hasStockOut) {
                $reversedBy = Auth::id();

                foreach ($prn->items as $item) {
                    $qty = floatval($item->quantity_returned ?? 0);
                    if ($qty <= 0 || in_array(strtolower($item->reason ?? ''), ['short delivery', 'auto-generated for short delivery'])) continue;

                    StockLedgerService::recordIn(
                        productId:       $item->product_id,
                        variantId:       $item->product_variant_id,
                        branchId:        $prn->branch_id,
                        quantity:        $qty,
                        unitId:          $item->unit_id,
                        referenceType:   'App\Models\PurchaseReturnNote_Reversal',
                        referenceId:     $prn->id,
                        transactionDate: now()->toDateString(),
                        createdBy:       $reversedBy,
                    );
                }
            }

            $number = $prn->prn_number;

            if (! PurchaseReturnNote::destroy($id)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete purchase return note',
                ], 500);
            }

            DB::commit();

            $this->logActivity('DELETE', 'PurchaseReturnNote', "Deleted PRN: {$number} (stock ledger reversed)");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note deleted successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete purchase return note',
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
            $product = PurchaseReturnNote::query()->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase return note not found',
                ], 404);
            }

            if ($product->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Purchase return note is already active',
                    'data' => [
                        'id' => $product->id,
                        'is_active' => $product->is_active,
                    ]
                ]);
            }

            $product->update(['is_active' => true]);

            $this->logActivity('ACTIVATE', 'PurchaseReturnNote', "Activated purchase return note: {$product->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note activated successfully',
                'data' => [
                    'id' => $product->id,
                    'is_active' => $product->is_active,
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
            $product = PurchaseReturnNote::query()->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase return note not found',
                ], 404);
            }

            if (!$product->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Purchase return note is already inactive',
                    'data' => [
                        'id' => $product->id,
                        'is_active' => $product->is_active,
                    ]
                ]);
            }

            $product->update(['is_active' => false]);

            $this->logActivity('DEACTIVATE', 'PurchaseReturnNote', "Deactivated purchase return note: {$product->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase return note deactivated successfully',
                'data' => [
                    'id' => $product->id,
                    'is_active' => $product->is_active,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate purchase return note',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
