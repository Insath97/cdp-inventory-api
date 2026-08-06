<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;
use App\Services\ProductPriceService;
use App\Services\SupplierProductService;
use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for permissions.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Product Index', only: ['index', 'show', 'lookupDetails']),
            new Middleware('permission:Product Create', only: ['store']),
            new Middleware('permission:Product Update', only: ['update']),
            new Middleware('permission:Product Delete', only: ['destroy']),
            new Middleware('permission:Product Toggle Status', only: ['toggleStatus', 'activate', 'deactivate']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = Product::query()->with(['brand', 'mainCategory', 'subCategory', 'measurement', 'unit', 'container', 'supplier', 'suppliers', 'variants']);

            $user = Auth::user();
            if ($user && !$user->can('Product View All')) {
                $subordinateIds = User::where('reporting_manager_id', $user->id)
                    ->orWhere('parent_user_id', $user->id)
                    ->pluck('id')
                    ->push($user->id)
                    ->toArray();

                $query->whereIn('created_by', $subordinateIds);
            }

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_variant')) {
                $query->where('is_variant', $request->boolean('is_variant'));
            }

            if ($request->has('is_default')) {
                $query->where('is_default', $request->boolean('is_default'));
            }

            if ($request->has('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            if ($request->has('main_category_id')) {
                $query->where('main_category_id', $request->main_category_id);
            }

            if ($request->has('sub_category_id')) {
                $query->where('sub_category_id', $request->sub_category_id);
            }

            if ($request->has('measurement_id')) {
                $query->where('measurement_id', $request->measurement_id);
            }

            if ($request->has('unit_id')) {
                $query->where('unit_id', $request->unit_id);
            }

            if ($request->has('container_id')) {
                $query->where('container_id', $request->container_id);
            }

            $products = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Products fetched successfully',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateProductRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            $user = Auth::user();
            if ($user) {
                $data['created_by'] = $user->id;
                if (!$user->can('Product View All')) {
                    if ($user->can('Product Type Admin')) {
                        $data['product_type'] = 'Admin';
                    } elseif ($user->can('Product Type IT')) {
                        $data['product_type'] = 'IT';
                    } else {
                        $reportingManager = $user->reportingManager;
                        if ($reportingManager) {
                            $managerRole = strtoupper($reportingManager->role);
                            if ($managerRole === 'ADMIN') {
                                $data['product_type'] = 'Admin';
                            } elseif ($managerRole === 'MANAGER') {
                                $data['product_type'] = 'IT';
                            }
                        }
                    }
                }
            }

            $data['is_variant'] = true;
            $data['is_active'] = $data['is_active'] ?? true;
            $data['is_default'] = $data['is_default'] ?? false;

            $product = Product::create($data);

            // Create variants if any
            $variants = $request->input('variants', []);
            if (!empty($variants) && is_array($variants)) {
                foreach ($variants as $variantData) {
                    $variantData['product_id'] = $product->id;
                    if (empty($variantData['variant_name'])) {
                        $variantData['variant_name'] = $variantData['sku'] ?? ($product->product_name . ' Variant');
                    }
                    ProductVariant::create($variantData);
                }
            }

            DB::commit();

            $this->logActivity('CREATE', 'Product', "Created product: {$product->product_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => $product->load(['brand', 'mainCategory', 'subCategory', 'measurement', 'unit', 'container', 'supplier', 'suppliers', 'variants'])
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product',
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
            $product = Product::with(['brand', 'mainCategory', 'subCategory', 'measurement', 'unit', 'container', 'supplier', 'suppliers', 'variants'])->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product retrieved successfully',
                'data' => $product
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get detailed product lookup data including branch-wise stock balances and recent GRNs.
     */
    public function lookupDetails(string $id)
    {
        try {
            $product = Product::with([
                'brand',
                'mainCategory',
                'subCategory',
                'measurement',
                'unit',
                'container',
                'supplier',
                'suppliers',
                'variants.brand',
                'variants.mainCategory',
                'variants.subCategory',
                'variants.measurement',
                'variants.unit',
                'variants.container',
            ])->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => null
                ], 404);
            }

            // Calculate branch-wise stock balance from stock_ledger table
            $branchStocks = DB::table('stock_ledger')
                ->join('branches', 'stock_ledger.branch_id', '=', 'branches.id')
                ->where('stock_ledger.product_id', $id)
                ->select(
                    'branches.id as branch_id',
                    'branches.name as branch_name',
                    DB::raw('SUM(quantity_in) as total_in'),
                    DB::raw('SUM(quantity_out) as total_out'),
                    DB::raw('(SUM(quantity_in) - SUM(quantity_out)) as current_balance')
                )
                ->groupBy('branches.id', 'branches.name')
                ->get();

            // Total stock across all branches
            $totalStockInHand = $branchStocks->sum('current_balance');

            // Recent GRNs received for this product
            $recentGrns = \App\Models\GrnItem::with(['grn.supplier', 'grn.branch'])
                ->where('product_id', $id)
                ->latest('id')
                ->take(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'grn_number' => $item->grn?->grn_number ?? 'N/A',
                        'batch_number' => $item->grn?->batch_number ?? 'N/A',
                        'supplier_name' => $item->grn?->supplier?->supplier_name ?? 'N/A',
                        'branch_name' => $item->grn?->branch?->name ?? 'N/A',
                        'quantity_received' => floatval($item->quantity_received ?? 0),
                        'unit_price' => floatval($item->unit_price ?? 0),
                        'received_date' => $item->grn?->received_date ? $item->grn->received_date->toDateString() : 'N/A',
                    ];
                });

            // $product->purchase_price (via the model accessor) reflects the
            // persisted column only. For this one endpoint we also want the
            // GRN/supplier_products fallback for a brand-new product that has
            // no persisted price yet — resolved separately, not written back
            // onto the model (an accessor for 'purchase_price' already exists
            // and would win over any raw attribute we tried to set here).
            $resolvedPrice = ProductPriceService::resolveCurrentPrice((int) $id);

            $priceHistory = \App\Models\ProductPriceHistory::where('product_id', $id)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->take(20)
                ->get(['id', 'unit_price', 'source_type', 'source_id', 'effective_date']);

            // Every price history row today is sourced from a GRN receipt —
            // resolve the human-readable GRN number so the report doesn't have
            // to show the raw source_id.
            $grnSourceIds = $priceHistory->where('source_type', \App\Models\Grn::class)->pluck('source_id')->unique();
            $grnNumbersById = \App\Models\Grn::whereIn('id', $grnSourceIds)->pluck('grn_number', 'id');

            $priceHistory = $priceHistory->map(function ($h) use ($grnNumbersById) {
                return [
                    'id' => $h->id,
                    'unit_price' => $h->unit_price,
                    'source_type' => $h->source_type,
                    'source_id' => $h->source_id,
                    'source_reference' => $h->source_type === \App\Models\Grn::class
                        ? ($grnNumbersById->get($h->source_id) ?? "GRN #{$h->source_id}")
                        : null,
                    'effective_date' => $h->effective_date,
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Product lookup details retrieved successfully',
                'data' => [
                    'product' => $product,
                    'last_grn_unit_price' => $resolvedPrice,
                    'total_stock_in_hand' => floatval($totalStockInHand),
                    'branch_stocks' => $branchStocks,
                    'recent_grns' => $recentGrns,
                    'price_history' => $priceHistory,
                ]
            ]);
        } catch (\Throwable $th) {
            Log::error('Product lookupDetails error: ' . $th->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product details',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, string $id)
    {
        try {
            $product = Product::query()->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();

            if (array_key_exists('is_pending_setup', $data) && !$data['is_pending_setup']) {
                $mainCategoryId = array_key_exists('main_category_id', $data) ? $data['main_category_id'] : $product->main_category_id;
                $unitId = array_key_exists('unit_id', $data) ? $data['unit_id'] : $product->unit_id;

                if (!$mainCategoryId || !$unitId) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Category and Unit are required before this product can be marked complete.',
                    ], 422);
                }
            }

            $user = Auth::user();
            if ($user) {
                if (!$user->can('Product View All')) {
                    if ($user->can('Product Type Admin')) {
                        $data['product_type'] = 'Admin';
                    } elseif ($user->can('Product Type IT')) {
                        $data['product_type'] = 'IT';
                    } else {
                        $reportingManager = $user->reportingManager;
                        if ($reportingManager) {
                            $managerRole = strtoupper($reportingManager->role);
                            if ($managerRole === 'ADMIN') {
                                $data['product_type'] = 'Admin';
                            } elseif ($managerRole === 'MANAGER') {
                                $data['product_type'] = 'IT';
                            }
                        }
                    }
                }
            }

            $data['is_variant'] = true;

            $product->update($data);

            if ($request->has('variants')) {
                $variants = $request->input('variants', []);
                if (is_array($variants)) {
                    $keepIds = collect($variants)->pluck('id')->filter()->toArray();
                    $product->variants()->whereNotIn('id', $keepIds)->delete();

                    foreach ($variants as $variantData) {
                        if (empty($variantData['variant_name'])) {
                            $variantData['variant_name'] = $variantData['sku'] ?? ($product->product_name . ' Variant');
                        }

                        if (!empty($variantData['id']) && is_numeric($variantData['id']) && $variantData['id'] < 1000000000000) {
                            $variant = ProductVariant::find($variantData['id']);
                            if ($variant) {
                                $variant->update($variantData);
                            }
                        } else {
                            $variantData['product_id'] = $product->id;
                            unset($variantData['id']);
                            ProductVariant::create($variantData);
                        }
                    }
                }
            }

            // Auto-ensure the supplier_products pivot link when supplier_id is set
            if ($product->supplier_id) {
                app(SupplierProductService::class)->ensureLinked(
                    (int) $product->supplier_id,
                    (int) $product->id,
                    [
                        'unit_id' => $product->unit_id,
                    ]
                );
            }

            DB::commit();

            $this->logActivity('UPDATE', 'Product', "Updated product: {$product->product_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => $product->load(['brand', 'mainCategory', 'subCategory', 'measurement', 'unit', 'container', 'supplier', 'suppliers', 'variants'])
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update product',
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
            $product = Product::query()->find($id);
            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => [],
                ], 404);
            }

            // Prevent deletion if product is referenced in GRN items, PO items, stock ledgers, assignments, or returns
            $hasGrnItems = DB::table('grn_items')->where('product_id', $id)->exists();
            $hasPoItems = DB::table('purchase_order_items')->where('product_id', $id)->exists();
            $hasStockLedger = DB::table('stock_ledgers')->where('product_id', $id)->exists();
            $hasAssignments = DB::table('product_assignments')->where('product_id', $id)->exists();
            $hasReturns = DB::table('product_returns')->where('product_id', $id)->exists();

            if ($hasGrnItems || $hasPoItems || $hasStockLedger || $hasAssignments || $hasReturns) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete product because it is currently referenced by inventory transactions.'
                ], 422);
            }

            $title = $product->product_name;

            DB::beginTransaction();
            // Delete associated variants safely before deleting product
            $product->variants()->delete();
            $product->delete();
            DB::commit();

            $this->logActivity('DELETE', 'Product', "Deleted product: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully',
            ]);
        } catch (\Illuminate\Database\QueryException $qe) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete product because it is referenced by active records.',
                'error' => $qe->getMessage()
            ], 422);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle the active status of the product.
     */
    public function toggleStatus(string $id)
    {
        try {
            $product = Product::query()->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found'
                ], 404);
            }

            $product->is_active = !$product->is_active;
            $product->save();

            Log::info('Product status toggled', [
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'new_status' => $product->is_active
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Product status updated successfully',
                'data' => [
                    'id' => $product->id,
                    'is_active' => $product->is_active
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle product status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Activate the product.
     */
    public function activate(string $id)
    {
        try {
            $product = Product::query()->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                ], 404);
            }

            if ($product->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product is already active',
                ], 422);
            }

            $product->update(['is_active' => true]);

            $this->logActivity('ACTIVATE', 'Product', "Activated product: {$product->product_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product activated successfully',
                'data' => $product->load(['brand', 'mainCategory', 'subCategory', 'measurement', 'unit', 'container', 'suppliers', 'variants'])
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
            $product = Product::query()->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                ], 404);
            }

            if (!$product->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product is already inactive',
                ], 422);
            }

            $product->update(['is_active' => false]);

            $this->logActivity('DEACTIVATE', 'Product', "Deactivated product: {$product->product_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product deactivated successfully',
                'data' => $product->load(['brand', 'mainCategory', 'subCategory', 'measurement', 'unit', 'container', 'suppliers', 'variants'])
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate product',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function showPublicProduct(string $id)
    {
        try {
            $product = Product::withoutGlobalScope('department')
                ->where('is_active', true)
                ->with([
                    'brand', 'mainCategory', 'subCategory', 'measurement', 'unit', 'container', 'supplier', 'suppliers',
                    'variants.brand', 'variants.mainCategory', 'variants.subCategory', 'variants.measurement', 'variants.unit', 'variants.container',
                ])
                ->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product retrieved successfully',
                'data' => $product,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product details',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
