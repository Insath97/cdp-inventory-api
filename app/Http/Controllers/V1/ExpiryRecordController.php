<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateExpiryRecordRequest;
use App\Http\Requests\UpdateExpiryRecordRequest;
use App\Models\ExpiryRecord;
use App\Models\StockLedger;
use App\Services\StockLedgerService;
use App\Services\AlertService;
use App\Services\NotificationRecipientService;
use App\Exceptions\InsufficientStockException;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\TogglesActiveStatus;

class ExpiryRecordController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    use TogglesActiveStatus;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Expiry Record Index', only: ['index', 'show']),
            new Middleware('permission:Expiry Record Create', only: ['store']),
            new Middleware('permission:Expiry Record Update', only: ['update', 'activate', 'deactivate']),
            new Middleware('permission:Expiry Record Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ExpiryRecord::with(['grnItem', 'product', 'productVariant', 'branch']);

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('product_variant_id')) {
                $query->where('product_variant_id', $request->product_variant_id);
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('expiry_date_from')) {
                $query->whereDate('expiry_date', '>=', $request->expiry_date_from);
            }

            if ($request->has('expiry_date_to')) {
                $query->whereDate('expiry_date', '<=', $request->expiry_date_to);
            }

            $expiryRecords = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Expiry records retrieved successfully',
                'data' => $expiryRecords,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch expiry records',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateExpiryRecordRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $expiryRecord = ExpiryRecord::create($data);

            DB::commit();
            $expiryRecord->load(['grnItem', 'product', 'productVariant', 'branch']);

            $this->logActivity('CREATE', 'Expiry Record', "Created expiry record: {$expiryRecord->batch_number}");

            // Instant expiry alert — notify admins if item expires within 30 days
            try {
                $productName = ($expiryRecord->productVariant?->variant_name)
                    ? ($expiryRecord->product?->product_name . ' (' . $expiryRecord->productVariant->variant_name . ')')
                    : ($expiryRecord->product?->product_name ?? 'Unknown');

                AlertService::expiryAlert(
                    productName: $productName,
                    batchNumber: $expiryRecord->batch_number,
                    branchName:  $expiryRecord->branch?->name ?? 'Unknown Branch',
                    expiryDate:  $expiryRecord->expiry_date,
                );
            } catch (\Throwable) {
                // Non-fatal — do not block the response
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Expiry record created successfully',
                'data' => $expiryRecord,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create expiry record',
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
            $expiryRecord = ExpiryRecord::with(['grnItem', 'product', 'productVariant', 'branch'])->find($id);

            if (! $expiryRecord) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Expiry record not found',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Expiry record retrieved successfully',
                'data' => $expiryRecord,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve expiry record',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpiryRecordRequest $request, string $id)
    {
        try {
            $expiryRecord = ExpiryRecord::query()->find($id);

            if (! $expiryRecord) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Expiry record not found',
                    'data'    => [],
                ], 404);
            }

            DB::beginTransaction();

            $previousStatus = $expiryRecord->status;
            $expiryRecord->update($request->validated());

            // Auto-record stock OUT when expiry status changes to written_off
            if ($previousStatus !== 'written_off' && $expiryRecord->status === 'written_off') {
                $this->applyExpiryWriteOff($expiryRecord);

                $recipientService = app(NotificationRecipientService::class);
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Expiry Write-Off',
                    'message' => 'Expiry record ' . $expiryRecord->batch_number . ' has been written off.',
                    'type' => 'expiry_write_off',
                    'module' => 'expiry-records',
                    'priority' => 'critical',
                    'reference_id' => $expiryRecord->id,
                    'reference_type' => ExpiryRecord::class,
                    'url' => '/expiry-records/' . $expiryRecord->id,
                ]);

                foreach ($recipientService->actorAndReportingManager(Auth::user()) as $target) {
                    $target->notify($notification);
                }
            }

            DB::commit();
            $expiryRecord->load(['grnItem', 'product', 'productVariant', 'branch']);

            $this->logActivity('UPDATE', 'Expiry Record', "Updated expiry record: {$expiryRecord->batch_number}");

            return response()->json([
                'status'  => 'success',
                'message' => 'Expiry record updated successfully',
                'data'    => $expiryRecord,
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
                'message' => 'Failed to update expiry record',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $expiryRecord = ExpiryRecord::query()->find($id);

            if (! $expiryRecord) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Expiry record not found',
                    'data'    => [],
                ], 404);
            }

            DB::beginTransaction();

            // If stock OUT was posted for this expiry record (written_off), post compensating IN
            $hasLedgerOut = StockLedger::where('reference_type', ExpiryRecord::class)
                ->where('reference_id', $expiryRecord->id)
                ->exists();

            if ($hasLedgerOut) {
                $qty = floatval($expiryRecord->quantity ?? 0);
                if ($qty > 0) {
                    StockLedgerService::recordIn(
                        productId:       $expiryRecord->product_id,
                        variantId:       $expiryRecord->product_variant_id,
                        branchId:        $expiryRecord->branch_id,
                        quantity:        $qty,
                        unitId:          null,
                        referenceType:   ExpiryRecord::class . '_Reversal',
                        referenceId:     $expiryRecord->id,
                        transactionDate: now()->toDateString(),
                        createdBy:       Auth::id(),
                    );
                }
            }

            if (! ExpiryRecord::destroy($id)) {
                DB::rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Failed to delete expiry record',
                ], 500);
            }

            DB::commit();

            $this->logActivity('DELETE', 'Expiry Record', "Deleted expiry record: {$expiryRecord->batch_number}");

            return response()->json([
                'status'  => 'success',
                'message' => 'Expiry record deleted successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete expiry record',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Write stock OUT entry when an expiry record is written off.
     */
    private function applyExpiryWriteOff(ExpiryRecord $record): void
    {
        $qty = floatval($record->quantity ?? 0);
        if ($qty <= 0) return;

        StockLedgerService::assertSufficientStock($record->product_id, $record->branch_id, $qty);

        StockLedgerService::recordOut(
            productId:       $record->product_id,
            variantId:       $record->product_variant_id,
            branchId:        $record->branch_id,
            quantity:        $qty,
            unitId:          null,
            referenceType:   ExpiryRecord::class,
            referenceId:     $record->id,
            transactionDate: $record->expiry_date?->toDateString(),
            createdBy:       Auth::id(),
        );

        Log::info('Expiry write-off stock ledger OUT created', ['expiry_record_id' => $record->id]);
    }


     public function activate(string $id)
    {
        return $this->setActiveState(ExpiryRecord::class, $id, true, [
            'not_found' => 'Expiry record not found',
            'already' => 'Expiry record is already active',
            'success' => 'Expiry record activated successfully',
            'failed' => 'Failed to activate expiry record',
        ], [
            'already' => 'error',
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($expiryRecord) {
                Log::info('Expiry record activated', [
                    'user_id' => Auth::id(),
                    'expiry_record_id' => $expiryRecord->id,
                ]);
            },
        ]);
    }

    public function deactivate(string $id)
    {
        return $this->setActiveState(ExpiryRecord::class, $id, false, [
            'not_found' => 'Expiry record not found',
            'already' => 'Expiry record is already inactive',
            'success' => 'Expiry record deactivated successfully',
            'failed' => 'Failed to deactivate expiry record',
        ], [
            'already' => 'error',
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($expiryRecord) {
                Log::info('Expiry record deactivated', [
                    'user_id' => Auth::id(),
                    'expiry_record_id' => $expiryRecord->id,
                ]);
            },
        ]);
    }
}
