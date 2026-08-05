<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStockLedgerRequest;
use App\Http\Requests\UpdateStockLedgerRequest;
use App\Models\StockLedger;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class StockLedgerController extends Controller
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:StockLedger Index|StockTake Index|CheckIn Index|CheckOut Index|Grn Index', only: ['index', 'show']),
            new Middleware('permission:StockLedger Create|StockTake Create|CheckIn Create|CheckOut Create|Grn Create', only: ['store']),
            new Middleware('permission:StockLedger Update|StockTake Update|CheckIn Update|CheckOut Update|Grn Update', only: ['update']),
            new Middleware('permission:StockLedger Delete|StockTake Delete|CheckIn Delete|CheckOut Delete|Grn Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = StockLedger::query()->with(['product', 'productVariant', 'branch', 'unit', 'creator']);

            $user = Auth::user();

            if ($request->has('search') ) {
                $query->search($request->search);
            }

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->filled('product_variant_id')) {
                $query->where('product_variant_id', $request->product_variant_id);
            }

            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->filled('reference_type')) {
                $query->where('reference_type', $request->reference_type);
            }

            if ($request->filled('reference_id')) {
                $query->where('reference_id', $request->reference_id);
            }

            if ($request->filled('transaction_date')) {
                $query->whereDate('transaction_date', $request->transaction_date);
            }

            if ($request->filled('unit_id')) {
                $query->where('unit_id', $request->unit_id);
            }

            if ($request->filled('created_by')) {
                $query->where('created_by', $request->created_by);
            }

            $stockLedgers = $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

            // Batch-resolve the friendly document number (GRN/PRN/etc.) behind each
            // "Reference Source" entry. reference_type sometimes carries a
            // "_Reversal"/"_ItemEdit_Reversal" suffix for adjustment entries — that
            // isn't a real class, so we can't use the reference() morphTo relation
            // directly (it would fatal trying to instantiate a non-existent class).
            // Strip the suffix ourselves and batch-fetch numbers per real model
            // instead, to avoid both that crash and an N+1 query per row.
            $numberFieldByModel = [
                \App\Models\Grn::class => 'grn_number',
                \App\Models\PurchaseReturnNote::class => 'prn_number',
                \App\Models\StockTransfer::class => 'transfer_number',
                \App\Models\CheckIn::class => 'check_in_no',
                \App\Models\DamagedRecord::class => 'damage_number',
                \App\Models\ExpiryRecord::class => 'batch_number',
                \App\Models\StockTake::class => 'take_number',
                // CheckOut has no natural document number — falls back to #id.
            ];

            $idsByType = [];
            foreach ($stockLedgers as $entry) {
                $baseType = preg_replace('/(_Reversal|_ItemEdit_Reversal)$/', '', $entry->reference_type ?? '');
                if (isset($numberFieldByModel[$baseType]) && $entry->reference_id) {
                    $idsByType[$baseType][] = $entry->reference_id;
                }
            }

            $numbersByType = [];
            foreach ($idsByType as $modelClass => $ids) {
                $numbersByType[$modelClass] = $modelClass::whereIn('id', array_unique($ids))
                    ->pluck($numberFieldByModel[$modelClass], 'id');
            }

            // Transform collection to attach accurate unit_price & total_amount for every stock movement
            $stockLedgers->getCollection()->transform(function ($item) use ($numbersByType) {
                $rawType = $item->reference_type ?? '';
                $isReversal = (bool) preg_match('/(_Reversal|_ItemEdit_Reversal)$/', $rawType);
                $baseType = preg_replace('/(_Reversal|_ItemEdit_Reversal)$/', '', $rawType);

                $typeLabels = [
                    \App\Models\Grn::class => 'GRN',
                    \App\Models\PurchaseReturnNote::class => 'PRN',
                    \App\Models\StockTransfer::class => 'Stock Transfer',
                    \App\Models\CheckIn::class => 'Check In',
                    \App\Models\CheckOut::class => 'Check Out',
                    \App\Models\DamagedRecord::class => 'Damage Record',
                    \App\Models\ExpiryRecord::class => 'Expiry Record',
                    \App\Models\StockTake::class => 'Stock Take',
                ];

                $number = $numbersByType[$baseType][$item->reference_id] ?? null;
                
                // Format CheckIn and CheckOut document numbers cleanly
                if ($baseType === \App\Models\CheckIn::class) {
                    if (!$number || (str_contains((string)$number, '-') && strlen((string)$number) > 20)) {
                        $number = 'CI-' . str_pad($item->reference_id, 4, '0', STR_PAD_LEFT);
                    }
                } elseif ($baseType === \App\Models\CheckOut::class) {
                    $number = 'CO-' . str_pad($item->reference_id, 4, '0', STR_PAD_LEFT);
                }

                $typeLabel = $typeLabels[$baseType] ?? (class_basename($baseType) ?: 'Record');

                $item->reference_type_label = $typeLabel;
                $item->reference_number = $number;
                $item->reference_is_reversal = $isReversal;
                $item->reference_label = trim(
                    $typeLabel . ' ' . ($number ?: '#' . $item->reference_id) . ($isReversal ? ' (Reversal)' : '')
                );

                $unitPrice = floatval($item->unit_price ?? 0);

                if (!$unitPrice && str_contains($item->reference_type, 'Grn')) {
                    $unitPrice = DB::table('grn_items')
                        ->where('grn_id', $item->reference_id)
                        ->where('product_id', $item->product_id)
                        ->value('unit_price') ?? 0;
                }

                if (!$unitPrice && str_contains($item->reference_type, 'PurchaseReturnNote')) {
                    $unitPrice = DB::table('purchase_return_note_items')
                        ->where('purchase_return_note_id', $item->reference_id)
                        ->where('product_id', $item->product_id)
                        ->value('unit_price') ?? 0;
                }

                // Product's current resolved price (persisted latest_purchase_price,
                // falling back to GRN/supplier_products — see ProductPriceService).
                if (!$unitPrice && $item->product_id) {
                    $unitPrice = \App\Services\ProductPriceService::resolveCurrentPrice($item->product_id);
                }

                $item->unit_price = (float) $unitPrice;
                return $item;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Stock ledger entries fetched successfully',
                'data' => $stockLedgers,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stock ledger entries',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateStockLedgerRequest $request)
    {
        try {
            DB::beginTransaction();

            $stockLedger = StockLedger::create($request->validated());

            DB::commit();

            $this->logActivity('CREATE', 'StockLedger', "Created stock ledger entry: {$stockLedger->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock ledger entry created successfully',
                'data' => $stockLedger->load(['product', 'productVariant', 'branch', 'unit', 'creator']),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create stock ledger entry',
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
            $stockLedger = StockLedger::with(['product', 'productVariant', 'branch', 'unit', 'creator'])->find($id);

            if (! $stockLedger) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock ledger entry not found',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Stock ledger entry retrieved successfully',
                'data' => $stockLedger,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock ledger entry',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStockLedgerRequest $request, string $id)
    {
        try {
            $stockLedger = StockLedger::query()->find($id);

            if (! $stockLedger) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock ledger entry not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            $stockLedger->update($request->validated());

            DB::commit();

            $this->logActivity('UPDATE', 'StockLedger', "Updated stock ledger entry: {$stockLedger->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock ledger entry updated successfully',
                'data' => $stockLedger->load(['product', 'productVariant', 'branch', 'unit', 'creator']),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock ledger entry',
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
            $stockLedger = StockLedger::query()->find($id);

            if (! $stockLedger) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock ledger entry not found',
                    'data' => [],
                ], 404);
            }

            if (! StockLedger::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete stock ledger entry',
                ], 500);
            }

            $this->logActivity('DELETE', 'StockLedger', "Deleted stock ledger entry: {$stockLedger->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock ledger entry deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete stock ledger entry',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


     public function activate(string $id)
    {
        try {
            $stockLedger = StockLedger::query()->find($id);

            if (! $stockLedger) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock ledger entry not found',
                ], 404);
            }

            if ($stockLedger->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock ledger entry is already active',
                ], 422);
            }

            $stockLedger->update(['is_active' => true]);

            Log::info('Stock ledger entry activated', [
                'user_id' => Auth::id(),
                'stock_ledger_id' => $stockLedger->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock ledger entry activated successfully',
                'data' => [
                    'id' => $stockLedger->id,
                    'is_active' => $stockLedger->is_active,
                ]
            ]);
        } catch (
            \Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate stock ledger entry',
                'error' => $th->getMessage(),
            ], 500);
        }
    }


     public function deactivate(string $id)
    {
        try {
            $stockLedger = StockLedger::query()->find($id);

            if (! $stockLedger) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock ledger entry not found',
                    'data' => [],
                ], 404);
            }

            if (! $stockLedger->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Stock ledger entry already inactive',
                    'data' => [
                        'id' => $stockLedger->id,
                        'is_active' => (bool) $stockLedger->is_active,
                    ],
                ]);
            }

            $stockLedger->update(['is_active' => 0]);

            $this->logActivity('DEACTIVATE', 'StockLedger', "Deactivated stock ledger entry: {$stockLedger->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock ledger entry deactivated successfully',
                'data' => [
                    'id' => $stockLedger->id,
                    'is_active' => (bool) $stockLedger->is_active,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate stock ledger entry',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
