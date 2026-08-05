<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckOutRequest;
use App\Http\Requests\UpdateCheckOutRequest;
use App\Models\CheckOut;
use App\Models\StockLedger;
use App\Services\StockLedgerService;
use App\Services\NotificationRecipientService;
use App\Exceptions\InsufficientStockException;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CheckOutController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:CheckOut Index', only: ['index', 'show', 'getStats']),
            new Middleware('permission:CheckOut Create', only: ['store']),
            new Middleware('permission:CheckOut Update', only: ['update']),
            new Middleware('permission:CheckOut Delete', only: ['destroy']),
            new Middleware('permission:CheckOut Toggle Status', only: ['activate', 'deactivate', 'toggleStatus']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = CheckOut::with(['product', 'container', 'branch']);

            $user = Auth::user();

            if ($request->has('search') ) {
                $query->search($request->search);
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $checkOuts = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'CheckOuts retrieved successfully',
                'data' => $checkOuts,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve check outs',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateCheckOutRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            if (empty($data['status'])) {
                $data['status'] = 'completed';
            }

            // Oversell prevention: check available stock balance before allowing checkout
            $requestedQty = floatval($data['quantity'] ?? 0);
            if ($requestedQty > 0 && $data['status'] === 'completed') {
                StockLedgerService::assertSufficientStock($data['product_id'], $data['branch_id'], $requestedQty);
            }

            $checkOut = CheckOut::create($data);

            // Record stock OUT if status is completed
            $qty = floatval($checkOut->quantity ?? 0);
            if ($checkOut->status === 'completed' && $qty > 0) {
                StockLedgerService::recordOut(
                    productId:       $checkOut->product_id,
                    variantId:       null,
                    branchId:        $checkOut->branch_id,
                    quantity:        $qty,
                    unitId:          null,
                    referenceType:   CheckOut::class,
                    referenceId:     $checkOut->id,
                    transactionDate: now()->toDateString(),
                    createdBy:       Auth::id(),
                );
            }

            $recipientService = app(NotificationRecipientService::class);
            $notification = new \App\Notifications\InventoryAlertNotification([
                'title' => 'Checked Out',
                'message' => ($checkOut->product?->product_name ?? 'Item') . ' checked out successfully.',
                'type' => 'checked_out',
                'module' => 'check-outs',
                'priority' => 'medium',
                'reference_id' => $checkOut->id,
                'reference_type' => CheckOut::class,
                'url' => '/check-outs/' . $checkOut->id,
            ]);

            $targets = $recipientService->mergeCollections(
                Auth::user() ? collect([Auth::user()]) : collect(),
                $recipientService->branchAdmins($checkOut->branch),
                $recipientService->supervisorsForBranch($checkOut->branch),
                $recipientService->hrTeam()
            );

            foreach ($targets as $user) {
                $user->notify($notification);
            }

            $reportingManager = $recipientService->reportingManagerOf(Auth::user(), ['CheckOut Update']);
            if ($reportingManager && !$targets->contains('id', $reportingManager->id)) {
                $reportingManager->notify($notification);
            }

            $admins = $recipientService->adminsAndSuperAdmins($checkOut->branch_id);
            foreach ($admins as $admin) {
                if ($admin->email) {
                    \Illuminate\Support\Facades\Mail::to($admin->email)->send(new \App\Mail\ManualInventoryActivityMail($checkOut, 'Check Out'));
                }
            }

            DB::commit();

            $this->logActivity('CREATE', 'CheckOut', "Created check-out: {$checkOut->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut created successfully',
                'data' => $checkOut,
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
                'message' => 'Failed to create check out',
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
            $checkOut = CheckOut::find($id);
            if (!$checkOut) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckOut not found',
                    'data' => [],
                ], 404);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut retrieved successfully',
                'data' => $checkOut,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve check out',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCheckOutRequest $request, string $id)
    {
        try {
            $checkOut = CheckOut::find($id);
            if (!$checkOut) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckOut not found',
                    'data' => [],
                ], 404);
            }
            DB::beginTransaction();

            $oldQty = floatval($checkOut->quantity ?? 0);
            $oldProductId = $checkOut->product_id;
            $oldBranchId = $checkOut->branch_id;

            $data = $request->validated();
            $checkOut->update($data);

            $newQty = floatval($checkOut->quantity ?? 0);
            $newProductId = $checkOut->product_id;
            $newBranchId = $checkOut->branch_id;

            // If quantity, product, or branch changed, reverse old OUT and re-post new OUT
            $hasExistingLedger = StockLedger::where('reference_type', CheckOut::class)
                ->where('reference_id', $checkOut->id)
                ->exists();

            if ($hasExistingLedger && ($oldQty != $newQty || $oldProductId != $newProductId || $oldBranchId != $newBranchId)) {
                // Reverse old stock OUT
                if ($oldQty > 0) {
                    StockLedgerService::recordIn(
                        productId:       $oldProductId,
                        variantId:       null,
                        branchId:        $oldBranchId,
                        quantity:        $oldQty,
                        unitId:          null,
                        referenceType:   CheckOut::class . '_Reversal',
                        referenceId:     $checkOut->id,
                        transactionDate: now()->toDateString(),
                        createdBy:       Auth::id(),
                    );
                }

                // Re-post new stock OUT
                if ($newQty > 0) {
                    StockLedgerService::assertSufficientStock($newProductId, $newBranchId, $newQty);
                    StockLedgerService::recordOut(
                        productId:       $newProductId,
                        variantId:       null,
                        branchId:        $newBranchId,
                        quantity:        $newQty,
                        unitId:          null,
                        referenceType:   CheckOut::class,
                        referenceId:     $checkOut->id,
                        transactionDate: now()->toDateString(),
                        createdBy:       Auth::id(),
                    );
                }
            }

            DB::commit();

            $this->logActivity('UPDATE', 'CheckOut', "Updated check-out: {$checkOut->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut updated successfully',
                'data' => $checkOut,
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
                'message' => 'Failed to update check out',
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
            $checkOut = CheckOut::find($id);
            if (!$checkOut) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckOut not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            // If stock OUT was posted for this check-out, post a compensating stock IN
            $hasLedgerOut = StockLedger::where('reference_type', CheckOut::class)
                ->where('reference_id', $checkOut->id)
                ->exists();

            if ($hasLedgerOut) {
                $qty = floatval($checkOut->quantity ?? 0);
                if ($qty > 0) {
                    StockLedgerService::recordIn(
                        productId:       $checkOut->product_id,
                        variantId:       null,
                        branchId:        $checkOut->branch_id,
                        quantity:        $qty,
                        unitId:          null,
                        referenceType:   CheckOut::class . '_Reversal',
                        referenceId:     $checkOut->id,
                        transactionDate: now()->toDateString(),
                        createdBy:       Auth::id(),
                    );
                }
            }

            $title = "CheckOut {$checkOut->id}";
            if (!CheckOut::destroy($id)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete check out',
                ], 500);
            }

            DB::commit();

            $this->logActivity('DELETE', 'CheckOut', "Deleted check-out: {$title}");
            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut deleted successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete check out',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of the check-out.
     */
    public function toggleStatus(string $id)
    {
        try {
            $checkOut = CheckOut::find($id);
            if (!$checkOut) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckOut not found',
                ], 404);
            }
            $checkOut->is_active = !$checkOut->is_active;
            $checkOut->save();
            $this->logActivity('TOGGLE_STATUS', 'CheckOut', "Toggled status for check-out ID {$checkOut->id}");
            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut status updated successfully',
                'data' => [
                    'id' => $checkOut->id,
                    'is_active' => $checkOut->is_active,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle check out status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Activate the check-out.
     */
    public function activate(string $id)
    {
        try {
            $checkOut = CheckOut::find($id);
            if (!$checkOut) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckOut not found',
                ], 404);
            }
            if ($checkOut->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'CheckOut is already active',
                    'data' => $checkOut,
                ]);
            }
            $checkOut->update(['is_active' => true]);
            $this->logActivity('ACTIVATE', 'CheckOut', "Activated check-out ID {$checkOut->id}");
            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut activated successfully',
                'data' => $checkOut,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate check out',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Deactivate the check-out.
     */
    public function deactivate(string $id)
    {
        try {
            $checkOut = CheckOut::find($id);
            if (!$checkOut) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckOut not found',
                ], 404);
            }
            if (!$checkOut->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'CheckOut is already inactive',
                    'data' => $checkOut,
                ]);
            }
            $checkOut->update(['is_active' => false]);
            $this->logActivity('DEACTIVATE', 'CheckOut', "Deactivated check-out ID {$checkOut->id}");
            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut deactivated successfully',
                'data' => $checkOut,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate check out',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
