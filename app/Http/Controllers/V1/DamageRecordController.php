<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDamageRecordRequest;
use App\Http\Requests\UpdateDamageRecordRequest;
use App\Models\DamagedRecord;
use App\Models\StockLedger;
use App\Services\StockLedgerService;
use App\Services\NotificationRecipientService;
use App\Exceptions\InsufficientStockException;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DamageRecordController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Damage Record Index', only: ['index', 'show']),
            new Middleware('permission:Damage Record Create', only: ['store']),
            new Middleware('permission:Damage Record Update', only: ['update', 'activate', 'deactivate']),
            new Middleware('permission:Damage Record Delete', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = DamagedRecord::with(['product', 'productVariant', 'branch', 'reportedBy', 'approvedBy']);

             if ($request->has('search') ) {
                $query->search($request->search);
            }

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $records = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Damage records retrieved successfully',
                'data' => $records,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch damage records',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['status' => 'error', 'message' => 'Not supported'], 405);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateDamageRecordRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            // New damage records always start as "reported" with no approver —
            // approving/writing off only happens later via update, by permitted users.
            $data['status'] = 'reported';
            $data['approved_by'] = null;
            $record = DamagedRecord::create($data);

            $recipientService = app(NotificationRecipientService::class);
            $notification = new \App\Notifications\InventoryAlertNotification([
                'title' => 'Damage Reported',
                'message' => 'Damage record ' . $record->damage_number . ' has been reported.',
                'type' => 'damage_reported',
                'module' => 'damage-records',
                'priority' => 'high',
                'reference_id' => $record->id,
                'reference_type' => DamagedRecord::class,
                'url' => '/damage-records/' . $record->id,
            ]);

            $targets = $recipientService->mergeCollections(
                $recipientService->inventoryAdmins(),
                $recipientService->usersByRoles(['Inventory Approver', 'INVENTORY APPROVER'])
            );

            foreach ($targets as $user) {
                $user->notify($notification);
            }

            $reportingManager = $recipientService->reportingManagerOf(Auth::user(), ['Damage Record Update']);
            if ($reportingManager && !$targets->contains('id', $reportingManager->id)) {
                $reportingManager->notify($notification);
            }

            $admins = $recipientService->adminsAndSuperAdmins($record->branch_id);
            foreach ($admins as $admin) {
                if ($admin->email) {
                    \Illuminate\Support\Facades\Mail::to($admin->email)->send(new \App\Mail\DamagedRecordMail($record));
                }
            }

            DB::commit();

            $this->logActivity('CREATE', 'DamageRecord', "Created damaged record: {$record->damage_number}");

            return response()->json([
                'status' => 'success',
                'message' => 'Damage record created successfully',
                'data' => $record->load(['product', 'productVariant', 'branch', 'reportedBy', 'approvedBy']),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create damage record',
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
            $record = DamagedRecord::with(['product', 'productVariant', 'branch', 'reportedBy', 'approvedBy'])->find($id);

            if (! $record) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Damage record not found',
                    'data'    => [],
                ], 404);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Damage record retrieved successfully',
                'data'    => $record,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve damage record',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return response()->json(['status' => 'error', 'message' => 'Not supported'], 405);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDamageRecordRequest $request, string $id)
    {
        try {
            $record = DamagedRecord::query()->find($id);

            if (! $record) {
                return response()->json(['status' => 'error', 'message' => 'Damage record not found', 'data' => []], 404);
            }

            $validated = $request->validated();

            $approvedByChanging = $request->has('approved_by')
                && (string) $request->input('approved_by') !== (string) $record->approved_by;

            $statusMovingToApproval = isset($validated['status'])
                && $validated['status'] !== $record->status
                && in_array($validated['status'], ['approved', 'written_off']);

            $attemptsApproval = $approvedByChanging || $statusMovingToApproval;

            if ($attemptsApproval && !Auth::user()?->can('Damage Record Approve')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to set an approver or move this record to Approved/Written Off.',
                ], 403);
            }

            DB::beginTransaction();

            $oldStatus = $record->status;
            $record->update($validated);

            if ($oldStatus !== 'written_off' && $record->status === 'written_off') {
                $this->applyWriteOff($record);

                $recipientService = app(NotificationRecipientService::class);
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Damage Write-Off Approved',
                    'message' => 'Damage record ' . $record->damage_number . ' has been written off.',
                    'type' => 'damage_write_off',
                    'module' => 'damage-records',
                    'priority' => 'high',
                    'reference_id' => $record->id,
                    'reference_type' => DamagedRecord::class,
                    'url' => '/damage-records/' . $record->id,
                ]);

                $targets = $recipientService->mergeCollections(
                    $recipientService->inventoryAdmins(),
                    $recipientService->usersByRoles(['Inventory Approver', 'INVENTORY APPROVER'])
                );

                foreach ($targets as $user) {
                    $user->notify($notification);
                }

                $admins = $recipientService->adminsAndSuperAdmins($record->branch_id);
                foreach ($admins as $admin) {
                    if ($admin->email) {
                        \Illuminate\Support\Facades\Mail::to($admin->email)->send(new \App\Mail\DamagedRecordMail($record));
                    }
                }
            }

            DB::commit();

            $this->logActivity('UPDATE', 'DamageRecord', "Updated damaged record: {$record->damage_number}");

            return response()->json(
                [
                'status' => 'success', 
                'message' => 'Damage record updated successfully', 
                'data' => $record->load(['product', 
                'productVariant', 
                'branch', 
                'reportedBy', 
                'approvedBy'])
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
                'message' => 'Failed to update damage record',
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
            $record = DamagedRecord::query()->find($id);

            if (! $record) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Damage record not found',
                    'data'    => [],
                ], 404);
            }

            DB::beginTransaction();

            $hasLedgerOut = StockLedger::where('reference_type', DamagedRecord::class)
                ->where('reference_id', $record->id)
                ->exists();

            if ($hasLedgerOut) {
                $qty = floatval($record->quantity ?? 0);
                if ($qty > 0) {
                    StockLedgerService::recordIn(
                        productId:       $record->product_id,
                        variantId:       $record->product_variant_id,
                        branchId:        $record->branch_id,
                        quantity:        $qty,
                        unitId:          null,
                        referenceType:   DamagedRecord::class . '_Reversal',
                        referenceId:     $record->id,
                        transactionDate: now()->toDateString(),
                        createdBy:       Auth::id(),
                    );
                }
            }

            if (! DamagedRecord::destroy($id)) {
                DB::rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Failed to delete damage record',
                ], 500);
            }

            DB::commit();

            $this->logActivity('DELETE', 'DamageRecord', "Deleted damaged record: {$record->damage_number}");

            return response()->json([
                'status'  => 'success',
                'message' => 'Damage record deleted successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete damage record',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function activate(string $id)
    {
        try {
            $damagedrecord = DamagedRecord::query()->find($id);

            if (!$damagedrecord) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Damage record not found',
                ], 404);
            }

            if ($damagedrecord->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Damage record is already active',
                    'data' => $damagedrecord
                ]);
            }

            $damagedrecord->update(['is_active' => true]);

            $this->logActivity('ACTIVATE', 'DamageRecord', "Activated damage record: {$damagedrecord->damage_number}");

            return response()->json([
                'status' => 'success',
                'message' => 'Damage record activated successfully',
                'data' => $damagedrecord
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate damage record',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Deactivate the damage record.
     */
    public function deactivate(string $id)
    {
        try {
            $damagedrecord = DamagedRecord::query()->find($id);

            if (!$damagedrecord) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Damage record not found',
                ], 404);
            }

            if (!$damagedrecord->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Damage record is already inactive',
                    'data' => $damagedrecord
                ]);
            }

            $damagedrecord->update(['is_active' => false]);

            $this->logActivity('DEACTIVATE', 'DamageRecord', "Deactivated damage record: {$damagedrecord->damage_number}");

            return response()->json([
                'status' => 'success',
                'message' => 'Damage record deactivated successfully',
                'data' => $damagedrecord
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate damage record',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    private function applyWriteOff(DamagedRecord $record): void
    {
        $record->refresh();
        $qty = floatval($record->quantity ?? 0);
        if ($qty <= 0) return;

        $createdBy = $record->approved_by ?? $record->reported_by ?? Auth::guard('api')->id();

        StockLedgerService::assertSufficientStock($record->product_id, $record->branch_id, $qty);

        StockLedgerService::recordOut(
            productId:       $record->product_id,
            variantId:       $record->product_variant_id,
            branchId:        $record->branch_id,
            quantity:        $qty,
            unitId:          null,
            referenceType:   DamagedRecord::class,
            referenceId:     $record->id,
            transactionDate: $record->damage_date,
            createdBy:       $createdBy,
        );

        $this->logActivity('CREATE', 'StockLedger', "Damage write-off ledger for damaged_record: {$record->id}");
    }
}
