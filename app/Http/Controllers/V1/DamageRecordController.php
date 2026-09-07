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
use App\Traits\TogglesActiveStatus;

class DamageRecordController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    use TogglesActiveStatus;

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
            $status = $request->input('status', 'reported');

            // Creating directly as Approved skips the normal reported ->
            // approved transition, so it needs the same approval permission
            // that transition would require via update().
            if ($status === 'approved' && !Auth::user()?->can('Damage Record Approve')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to create a record as Approved.',
                ], 403);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $data['status'] = $status;
            $data['approved_by'] = $status === 'approved' ? Auth::id() : null;
            $record = DamagedRecord::create($data);

            // Stock only moves once a record is Approved — whether that
            // happens later via update() or, here, immediately on creation.
            if ($status === 'approved') {
                $this->applyStockDeduction($record);
            }

            $recipientService = app(NotificationRecipientService::class);
            $statusLabel = ['approved' => 'approved', 'cancelled' => 'cancelled'][$status] ?? 'reported';
            $notification = new \App\Notifications\InventoryAlertNotification([
                'title' => 'Damage Record ' . ucfirst($statusLabel),
                'message' => 'Damage record ' . $record->damage_number . ' has been ' . $statusLabel . '.',
                'type' => 'damage_' . $statusLabel,
                'module' => 'damage-records',
                'priority' => 'high',
                'reference_id' => $record->id,
                'reference_type' => DamagedRecord::class,
                'url' => '/damage-records/' . $record->id,
            ]);

            foreach ($recipientService->actorAndReportingManager(Auth::user()) as $target) {
                $target->notify($notification);
            }

            DB::commit();

            $this->logActivity('CREATE', 'DamageRecord', "Created damaged record: {$record->damage_number}");

            return response()->json([
                'status' => 'success',
                'message' => 'Damage record created successfully',
                'data' => $record->load(['product', 'productVariant', 'branch', 'reportedBy', 'approvedBy']),
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

            $movingToApproved = isset($validated['status'])
                && $validated['status'] === 'approved'
                && $record->status !== 'approved';

            // Leaving "approved" (e.g. approved -> cancelled, or an un-approve
            // back to reported) reverses the stock deduction, so it needs the
            // same approval permission as granting it.
            $movingFromApproved = isset($validated['status'])
                && $record->status === 'approved'
                && $validated['status'] !== 'approved';

            $attemptsApproval = $approvedByChanging || $movingToApproved || $movingFromApproved;

            if ($attemptsApproval && !Auth::user()?->can('Damage Record Approve')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to set an approver or change this record\'s Approved status.',
                ], 403);
            }

            DB::beginTransaction();

            $oldStatus = $record->status;
            $record->update($validated);

            if ($movingToApproved) {
                $this->applyStockDeduction($record);

                $recipientService = app(NotificationRecipientService::class);
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Damage Record Approved',
                    'message' => 'Damage record ' . $record->damage_number . ' has been approved and stock has been adjusted.',
                    'type' => 'damage_approved',
                    'module' => 'damage-records',
                    'priority' => 'high',
                    'reference_id' => $record->id,
                    'reference_type' => DamagedRecord::class,
                    'url' => '/damage-records/' . $record->id,
                ]);

                foreach ($recipientService->actorAndReportingManager(Auth::user()) as $target) {
                    $target->notify($notification);
                }
            } elseif ($movingFromApproved) {
                $this->reverseStockDeduction($record);
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

            $this->reverseStockDeduction($record);

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
        return $this->setActiveState(DamagedRecord::class, $id, true, [
            'not_found' => 'Damage record not found',
            'already' => 'Damage record is already active',
            'success' => 'Damage record activated successfully',
            'failed' => 'Failed to activate damage record',
        ], [
            'log' => function ($damagedrecord) {
                $this->logActivity('ACTIVATE', 'DamageRecord', "Activated damage record: {$damagedrecord->damage_number}");
            },
        ]);
    }

    /**
     * Deactivate the damage record.
     */
    public function deactivate(string $id)
    {
        return $this->setActiveState(DamagedRecord::class, $id, false, [
            'not_found' => 'Damage record not found',
            'already' => 'Damage record is already inactive',
            'success' => 'Damage record deactivated successfully',
            'failed' => 'Failed to deactivate damage record',
        ], [
            'log' => function ($damagedrecord) {
                $this->logActivity('DEACTIVATE', 'DamageRecord', "Deactivated damage record: {$damagedrecord->damage_number}");
            },
        ]);
    }

    /**
     * Deduct the damaged quantity from stock once a record is approved.
     */
    private function applyStockDeduction(DamagedRecord $record): void
    {
        $record->refresh();
        $qty = floatval($record->quantity ?? 0);
        if ($qty <= 0) return;

        // Idempotency guard against a record being re-approved (e.g. cancelled
        // then approved again) while an un-reversed ledger entry still exists.
        $alreadyPosted = StockLedger::where('reference_type', DamagedRecord::class)
            ->where('reference_id', $record->id)
            ->where('quantity_out', '>', 0)
            ->exists();
        if ($alreadyPosted) return;

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

        $this->logActivity('CREATE', 'StockLedger', "Damage approval ledger for damaged_record: {$record->id}");
    }

    /**
     * Reverse a previously-applied stock deduction (record cancelled/un-approved/deleted).
     * No-op if the deduction was never posted.
     */
    private function reverseStockDeduction(DamagedRecord $record): void
    {
        $hasLedgerOut = StockLedger::where('reference_type', DamagedRecord::class)
            ->where('reference_id', $record->id)
            ->where('quantity_out', '>', 0)
            ->exists();

        if (! $hasLedgerOut) return;

        $qty = floatval($record->quantity ?? 0);
        if ($qty <= 0) return;

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

        $this->logActivity('CREATE', 'StockLedger', "Damage reversal ledger for damaged_record: {$record->id}");
    }
}
