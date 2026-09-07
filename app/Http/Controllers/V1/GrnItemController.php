<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGrnItemRequest;
use App\Http\Requests\UpdateGrnItemRequest;
use App\Models\GrnItem;
use App\Models\GrnItemSerial;
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
use App\Traits\TogglesActiveStatus;

class GrnItemController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    use TogglesActiveStatus;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Grn Item Index|Grn Index|Product Index|ProductAssignment Index', only: ['index', 'show', 'searchAvailableSerials', 'resolveSerial']),
            new Middleware('permission:Grn Item Create|Grn Create', only: ['store', 'nextSerial']),
            new Middleware('permission:Grn Item Update|Grn Update', only: ['update', 'activate', 'deactivate']),
            new Middleware('permission:Grn Item Delete|Grn Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = GrnItem::with(['product', 'productVariant.product', 'unit', 'container', 'serials']);

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
     * Next available serial number for a product/variant, derived from every
     * numeric serial ever recorded against it (across all GRNs, not just the
     * current one) — so a freshly generated batch never collides with a
     * serial issued in an earlier receipt of the same product+variant.
     */
    public function nextSerial(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|integer|exists:products,id',
                'product_variant_id' => 'nullable|integer|exists:product_variants,id',
                'count' => 'nullable|integer|min:1|max:1000',
            ]);

            $query = GrnItemSerial::where('product_id', $request->integer('product_id'));
            if ($request->filled('product_variant_id')) {
                $query->where('product_variant_id', $request->integer('product_variant_id'));
            } else {
                $query->whereNull('product_variant_id');
            }

            $maxNumeric = $query->pluck('serial_number')
                ->filter(fn ($s) => ctype_digit((string) $s))
                ->map(fn ($s) => (int) $s)
                ->max();

            $next = $maxNumeric ? $maxNumeric + 1 : 1;
            $count = $request->integer('count') ?: 1;

            return response()->json([
                'status' => 'success',
                'message' => 'Next serial number resolved successfully',
                'data' => [
                    'next_serial' => $next,
                    'serials' => range($next, $next + $count - 1),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to resolve next serial number',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Search captured serials by product name, product code, variant SKU/
     * barcode, or the serial number itself — powers the Product Assignment
     * page's serial picker. Excludes serials that are already actively
     * assigned unless explicitly asked to include them.
     */
    public function searchAvailableSerials(Request $request)
    {
        try {
            $request->validate([
                'q' => 'nullable|string|max:255',
                'include_assigned' => 'nullable|boolean',
                'product_id' => 'nullable|integer|exists:products,id',
            ]);

            $q = trim((string) $request->query('q', ''));

            $query = GrnItemSerial::with(['product', 'productVariant']);

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->integer('product_id'));
            }

            if ($q !== '') {
                $query->where(function ($qr) use ($q) {
                    $qr->where('serial_number', 'like', "%{$q}%")
                        ->orWhereHas('product', function ($p) use ($q) {
                            $p->where('product_name', 'like', "%{$q}%")
                              ->orWhere('product_code', 'like', "%{$q}%");
                        })
                        ->orWhereHas('productVariant', function ($v) use ($q) {
                            $v->where('sku', 'like', "%{$q}%")
                              ->orWhere('barcode', 'like', "%{$q}%");
                        });
                });
            }

            if (!$request->boolean('include_assigned')) {
                $query->whereDoesntHave('assignments', function ($a) {
                    $a->where('is_active', true);
                });
            }

            $serials = $query->orderByDesc('id')->limit(50)->get()->map(function ($s) {
                return [
                    'id' => $s->id,
                    'serial_number' => $s->serial_number,
                    'product_id' => $s->product_id,
                    'product_name' => $s->product?->product_name,
                    'product_code' => $s->product?->product_code,
                    'product_variant_id' => $s->product_variant_id,
                    'variant_name' => $s->productVariant?->variant_name,
                    'variant_sku' => $s->productVariant?->sku,
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Serials fetched successfully',
                'data' => $serials,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to search serials',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Resolve a bare serial number (as scanned from a QR code) to its owning
     * product — used to route a scan straight to that product's lookup page.
     * A serial is only unique per variant (not globally), so if the same
     * text was somehow used across two variants this returns the most
     * recently captured match.
     */
    public function resolveSerial(Request $request)
    {
        try {
            $request->validate([
                'serial' => 'required|string|max:255',
            ]);

            $serial = GrnItemSerial::where('serial_number', $request->query('serial'))
                ->orderByDesc('id')
                ->first();

            if (!$serial) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No product found for this serial number.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Serial resolved successfully',
                'data' => [
                    'product_id' => $serial->product_id,
                    'product_variant_id' => $serial->product_variant_id,
                    'serial_number' => $serial->serial_number,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to resolve serial number',
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

            $serialNumbers = collect($data['serial_numbers'] ?? [])
                ->map(fn ($s) => is_string($s) ? trim($s) : $s)
                ->filter(fn ($s) => $s !== null && $s !== '')
                ->values();
            unset($data['serial_numbers']);

            if ($serialNumbers->isNotEmpty()) {
                // Products no longer carry variants, so scope the duplicate
                // check by variant when one was sent (legacy rows) and by
                // product otherwise.
                $existing = GrnItemSerial::query()
                    ->when(
                        !empty($data['product_variant_id']),
                        fn ($q) => $q->where('product_variant_id', $data['product_variant_id']),
                        fn ($q) => $q->where('product_id', $data['product_id'] ?? 0)
                    )
                    ->whereIn('serial_number', $serialNumbers)
                    ->pluck('serial_number');
                if ($existing->isNotEmpty()) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Serial number(s) already recorded for this product: ' . $existing->implode(', '),
                    ], 422);
                }
            }

            if ($product && $product->track_serial_numbers) {
                $expectedQty = (int) round(floatval($data['quantity_received'] ?? 0));
                if ($serialNumbers->count() !== $expectedQty) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => "{$product->product_name} requires a serial number for every unit received. Expected {$expectedQty}, got {$serialNumbers->count()}.",
                    ], 422);
                }
            }

            $grnItem = GrnItem::create($data);

            foreach ($serialNumbers as $serialNumber) {
                GrnItemSerial::create([
                    'grn_item_id' => $grnItem->id,
                    'product_id' => $grnItem->product_id,
                    'product_variant_id' => $grnItem->product_variant_id,
                    'serial_number' => $serialNumber,
                ]);
            }

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
                $this->recordShortDeliveryPrn($grn, $grnItem, $qtyOrdered - $qty);
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
                    'serials',
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
                'serials',
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

            $serialNumbersProvided = array_key_exists('serial_numbers', $data);
            $serialNumbers = collect($data['serial_numbers'] ?? [])
                ->map(fn ($s) => is_string($s) ? trim($s) : $s)
                ->filter(fn ($s) => $s !== null && $s !== '')
                ->values();
            unset($data['serial_numbers']);

            $resolvedVariantId = $data['product_variant_id'] ?? $grnItem->product_variant_id;
            if ($serialNumbersProvided && $serialNumbers->isNotEmpty() && !empty($resolvedVariantId)) {
                $existing = GrnItemSerial::where('product_variant_id', $resolvedVariantId)
                    ->where('grn_item_id', '!=', $grnItem->id)
                    ->whereIn('serial_number', $serialNumbers)
                    ->pluck('serial_number');
                if ($existing->isNotEmpty()) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Serial number(s) already recorded for this product: ' . $existing->implode(', '),
                    ], 422);
                }
            }

            if ($product && $product->track_serial_numbers) {
                $expectedQty = (int) round(floatval($data['quantity_received'] ?? $grnItem->quantity_received ?? 0));
                $currentSerialCount = $serialNumbersProvided ? $serialNumbers->count() : $grnItem->serials()->count();
                if ($currentSerialCount !== $expectedQty) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => "{$product->product_name} requires a serial number for every unit received. Expected {$expectedQty}, got {$currentSerialCount}.",
                    ], 422);
                }
            }

            $grnItem->update($data);

            // Full replace, same pattern as the variant-list sync elsewhere —
            // simpler than diffing individual serial rows, and this list is
            // small (one row per received unit on this single GRN item).
            if ($serialNumbersProvided) {
                $grnItem->serials()->delete();
                foreach ($serialNumbers as $serialNumber) {
                    GrnItemSerial::create([
                        'grn_item_id' => $grnItem->id,
                        'product_id' => $grnItem->product_id,
                        'product_variant_id' => $grnItem->product_variant_id,
                        'serial_number' => $serialNumber,
                    ]);
                }
            }

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
                $this->recordShortDeliveryPrn($grn, $grnItem, $qtyOrdered - $qtyReceived);
            } else {
                // `reason` lives on the parent purchase_return_notes row, not
                // on the item — filter through the relation so this doesn't
                // query a column purchase_return_note_items has never had.
                $prnItem = \App\Models\PurchaseReturnNoteItem::where('grn_item_id', $grnItem->id)
                            ->whereHas('purchaseReturnNote', function ($q) {
                                $q->where('reason', 'Short Delivery');
                            })
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
                    'serials',
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
     * Auto-generate (or refresh) the short-delivery PRN entry for a GRN item.
     *
     * Runs inside the caller's open DB transaction (store()/update() both wrap
     * it in DB::beginTransaction()/DB::commit()), so all writes here commit or
     * roll back together with the GRN item itself.
     */
    private function recordShortDeliveryPrn(Grn $grn, GrnItem $grnItem, float $shortfall): void
    {
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
                // No 'reason' here — it belongs to the PRN above, and the item
                // has neither the column nor the fillable entry for it.
            ]
        );
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
        return $this->setActiveState(GrnItem::class, $id, true, [
            'not_found' => 'GRN item not found',
            'already' => 'GRN item is already active',
            'success' => 'GRN item activated successfully',
            'failed' => 'Failed to activate GRN item',
        ], [
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($grnItem) {
                Log::info('GRN item activated', [
                    'user_id' => Auth::id(),
                    'grn_item_id' => $grnItem->id,
                ]);
            },
        ]);
    }


    public function deactivate(string $id)
    {
        return $this->setActiveState(GrnItem::class, $id, false, [
            'not_found' => 'GRN item not found',
            'already' => 'GRN item is already inactive',
            'success' => 'GRN item deactivated successfully',
            'failed' => 'Failed to deactivate GRN item',
        ], [
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($grnItem) {
                Log::info('GRN item deactivated', [
                    'user_id' => Auth::id(),
                    'grn_item_id' => $grnItem->id,
                ]);
            },
        ]);
    }
}
