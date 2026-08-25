<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGrnRequest;
use App\Http\Requests\UpdateGrnRequest;
use App\Models\ExpiryRecord;
use App\Models\Grn;
use App\Models\StockLedger;
use App\Services\StockLedgerService;
use App\Services\NotificationRecipientService;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment;
use App\Models\User;

class GrnController extends Controller implements HasMiddleware
{
    use ActivityLogTrait, FileUploadTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Grn Index', only: ['index', 'show']),
            new Middleware('permission:Grn Create', only: ['store']),
            new Middleware('permission:Grn Update', only: ['update']),
            new Middleware('permission:Grn Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = Grn::query()->with(['purchaseOrder', 'supplier', 'receiver'])->withCount('items');

            $user = Auth::user();
            $subordinateIds = [];
            $isAdminOrReportingManager = false;

            if ($user && !$user->can('Grn View All')) {
                $rmIds = \App\Models\ReportingManager::where('email', $user->email)
                    ->orWhere('username', $user->username)
                    ->orWhere('name', $user->name)
                    ->pluck('id')
                    ->toArray();

                $hasSubordinates = User::whereIn('reporting_manager_id', $rmIds)
                    ->orWhere('reporting_manager_id', $user->id)
                    ->orWhere('parent_user_id', $user->id)
                    ->where('id', '!=', $user->id)
                    ->exists();

                $isAdminOrReportingManager = $hasSubordinates || $user->branch_id;

                if ($isAdminOrReportingManager) {
                    $subordinateIds = User::where(function($q) use ($rmIds, $user) {
                        if (!empty($rmIds)) {
                            $q->whereIn('reporting_manager_id', $rmIds);
                        }
                        $q->orWhere('reporting_manager_id', $user->id)
                          ->orWhere('parent_user_id', $user->id);
                    })
                    ->pluck('id')
                    ->push($user->id)
                    ->toArray();

                    $query->where(function ($q) use ($subordinateIds) {
                        $q->whereIn('received_by', $subordinateIds);
                    });
                } else {
                    $query->where('received_by', $user->id);
                }
            }

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->filled('purchase_order_id')) {
                $query->where('purchase_order_id', $request->purchase_order_id);
            }

            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }


            if ($request->filled('received_by')) {
                $query->where('received_by', $request->received_by);
            }

            if ($request->filled('received_by')) {
                $query->where('received_by', $request->received_by);
            }

            if ($request->filled('received_date')) {
                $query->whereDate('received_date', $request->received_date);
            }

            $grns = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'GRNs fetched successfully',
                'data' => $grns,
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to fetch GRNs: ' . $th->getMessage() . "\n" . $th->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch GRNs',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateGrnRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            unset($data['status']);

            if (empty($data['grn_number'])) {
                $maxId = (Grn::max('id') ?? 0) + 1;
                $grnNumber = 'GRN-' . date('Y') . '-' . str_pad($maxId, 4, '0', STR_PAD_LEFT);
                while (Grn::where('grn_number', $grnNumber)->exists()) {
                    $maxId++;
                    $grnNumber = 'GRN-' . date('Y') . '-' . str_pad($maxId, 4, '0', STR_PAD_LEFT);
                }
                $data['grn_number'] = $grnNumber;
            }

            if ($request->hasFile('bill_image')) {
                $imagePath = $this->handleFileUpload(
                    $request,
                    'bill_image',
                    null,
                    'grns/bills',
                    'GRN-BILL-' . $data['grn_number']
                );
                $data['bill_image'] = $imagePath;
            } else {
                unset($data['bill_image']);
            }

            if (!isset($data['created_by'])) {
                $data['created_by'] = Auth::id();
            }
            if (!isset($data['received_by'])) {
                $data['received_by'] = Auth::id();
            }
            if (empty($data['batch_number'])) {
                $data['batch_number'] = 'BATCH-' . date('Ymd') . '-' . rand(100, 999999);
            }

            $grn = Grn::create($data);

            // Auto-create pending Payment record if connected to a Purchase Order
            if (!empty($grn->purchase_order_id)) {
                $existingPayment = Payment::where('purchase_order_id', $grn->purchase_order_id)->first();
                if (!$existingPayment) {
                    $po = $grn->purchaseOrder;
                    $poAmount = $po ? floatval($po->total_amount ?? 0) : 0;
                    Payment::create([
                        'payment_number' => 'PAY-' . strtoupper(uniqid()),
                        'purchase_order_id' => $grn->purchase_order_id,
                        'supplier_id' => $grn->supplier_id,
                        'amount' => $poAmount,
                        'status' => 'pending',
                        'paid_by' => Auth::id() ?? $grn->created_by,
                        'payment_date' => now()->toDateString(),
                        'notes' => 'Auto-generated pending payment for GRN ' . $grn->grn_number,
                    ]);
                }
            }

            DB::commit();

            try {
                $grn->load(['supplier']);
                $creator = Auth::user() ?? \App\Models\User::find($grn->created_by);

                $creatorName = $creator ? $creator->name : 'System User';
                $supplierName = $grn->supplier?->supplier_name ?? 'N/A';

                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'New GRN Created: ' . ($grn->grn_number ?? $grn->id),
                    'message' => "New Goods Received Note ({$grn->grn_number}) was created by {$creatorName} for Supplier: {$supplierName}.",
                    'type' => 'grn_created',
                    'module' => 'grns',
                    'priority' => 'medium',
                    'reference_id' => $grn->id,
                    'reference_type' => Grn::class,
                    'url' => '/grns/' . $grn->id,
                ]);

                $grnPermissions = ['Grn Index', 'Grn Update', 'Grn View All'];
                $recipientService = app(NotificationRecipientService::class);
                $targets = $recipientService->usersByPermissions($grnPermissions);

                $notifiedUserIds = [];

                foreach ($targets as $targetUser) {
                    $targetUser->notify($notification);
                    $notifiedUserIds[] = $targetUser->id;
                }

                // Identify and Notify Creator's Reporting Manager if not already notified
                if ($creator) {
                    $reportingManagerUser = $recipientService->reportingManagerOf($creator, $grnPermissions);
                    if ($reportingManagerUser && !in_array($reportingManagerUser->id, $notifiedUserIds)) {
                        $reportingManagerUser->notify($notification);
                    }
                }
            } catch (\Throwable $notifyErr) {
                Log::error('Failed to send GRN creation notification to reporting manager: ' . $notifyErr->getMessage());
            }

            $this->logActivity('CREATE', 'Grn', "Created GRN: {$grn->grn_number}");

            return response()->json([
                'status' => 'success',
                'message' => 'GRN created successfully',
                'data' => $grn->load(['purchaseOrder', 'supplier', 'receiver']),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create GRN',
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
            $grn = Grn::with(['purchaseOrder', 'supplier', 'receiver', 'items.product', 'items.productVariant.product', 'items.unit', 'items.container', 'items.serials'])->find($id);

            if (!$grn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'GRN not found'
                ], 404);
            }

            Log::info('GRN viewed', [
                'user_id' => Auth::id(),
                'grn_id' => $grn->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'GRN retrieved successfully',
                'data' => $grn
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to retrieve GRN', [
                'user_id' => Auth::id(),
                'error' => $th->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve GRN',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGrnRequest $request, string $id)
    {
        try {
            $grn = Grn::query()->find($id);

            if (! $grn) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'GRN not found',
                    'data'    => [],
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            unset($data['status']);

            // Handle bill image upload/update
            if ($request->hasFile('bill_image')) {
                $imagePath = $this->handleFileUpload(
                    $request,
                    'bill_image',
                    $grn->bill_image,
                    'grns/bills',
                    'GRN-BILL-' . ($data['grn_number'] ?? $grn->grn_number)
                );
                $data['bill_image'] = $imagePath;
            } else {
                if (isset($data['bill_image']) && is_string($data['bill_image']) && !empty($data['bill_image'])) {
                    $data['bill_image'] = $data['bill_image'];
                } else {
                    unset($data['bill_image']);
                }
            }

            unset($data['created_by']);
            $grn->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'Grn', "Updated GRN: {$grn->grn_number}");

            return response()->json([
                'status'  => 'success',
                'message' => 'GRN updated successfully',
                'data'    => $grn->load(['purchaseOrder', 'supplier', 'receiver']),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update GRN',
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
            $grn = Grn::query()->find($id);

            if (! $grn) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'GRN not found',
                    'data'    => [],
                ], 404);
            }

            $hasPrn = DB::table('purchase_return_notes')->where('grn_id', $id)->exists();
            if ($hasPrn) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'This GRN cannot be deleted because it is connected to a Purchase Return Note (PRN).',
                    'error'   => 'This GRN is connected to a Purchase Return Note (PRN). Please delete the PRN first.',
                ], 422);
            }

            $number = $grn->grn_number;

            if ($grn->bill_image) {
                $this->deleteFile($grn->bill_image);
            }

            if (! Grn::destroy($id)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Failed to delete GRN',
                ], 500);
            }

            $this->logActivity('DELETE', 'Grn', "Deleted GRN: {$number}");

            return response()->json([
                'status'  => 'success',
                'message' => 'GRN deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete GRN',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Create one stock ledger IN entry per GRN item when the GRN is received.
     */
    private function applyGrnLedgerEntries(Grn $grn): void
    {
        $grn->load('items');
        $createdBy = $grn->received_by ?? Auth::id();

        foreach ($grn->items as $item) {
            $qty = floatval($item->quantity_received ?? 0);
            if ($qty <= 0) {
                continue;
            }

            // ── Stock Ledger IN ──────────────────────────────────────────────
            StockLedgerService::recordIn(
                productId:       $item->product_id,
                variantId:       $item->product_variant_id,
                branchId:        $grn->branch_id,
                quantity:        $qty,
                unitId:          $item->unit_id,
                referenceType:   Grn::class,
                referenceId:     $grn->id,
                transactionDate: $grn->received_date?->toDateString(),
                createdBy:       $createdBy,
            );

            // ── Auto-create ExpiryRecord if item has an expiry date ───────────
            if ($item->expiry_date) {
                $alreadyExists = ExpiryRecord::where('grn_item_id', $item->id)->exists();
                if (! $alreadyExists) {
                    ExpiryRecord::create([
                        'grn_item_id'        => $item->id,
                        'product_id'         => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'branch_id'          => $grn->branch_id,
                        'batch_number'       => $item->batch_number,
                        'expiry_date'        => $item->expiry_date,
                        'quantity'           => $qty,
                        'status'             => 'active',
                        'is_active'          => true,
                    ]);
                }
            }
        }

        Log::info('GRN stock ledger IN entries created', ['grn_id' => $grn->id, 'items' => $grn->items->count()]);
    }
}
