<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInventoryDashboardRequest;
use App\Http\Requests\UpdateInventoryDashboardRequest;
use App\Models\InventoryDashboard;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\StockTransfer;
use App\Models\DamagedRecord;
use App\Models\Payment;
use App\Models\ExpiryRecord;
use App\Models\StockLedger;
use App\Models\ReorderLevel;
use App\Models\Grn;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\ActivityLogTrait;

class InventoryDashboardController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Inventory Dashboard Index', only: ['index', 'show', 'getStats']),
            new Middleware('permission:Inventory Dashboard Create', only: ['store']),
            new Middleware('permission:Inventory Dashboard Update', only: ['update']),
            new Middleware('permission:Inventory Dashboard Delete', only: ['destroy']),
            new Middleware('permission:Inventory Dashboard Toggle Status', only: ['activate', 'deactivate', 'toggleStatus']),
        ];
    }

    /**
     * Get aggregated inventory dashboard metrics & data.
     */
    public function getStats(Request $request)
    {
        try {
            $user = Auth::user();
            
            $isAdminOrReportingManager = false;
            $subordinateIds = null;
            
            if ($user) {
                $roles = $user->roles->pluck('name')->toArray();
                $isAdminOrReportingManager = count(array_intersect(['Admin', 'Super Admin', 'admin', 'super-admin', 'reporting_manager', 'Reporting Manager'], $roles)) > 0;
                
                if (in_array('Reporting Manager', $roles) || in_array('reporting_manager', $roles)) {
                    $subordinateIds = \App\Models\User::where('reporting_manager_id', $user->id)->pluck('id')->toArray();
                    $subordinateIds[] = $user->id;
                } elseif (!$isAdminOrReportingManager) {
                    $subordinateIds = [$user->id];
                }
            }

            $productQuery = Product::query();
            $poQuery = PurchaseOrder::query();

            $totalProductsCount = (clone $productQuery)->count();
            $recentProductsCount = (clone $productQuery)->where('created_at', '>=', now()->subDays(30))->count();

            $pendingPosCount = (clone $poQuery)->whereIn('status', ['pending', 'draft'])->count();
            $awaitingApprovalCount = (clone $poQuery)->where('status', 'pending')->count();

            $reorderLevels = ReorderLevel::with(['product', 'productVariant', 'branch'])
                ->where('is_active', true)
                ->get();

            $lowStockCount = 0;
            $reorderAlerts = [];

            $latestBalances = DB::table('stock_ledger as sl')
                ->joinSub(
                    DB::table('stock_ledger')
                        ->select('product_id', DB::raw('COALESCE(product_variant_id, 0) as var_id'), DB::raw('COALESCE(branch_id, 0) as br_id'), DB::raw('MAX(id) as max_id'))
                        ->groupBy('product_id', DB::raw('COALESCE(product_variant_id, 0)'), DB::raw('COALESCE(branch_id, 0)')),
                    'latest',
                    'sl.id',
                    '=',
                    'latest.max_id'
                )
                ->select('sl.product_id', 'sl.product_variant_id', 'sl.branch_id', 'sl.balance')
                ->get()
                ->keyBy(fn($row) => $row->product_id . '_' . ($row->product_variant_id ?? 0) . '_' . ($row->branch_id ?? 0));

            foreach ($reorderLevels as $level) {
                if (!$level->product) continue;
                
                $key = $level->product_id . '_' . ($level->product_variant_id ?? 0) . '_' . ($level->branch_id ?? 0);
                $latest = $latestBalances->get($key);
                $currentStock = $latest ? floatval($latest->balance) : 0.0;
                $minStock = floatval($level->min_quantity);

                if ($currentStock <= $minStock) {
                    $lowStockCount++;
                    
                    $productName = ($level->productVariant && $level->productVariant->variant_name) 
                        ? $level->product->product_name . ' (' . $level->productVariant->variant_name . ')' 
                        : $level->product->product_name;

                    $reorderAlerts[] = [
                        'product_name' => $productName,
                        'branch' => $level->branch->name ?? 'Unknown Branch',
                        'current_stock' => $currentStock,
                        'min_stock' => $minStock,
                        'unit' => $level->product->unit->name ?? 'Pcs',
                        'ratio' => $minStock > 0 ? round(($currentStock / $minStock) * 100) : 0
                    ];
                }
            }
            // Sort by ratio ascending (critical first)
            usort($reorderAlerts, function ($a, $b) {
                return $a['ratio'] <=> $b['ratio'];
            });
            $reorderAlerts = array_slice($reorderAlerts, 0, 5);

            // 4. Expiring Soon (within 30 days)
            $expiringSoonCount = ExpiryRecord::where('status', 'active')
                ->whereBetween('expiry_date', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
                ->count();

            $expiringSoonList = ExpiryRecord::with(['product', 'productVariant', 'branch'])
                ->where('status', 'active')
                ->whereBetween('expiry_date', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
                ->orderBy('expiry_date', 'asc')
                ->limit(5)
                ->get()
                ->map(function ($record) {
                    if (!$record->product) return null;
                    $productName = ($record->productVariant && $record->productVariant->variant_name) 
                        ? $record->product->product_name . ' (' . $record->productVariant->variant_name . ')' 
                        : $record->product->product_name;
                    return [
                        'product_name' => $productName,
                        'branch' => $record->branch->name ?? 'Unknown Branch',
                        'batch_number' => $record->batch_number,
                        'quantity' => floatval($record->quantity),
                        'unit' => $record->product->unit->name ?? 'Pcs',
                        'expiry_date' => $record->expiry_date->format('Y-m-d'),
                        'days_remaining' => max(0, now()->diffInDays($record->expiry_date, false))
                    ];
                })->filter()->values();

            // 5. Active Suppliers
            $activeSuppliersCount = Supplier::where('is_active', true)->count();
            $recentSuppliersCount = Supplier::where('is_active', true)->where('created_at', '>=', now()->subDays(30))->count();

            // 6. Stock Transfers in transit
            $inTransitTransfersCount = StockTransfer::where('status', 'in_transit')->count();

            // 7. Damage Records pending write-off (reported status)
            $damageRecordsCount = DamagedRecord::where('status', 'reported')->count();
            $recentDamageCount = DamagedRecord::where('status', 'reported')->where('created_at', '>=', now()->subDays(7))->count();

            // 8. Total Payments completed this month
            $totalPaymentsThisMonth = Payment::where('status', 'completed')
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount');
            
            // 9. PO vs GRN - This Week
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $posThisWeekQuery = PurchaseOrder::whereBetween('order_date', [$startOfWeek, $endOfWeek]);
            if ($subordinateIds) {
                $posThisWeekQuery->whereIn('created_by', $subordinateIds);
            }
            $posThisWeek = $posThisWeekQuery
                ->select(DB::raw('DATE(order_date) as date'), DB::raw('count(*) as count'))
                ->groupBy(DB::raw('DATE(order_date)'))
                ->get()
                ->keyBy('date');

            $grnsThisWeek = Grn::whereBetween('received_date', [$startOfWeek, $endOfWeek])
                ->select(DB::raw('DATE(received_date) as date'), DB::raw('count(*) as count'))
                ->groupBy(DB::raw('DATE(received_date)'))
                ->get()
                ->keyBy('date');

            $chartData = [];
            $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            for ($i = 0; $i < 7; $i++) {
                $currentDayDate = $startOfWeek->copy()->addDays($i)->format('Y-m-d');
                $dayName = $days[$i];
                $chartData[] = [
                    'day' => $dayName,
                    'date' => $currentDayDate,
                    'po_count' => isset($posThisWeek[$currentDayDate]) ? intval($posThisWeek[$currentDayDate]->count) : 0,
                    'grn_count' => isset($grnsThisWeek[$currentDayDate]) ? intval($grnsThisWeek[$currentDayDate]->count) : 0,
                ];
            }

            // 10. Stock by Category — sum of latest ledger balances per product grouped by category
            $stockByCategory = DB::table('products')
                ->join('main_categories', 'products.main_category_id', '=', 'main_categories.id')
                ->joinSub(
                    DB::table('stock_ledger as sl')
                        ->select('sl.product_id', DB::raw('MAX(sl.id) as max_id'))
                        ->groupBy('sl.product_id'),
                    'latest_ledger',
                    'latest_ledger.product_id',
                    '=',
                    'products.id'
                )
                ->join('stock_ledger as sl2', 'sl2.id', '=', 'latest_ledger.max_id')
                ->select('main_categories.name as category', DB::raw('ROUND(SUM(sl2.balance), 2) as total_stock'))
                ->groupBy('main_categories.id', 'main_categories.name')
                ->orderBy('total_stock', 'desc')
                ->get();

            $categoriesData = [];
            $otherStock = 0.0;
            $idx = 0;
            foreach ($stockByCategory as $item) {
                if ($idx < 4) {
                    $categoriesData[] = [
                        'category' => $item->category,
                        'count'    => floatval($item->total_stock ?? 0),
                    ];
                } else {
                    $otherStock += floatval($item->total_stock ?? 0);
                }
                $idx++;
            }
            if ($otherStock > 0 || $idx > 4) {
                $categoriesData[] = [
                    'category' => 'Other',
                    'count'    => round($otherStock, 2),
                ];
            }

            // 11. Recent Purchase Orders
            $recentPoQuery = PurchaseOrder::with(['supplier:id,supplier_name', 'branch:id,name', 'items.variant.product', 'items.productVariant.product']);
            if ($subordinateIds) {
                if ($isAdminOrReportingManager) {
                    $recentPoQuery->where(function ($q) use ($subordinateIds, $user) {
                        $q->whereIn('created_by', $subordinateIds);
                        if ($user && $user->branch_id) {
                            $q->orWhere('branch_id', $user->branch_id);
                        }
                    });
                } else {
                    $recentPoQuery->where('created_by', $user->id);
                }
            }
            $recentPurchaseOrders = $recentPoQuery
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($po) {
                    $productNames = [];
                    if ($po->items) {
                        foreach ($po->items as $item) {
                            $prodName = $item->variant->product->product_name 
                                        ?? $item->productVariant->product->product_name 
                                        ?? $item->variant->variant_name 
                                        ?? $item->productVariant->variant_name 
                                        ?? null;
                            if ($prodName) {
                                $productNames[] = $prodName;
                            }
                        }
                    }
                    $productNames = array_values(array_unique(array_filter($productNames)));
                    $productSummary = '—';
                    if (count($productNames) > 0) {
                        if (count($productNames) == 1) {
                            $productSummary = $productNames[0];
                        } else {
                            $productSummary = $productNames[0] . ' + ' . (count($productNames) - 1) . ' more';
                        }
                    }

                    return [
                        'po_number' => $po->po_number,
                        'supplier' => $po->supplier->supplier_name ?? 'Unknown Supplier',
                        'branch' => $po->branch->name ?? 'Unknown Branch',
                        'amount' => floatval($po->total_amount),
                        'product' => $productSummary,
                        'status' => $po->status
                    ];
                });

            // 12. Recent Activity Logs
            $recentActivity = ActivityLog::with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'user_name' => $log->user->name ?? 'System',
                        'description' => $log->description,
                        'time' => $log->created_at->diffForHumans()
                    ];
                });

            // "Inventory Dashboard Index" only decides whether the dashboard opens
            // at all. Each tile and panel additionally needs the permission for the
            // module it summarises, so a manager without (say) "Product Index"
            // never receives product counts — not just doesn't render them.
            $can = fn (string $permission): bool => (bool) $user?->can($permission);

            $metrics = [
                'total_products' => [
                    'value' => $totalProductsCount,
                    'change' => $recentProductsCount,
                    'label' => 'across all branches'
                ],
                'pending_pos' => [
                    'value' => $pendingPosCount,
                    'change' => $awaitingApprovalCount,
                    'label' => 'awaiting approval'
                ],
                'low_stock_items' => [
                    'value' => $lowStockCount,
                    'change' => 0, // placeholder or logic if required
                    'label' => 'below reorder level'
                ],
                'expiring_soon' => [
                    'value' => $expiringSoonCount,
                    'change' => 0, // placeholder or logic if required
                    'label' => 'within 30 days'
                ],
                'active_suppliers' => [
                    'value' => $activeSuppliersCount,
                    'change' => $recentSuppliersCount,
                    'label' => 'verified suppliers'
                ],
                'stock_transfers' => [
                    'value' => StockTransfer::count(), // total stock transfers
                    'in_transit' => $inTransitTransfersCount,
                    'label' => 'in transit this week'
                ],
                'damage_records' => [
                    'value' => $damageRecordsCount,
                    'change' => $recentDamageCount,
                    'label' => 'pending write-off'
                ],
                'total_payments' => [
                    'value' => floatval($totalPaymentsThisMonth),
                    'label' => 'completed this month'
                ],
            ];

            $metricPermissions = [
                'total_products'   => 'Product Index',
                'pending_pos'      => 'PurchaseOrder Index',
                'low_stock_items'  => 'Reorder Level Index',
                'expiring_soon'    => 'Expiry Record Index',
                'active_suppliers' => 'Supplier Index',
                'stock_transfers'  => 'StockTransfer Index',
                'damage_records'   => 'Damage Record Index',
                'total_payments'   => 'Payment Index',
            ];

            foreach ($metricPermissions as $metricKey => $permission) {
                if (! $can($permission)) {
                    unset($metrics[$metricKey]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'metrics' => $metrics,
                    // The chart plots PO and GRN side by side, so it needs both.
                    'po_vs_grn_chart' => $can('PurchaseOrder Index') && $can('Grn Index') ? $chartData : [],
                    'stock_by_category' => $can('Product Index') ? $categoriesData : [],
                    'reorder_alerts' => $can('Reorder Level Index') ? $reorderAlerts : [],
                    'expiring_soon_list' => $can('Expiry Record Index') ? $expiringSoonList : [],
                    'recent_purchase_orders' => $can('PurchaseOrder Index') ? $recentPurchaseOrders : [],
                    'recent_activity' => $can('Activity Log Index') ? $recentActivity : [],
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = InventoryDashboard::query();

            // Search
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $query->orderBy('created_at', 'desc');
            $dashboards = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Inventory dashboards retrieved successfully',
                'data' => $dashboards
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve inventory dashboards',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateInventoryDashboardRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $dashboard = InventoryDashboard::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'InventoryDashboard', "Created inventory dashboard: {$dashboard->name} ({$dashboard->code})");

            return response()->json([
                'status' => 'success',
                'message' => 'Inventory dashboard created successfully',
                'data' => $dashboard
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create inventory dashboard',
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
            $dashboard = InventoryDashboard::query()->find($id);

            if (!$dashboard) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Inventory dashboard not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Inventory dashboard retrieved successfully',
                'data' => $dashboard
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve inventory dashboard',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryDashboardRequest $request, string $id)
    {
        try {
            $dashboard = InventoryDashboard::query()->find($id);

            if (!$dashboard) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Inventory dashboard not found',
                    'data' => []
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $dashboard->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'InventoryDashboard', "Updated inventory dashboard: {$dashboard->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Inventory dashboard updated successfully',
                'data' => $dashboard
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update inventory dashboard',
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
            $dashboard = InventoryDashboard::query()->find($id);
            if (!$dashboard) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Inventory dashboard not found',
                    'data' => [],
                ], 404);
            }

            $title = $dashboard->name;
            if (!InventoryDashboard::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to soft delete inventory dashboard',
                ], 500);
            }

            $this->logActivity('SOFT_DELETE', 'InventoryDashboard', "Soft deleted inventory dashboard: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Inventory dashboard soft deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to soft delete inventory dashboard',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of the dashboard.
     */
    public function toggleStatus(string $id)
    {
        try {
            $dashboard = InventoryDashboard::query()->find($id);

            if (!$dashboard) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Inventory dashboard not found'
                ], 404);
            }

            $dashboard->is_active = !$dashboard->is_active;
            $dashboard->save();

            $this->logActivity('TOGGLE_STATUS', 'InventoryDashboard', "Toggled inventory dashboard status: {$dashboard->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Inventory dashboard status updated successfully',
                'data' => [
                    'id' => $dashboard->id,
                    'is_active' => $dashboard->is_active
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle inventory dashboard status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Activate the dashboard.
     */
    public function activate(string $id)
    {
        try {
            $dashboard = InventoryDashboard::query()->find($id);

            if (!$dashboard) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Inventory dashboard not found',
                ], 404);
            }

            if ($dashboard->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Inventory dashboard is already active',
                    'data' => $dashboard
                ]);
            }

            $dashboard->update(['is_active' => true]);

            $this->logActivity('ACTIVATE', 'InventoryDashboard', "Activated inventory dashboard: {$dashboard->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Inventory dashboard activated successfully',
                'data' => $dashboard
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate inventory dashboard',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Deactivate the dashboard.
     */
    public function deactivate(string $id)
    {
        try {
            $dashboard = InventoryDashboard::query()->find($id);

            if (!$dashboard) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Inventory dashboard not found',
                ], 404);
            }

            if (!$dashboard->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Inventory dashboard is already inactive',
                    'data' => $dashboard
                ]);
            }

            $dashboard->update(['is_active' => false]);

            $this->logActivity('DEACTIVATE', 'InventoryDashboard', "Deactivated inventory dashboard: {$dashboard->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Inventory dashboard deactivated successfully',
                'data' => $dashboard
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate inventory dashboard',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
