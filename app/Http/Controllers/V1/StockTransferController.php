<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStockTransferRequest;
use App\Http\Requests\UpdateStockTransferRequest;
use App\Models\StockTransfer;
use App\Models\StockLedger;
use App\Services\StockLedgerService;
use App\Services\AlertService;
use App\Services\NotificationRecipientService;
use App\Exceptions\InsufficientStockException;
use App\Traits\ActivityLogTrait;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:StockTransfer Index',  only: ['index', 'show']),
            new Middleware('permission:StockTransfer Create', only: ['store']),
            new Middleware('permission:StockTransfer Update', only: ['update']),
            new Middleware('permission:StockTransfer Delete', only: ['destroy']),
        ];
    }

    private const RELATIONS = ['fromBranch', 'toBranch', 'fromEmployee', 'toEmployee', 'requester', 'approver'];

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query   = StockTransfer::query()->with(self::RELATIONS)->withCount('items');
            $user = Auth::user();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->filled('transfer_type')) {
                $query->where('transfer_type', $request->transfer_type);
            }

            if ($request->filled('from_branch_id')) {
                $query->where('from_branch_id', $request->from_branch_id);
            }

            if ($request->filled('to_branch_id')) {
                $query->where('to_branch_id', $request->to_branch_id);
            }

            if ($request->filled('from_employee_id')) {
                $query->where('from_employee_id', $request->from_employee_id);
            }

            if ($request->filled('to_employee_id')) {
                $query->where('to_employee_id', $request->to_employee_id);
            }

            if ($request->filled('requested_by')) {
                $query->where('requested_by', $request->requested_by);
            }

            if ($request->filled('approved_by')) {
                $query->where('approved_by', $request->approved_by);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $stockTransfers = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status'  => 'success',
                'message' => 'Stock transfers fetched successfully',
                'data'    => $stockTransfers,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch stock transfers',
                'error'   => $th->getMessage(),
            ], 500);
        }
    }

    public function store(CreateStockTransferRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            if (!isset($data['requested_by'])) {
                $data['requested_by'] = Auth::id();
            }
            if (!isset($data['created_by'])) {
                $data['created_by'] = Auth::id();
            }

            $stockTransfer = StockTransfer::create($data);

            // If created directly with a sent status, post stock OUT immediately
            $sentStatuses = ['approved', 'in_transit', 'received'];
            if (in_array($stockTransfer->status, $sentStatuses)) {
                $this->applyStockTransferLedgerUpdates($stockTransfer, 'draft');
            }

            DB::commit();

            $stockTransfer->load(self::RELATIONS);

            $user = auth('api')->user();
            if ($user && $stockTransfer->status === 'draft' && !$user->can('StockTransfer Update')) {
                AlertService::notifyAdmins(
                    'Stock Transfer Requires Approval',
                    "A new draft stock transfer ({$stockTransfer->transfer_number}) was created by {$user->name} and requires approval.",
                    'system_alert'
                );
            }

            // Creation Notification to Reporting Managers
            try {
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Stock Transfer Created',
                    'message' => 'New Stock Transfer ' . ($stockTransfer->transfer_number ?? $stockTransfer->id) . ' has been created and is awaiting approval.',
                    'type' => 'stock_transfer_created',
                    'module' => 'stock-transfers',
                    'priority' => 'medium',
                    'reference_id' => $stockTransfer->id,
                    'reference_type' => StockTransfer::class,
                    'url' => '/stock-transfers/' . $stockTransfer->id,
                ]);

                $recipientService = app(NotificationRecipientService::class);
                $targets = clone $recipientService->usersByBranchPermissions($stockTransfer->fromBranch, ['StockTransfer Update', 'StockTransfer Index']);
                
                if ($targets->isEmpty()) {
                    $targets = $recipientService->usersByPermissions(['StockTransfer Update', 'StockTransfer Index']);
                }

                foreach ($targets as $targetUser) {
                    $targetUser->notify($notification);
                }

                $reportingManager = $recipientService->reportingManagerOf($user, ['StockTransfer Update']);
                if ($reportingManager && !$targets->contains('id', $reportingManager->id)) {
                    $reportingManager->notify($notification);
                }
            } catch (\Throwable $notifyErr) {
                Log::error('Failed to send Stock Transfer creation notification: ' . $notifyErr->getMessage());
            }

            $this->logActivity('CREATE', 'StockTransfer', "Created stock transfer: {$stockTransfer->transfer_number} (type: {$stockTransfer->transfer_type})");

            return response()->json([
                'status'  => 'success',
                'message' => 'Stock transfer created successfully',
                'data'    => $stockTransfer,
            ], 201);
        } catch (InsufficientStockException $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create stock transfer',
                'error'   => $th->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $stockTransfer = StockTransfer::with(array_merge(self::RELATIONS, ['items']))->find($id);

            if (! $stockTransfer) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Stock transfer not found',
                    'data'    => [],
                ], 404);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Stock transfer retrieved successfully',
                'data'    => $stockTransfer,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve stock transfer',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function update(UpdateStockTransferRequest $request, string $id)
    {
        try {
            $stockTransfer = StockTransfer::query()->find($id);

            if (! $stockTransfer) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Stock transfer not found',
                    'data'    => [],
                ], 404);
            }

            $previousStatus = $stockTransfer->status;

            DB::beginTransaction();

            $data = $request->validated();
            unset($data['created_by']);
            unset($data['requested_by']);

            if (isset($data['status']) && in_array($data['status'], ['approved', 'in_transit', 'received'])) {
                $data['approved_by'] = Auth::id();
            }

            $stockTransfer->update($data);

            $this->applyStockTransferLedgerUpdates($stockTransfer, $previousStatus);

            if (in_array($stockTransfer->status, ['approved', 'in_transit', 'received'], true) && $previousStatus !== $stockTransfer->status) {
                $recipientService = app(NotificationRecipientService::class);
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Stock Transfer Update',
                    'message' => 'Transfer ' . $stockTransfer->transfer_number . ' ' . $stockTransfer->status . '.',
                    'type' => 'stock_transfer_' . $stockTransfer->status,
                    'module' => 'stock-transfers',
                    'priority' => 'high',
                    'reference_id' => $stockTransfer->id,
                    'reference_type' => StockTransfer::class,
                    'url' => '/stock-transfers/' . $stockTransfer->id,
                ]);

                $targets = $recipientService->mergeCollections(
                    $recipientService->branchManagers($stockTransfer->fromBranch),
                    $recipientService->branchManagers($stockTransfer->toBranch)
                );

                foreach ($targets as $user) {
                    $user->notify($notification);
                }

                $admins = $recipientService->adminsAndSuperAdmins($stockTransfer->from_branch_id);
                foreach ($admins as $admin) {
                    if ($admin->email) {
                        \Illuminate\Support\Facades\Mail::to($admin->email)->send(new \App\Mail\StockTransferMail($stockTransfer));
                    }
                }
            }

            DB::commit();

            $this->logActivity('UPDATE', 'StockTransfer', "Updated stock transfer: {$stockTransfer->transfer_number} → status: {$stockTransfer->status}");

            // Instant stale-transfer alert: notify admins if transfer has been in transit > 5 days
            if ($stockTransfer->status === 'in_transit' && $previousStatus !== 'in_transit') {
                try {
                    $daysInTransit = Carbon::parse($stockTransfer->transfer_date)->diffInDays(Carbon::today());
                    if ($daysInTransit >= 5) {
                        AlertService::staleTransferAlert(
                            transferNumber: $stockTransfer->transfer_number,
                            fromBranch:     $stockTransfer->fromBranch?->name ?? 'N/A',
                            toBranch:       $stockTransfer->toBranch?->name   ?? 'N/A',
                            daysInTransit:  $daysInTransit,
                        );
                    }
                } catch (\Throwable) {
                    // Non-fatal
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Stock transfer updated successfully',
                'data'    => $stockTransfer->load(self::RELATIONS),
            ]);
        } catch (InsufficientStockException $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update stock transfer',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $stockTransfer = StockTransfer::query()->find($id);

            if (! $stockTransfer) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Stock transfer not found',
                    'data'    => [],
                ], 404);
            }

            if (! StockTransfer::destroy($id)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Failed to delete stock transfer',
                ], 500);
            }

            $this->logActivity('DELETE', 'StockTransfer', "Deleted stock transfer: {$stockTransfer->transfer_number}");

            return response()->json([
                'status'  => 'success',
                'message' => 'Stock transfer deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete stock transfer',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


    private function applyStockTransferLedgerUpdates(StockTransfer $stockTransfer, string $previousStatus): void
    {
        $stockTransfer->load('items');
        $currentStatus = $stockTransfer->status;

        $sentStatuses = ['approved', 'in_transit', 'received'];
        $wasSent = in_array($previousStatus, $sentStatuses);
        $isSent  = in_array($currentStatus,  $sentStatuses);

        $createdBy = $stockTransfer->approved_by ?? $stockTransfer->requested_by;

        // Resolve which branches are involved based on transfer_type
        $fromBranchId = in_array($stockTransfer->transfer_type, ['branch_to_branch', 'branch_to_employee'])
            ? $stockTransfer->from_branch_id
            : null;

        $toBranchId = in_array($stockTransfer->transfer_type, ['branch_to_branch', 'employee_to_branch'])
            ? $stockTransfer->to_branch_id
            : null;

        // OUT: deduct from source branch when first entering an in-transit state
        if (! $wasSent && $isSent && $fromBranchId) {
            // Idempotency: only post OUT if not already posted
            $alreadyPostedOut = StockLedger::where('reference_type', StockTransfer::class)
                ->where('reference_id', $stockTransfer->id)
                ->where('quantity_out', '>', 0)
                ->exists();

            if (!$alreadyPostedOut) {
                foreach ($stockTransfer->items as $item) {
                    $qty = floatval($item->quantity_sent ?? $item->quantity_requested ?? 0);
                    if ($qty <= 0) continue;

                    // Source branch only — the destination branch is receiving
                    // stock, never validated against its own balance.
                    StockLedgerService::assertSufficientStock($item->product_id, $fromBranchId, $qty);

                    StockLedgerService::recordOut(
                        productId:       $item->product_id,
                        variantId:       $item->product_variant_id,
                        branchId:        $fromBranchId,
                        quantity:        $qty,
                        unitId:          $item->unit_id,
                        referenceType:   StockTransfer::class,
                        referenceId:     $stockTransfer->id,
                        transactionDate: $stockTransfer->transfer_date,
                        createdBy:       $createdBy,
                    );

                    $this->logActivity('CREATE', 'StockLedger', "Transfer OUT: Branch #{$fromBranchId}, Item #{$item->id}");
                }
            }
        }

        // CANCEL: reverse stock OUT when a sent transfer is cancelled
        if ($wasSent && $currentStatus === 'cancelled' && $fromBranchId) {
            $hasOutEntries = StockLedger::where('reference_type', StockTransfer::class)
                ->where('reference_id', $stockTransfer->id)
                ->where('quantity_out', '>', 0)
                ->exists();

            if ($hasOutEntries) {
                foreach ($stockTransfer->items as $item) {
                    $qty = floatval($item->quantity_sent ?? $item->quantity_requested ?? 0);
                    if ($qty <= 0) continue;

                    StockLedgerService::recordIn(
                        productId:       $item->product_id,
                        variantId:       $item->product_variant_id,
                        branchId:        $fromBranchId,
                        quantity:        $qty,
                        unitId:          $item->unit_id,
                        referenceType:   StockTransfer::class . '_Reversal',
                        referenceId:     $stockTransfer->id,
                        transactionDate: now()->toDateString(),
                        createdBy:       $createdBy,
                    );

                    $this->logActivity('CREATE', 'StockLedger', "Transfer CANCEL reversal IN: Branch #{$fromBranchId}, Item #{$item->id}");
                }
            }
        }

        // IN: add to destination branch when transfer is received
        if ($previousStatus !== 'received' && $currentStatus === 'received' && $toBranchId) {
            // Idempotency: only post IN if not already posted for received
            $alreadyPostedIn = StockLedger::where('reference_type', StockTransfer::class)
                ->where('reference_id', $stockTransfer->id)
                ->where('quantity_in', '>', 0)
                ->where('branch_id', $toBranchId)
                ->exists();

            if (!$alreadyPostedIn) {
                foreach ($stockTransfer->items as $item) {
                    $qty = floatval($item->quantity_received ?? $item->quantity_sent ?? $item->quantity_requested ?? 0);
                    if ($qty <= 0) continue;

                    StockLedgerService::recordIn(
                        productId:       $item->product_id,
                        variantId:       $item->product_variant_id,
                        branchId:        $toBranchId,
                        quantity:        $qty,
                        unitId:          $item->unit_id,
                        referenceType:   StockTransfer::class,
                        referenceId:     $stockTransfer->id,
                        transactionDate: $stockTransfer->transfer_date,
                        createdBy:       $createdBy,
                    );

                    $this->logActivity('CREATE', 'StockLedger', "Transfer IN: Branch #{$toBranchId}, Item #{$item->id}");
                }
            }
        }
    }
}
