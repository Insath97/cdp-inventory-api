<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStockTakeRequest;
use App\Http\Requests\UpdateStockTakeRequest;
use App\Models\StockLedger;
use App\Models\StockTake;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockTakeController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:StockTake Index', only: ['index', 'show']),
            new Middleware('permission:StockTake Create', only: ['store']),
            new Middleware('permission:StockTake Update', only: ['update']),
            new Middleware('permission:StockTake Delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = StockTake::query()->withCount('items')->with(['items.product', 'items.productVariant', 'items.unit', 'branch', 'creator', 'approver']);

            $user = Auth::user();

            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->filled('approved_by')) {
                $query->where('approved_by', $request->approved_by);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $stockTakes = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock takes fetched successfully',
                'data' => $stockTakes,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stock takes',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function store(CreateStockTakeRequest $request)
    {
        try {
            $validatedData = $request->validated();

            if (isset($validatedData['status']) && $validatedData['status'] === 'approved') {
                $user = auth()->user();
                if (!$user || !$user->can('StockTake Update')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You do not have permission to approve stock takes.',
                    ], 403);
                }
            }

            if (empty($validatedData['created_by'])) {
                $validatedData['created_by'] = \Illuminate\Support\Facades\Auth::id();
            }

            if (empty($validatedData['branch_id'])) {
                $validatedData['branch_id'] = \Illuminate\Support\Facades\Auth::user()?->branch_id;
            }

            if (empty($validatedData['take_number'])) {
                $maxId = (StockTake::max('id') ?? 0) + 1;
                $code = 'STK-' . date('Y') . '-' . str_pad($maxId, 4, '0', STR_PAD_LEFT);
                while (StockTake::where('take_number', $code)->exists()) {
                    $maxId++;
                    $code = 'STK-' . date('Y') . '-' . str_pad($maxId, 4, '0', STR_PAD_LEFT);
                }
                $validatedData['take_number'] = $code;
            } else {
                if (StockTake::where('take_number', $validatedData['take_number'])->exists()) {
                    $validatedData['take_number'] = $validatedData['take_number'] . '-' . uniqid();
                }
            }

            DB::beginTransaction();

            $stockTake = StockTake::create($validatedData);

            DB::commit();

            try {
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Stock Take Created',
                    'message' => 'New Stock Take #' . $stockTake->id . ' has been created and is awaiting approval.',
                    'type' => 'stock_take_created',
                    'module' => 'stock-takes',
                    'priority' => 'medium',
                    'reference_id' => $stockTake->id,
                    'reference_type' => StockTake::class,
                    'url' => '/stock-takes/' . $stockTake->id,
                ]);

                $recipientService = app(\App\Services\NotificationRecipientService::class);

                foreach ($recipientService->actorAndReportingManager(\Illuminate\Support\Facades\Auth::user()) as $target) {
                    $target->notify($notification);
                }
            } catch (\Throwable $notifyErr) {
                \Illuminate\Support\Facades\Log::error('Failed to send Stock Take creation notification: ' . $notifyErr->getMessage());
            }

            $stockTake->load(['branch', 'creator', 'approver']);

            $this->logActivity('CREATE', 'StockTake', "Created stock take: {$stockTake->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock take created successfully',
                'data' => $stockTake,
            ], 201);
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            \Illuminate\Support\Facades\Log::error('Failed to create stock take: ' . $th->getMessage() . "\n" . $th->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create stock take: ' . $th->getMessage(),
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $stockTake = StockTake::with(['branch', 'creator', 'approver', 'items.product', 'items.productVariant', 'items.unit'])->find($id);

            if (! $stockTake) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock take not found',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Stock take retrieved successfully',
                'data' => $stockTake,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock take',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function update(UpdateStockTakeRequest $request, string $id)
    {
        try {
            $stockTake = StockTake::query()->find($id);

            if (! $stockTake) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock take not found',
                    'data' => [],
                ], 404);
            }

            $validatedData = $request->validated();
            $previousStatus = $stockTake->status;

            // Strict Enforcement: Check if user has permission to approve
            if (isset($validatedData['status']) && $validatedData['status'] === 'approved' && $previousStatus !== 'approved') {
                $user = auth()->user();
                if (!$user || !$user->can('StockTake Update')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You do not have permission to approve stock takes.',
                    ], 403);
                }
            }

            unset($validatedData['created_by']);
            if (isset($validatedData['status']) && in_array($validatedData['status'], ['approved', 'completed'])) {
                if (empty($validatedData['approved_by'])) {
                    $validatedData['approved_by'] = \Illuminate\Support\Facades\Auth::id();
                }
            }

            DB::beginTransaction();

            $stockTake->update($validatedData);

            $isNewlyApproved = $previousStatus !== $stockTake->status && in_array($stockTake->status, ['approved', 'completed']);

            if ($previousStatus !== 'approved' && $stockTake->status === 'approved') {
                $this->applyStockTakeApproval($stockTake);
            }

            DB::commit();

            if ($isNewlyApproved) {
                try {
                    $creator = \App\Models\User::find($stockTake->created_by);
                    $recipientService = app(\App\Services\NotificationRecipientService::class);
                    $targets = $recipientService->actorAndReportingManager($creator);

                    $approverName = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Admin';
                    $statusTitle = ucfirst($stockTake->status);

                    $notification = new \App\Notifications\InventoryAlertNotification([
                        'title'          => "Stock Take {$statusTitle}",
                        'message'        => "Stock Take (#{$stockTake->take_number}) has been {$stockTake->status} by {$approverName}.",
                        'type'           => 'stock_take_approved',
                        'module'         => 'stock-takes',
                        'priority'       => 'high',
                        'reference_id'   => $stockTake->id,
                        'reference_type' => StockTake::class,
                        'url'            => '/stock-takes',
                    ]);

                    foreach ($targets as $targetUser) {
                        try {
                            $targetUser->notify($notification);
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to notify user: ' . $e->getMessage());
                        }
                    }
                } catch (\Throwable $notifyError) {
                    \Illuminate\Support\Facades\Log::error('Failed to send Stock Take notification: ' . $notifyError->getMessage());
                }
            }

            $stockTake->load(['branch', 'creator', 'approver']);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock take updated successfully',
                'data' => $stockTake,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock take',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    private function applyStockTakeApproval(StockTake $stockTake): void
    {
        $stockTake->load('items');

        $createdBy = $stockTake->approved_by ?? $stockTake->created_by;

        foreach ($stockTake->items as $item) {
            if ($item->variance == 0) {
                continue;
            }

            try {
                if ($item->product_variant_id) {
                    \App\Models\ProductVariant::where('id', $item->product_variant_id)->lockForUpdate()->first();
                } else {
                    \App\Models\Product::where('id', $item->product_id)->lockForUpdate()->first();
                }

                // Product+branch scoped, matching StockLedgerService — see the
                // note there on why variant is deliberately not part of the key.
                $lastBalance = StockLedger::query()
                    ->where('product_id', $item->product_id)
                    ->where('branch_id', $stockTake->branch_id)
                    ->orderBy('transaction_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->value('balance') ?? 0;

                $physicalQty = floatval($item->physical_quantity ?? 0);
                $variance = $physicalQty - floatval($lastBalance);
                $quantityIn = $variance > 0 ? $variance : 0;
                $quantityOut = $variance < 0 ? abs($variance) : 0;

                if ($variance == 0) {
                    continue;
                }

                $ledger = StockLedger::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'branch_id' => $stockTake->branch_id,
                    'reference_type' => StockTake::class,
                    'reference_id' => $stockTake->id,
                    'transaction_date' => $stockTake->take_date,
                    'quantity_in' => $quantityIn,
                    'quantity_out' => $quantityOut,
                    'balance' => $physicalQty,
                    'unit_id' => $item->unit_id,
                    'created_by' => $createdBy,
                ]);

                $this->logActivity('CREATE', 'StockLedger', "Stock take adjustment: {$stockTake->id} item: {$item->id} ledger: {$ledger->id}");
            } catch (\Throwable $e) {
                $this->logActivity('ERROR', 'StockLedger', "Failed to create stock ledger (stock_take: {$stockTake->id}, item: {$item->id}): {$e->getMessage()}");
                continue;
            }
        }
    }

    public function destroy(string $id)
    {
        try {
            $stockTake = StockTake::query()->find($id);

            if (! $stockTake) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock take not found',
                    'data' => [],
                ], 404);
            }

            if (! StockTake::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete stock take',
                ], 500);
            }

            $this->logActivity('DELETE', 'StockTake', "Deleted stock take: {$stockTake->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock take deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete stock take',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
