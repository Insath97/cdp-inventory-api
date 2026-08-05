<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class PaymentController extends Controller implements HasMiddleware
{
    use ActivityLogTrait, FileUploadTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Payment Index', only: ['index', 'show']),
            new Middleware('permission:Payment Create', only: ['store']),
            new Middleware('permission:Payment Update', only: ['update']),
            new Middleware('permission:Payment Delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Payment::with(['supplier', 'purchaseOrder', 'supplierBankAccount', 'paidBy']);

            $user = Auth::user();

           if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->has('purchase_order_id')) {
                $query->where('purchase_order_id', $request->purchase_order_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $payments = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Payments fetched successfully',
                'data' => $payments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function create()
    {
        return response()->json(['status' => 'error', 'message' => 'Not supported'], 405);
    }

    public function store(CreatePaymentRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Handle bill image upload using FileUploadTrait
            if ($request->hasFile('bill_image')) {
                $imagePath = $this->handleFileUpload(
                    $request,
                    'bill_image',
                    null,
                    'payments/bills',
                    'BILL-' . ($data['payment_number'] ?? uniqid())
                );
                $data['bill_image'] = $imagePath;
            } else {
                unset($data['bill_image']);
            }

            $payment = Payment::create($data);

            // Sync PO financial fields after payment creation
            if ($payment->purchase_order_id) {
                $this->syncPurchaseOrderAmounts($payment->purchase_order_id);
            }

            DB::commit();

            $this->logActivity('CREATE', 'Payment', "Created payment: {$payment->payment_number}");

           return response()->json([
                'status' => 'success',
                'message' => 'Payment created successfully',
                'data' => $payment->load(['supplier', 'purchaseOrder', 'supplierBankAccount', 'paidBy'])
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create payment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $payment = Payment::with(['supplier', 'purchaseOrder', 'supplierBankAccount', 'paidBy'])->find($id);

              if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found',
                    'data' => []
                ], 404);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Payment retrieved successfully',
                'data' => $payment
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve payment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function edit(string $id)
    {
       //
    }

    public function update(UpdatePaymentRequest $request, string $id)
    {
        try {
            $payment = Payment::query()->find($id);

            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found',
                    'data' => []
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();

            // Handle bill image upload: replaces old file if a new one is uploaded
            if ($request->hasFile('bill_image')) {
                $imagePath = $this->handleFileUpload(
                    $request,
                    'bill_image',
                    $payment->bill_image,   // old path → will be deleted by the trait
                    'payments/bills',
                    'BILL-' . $payment->payment_number
                );
                $data['bill_image'] = $imagePath;
            } else {
                // Preserve existing bill_image — don't overwrite with null
                unset($data['bill_image']);
            }

            $payment->update($data);

            // Re-sync PO financial fields (handles status changes: pending→completed etc.)
            if ($payment->purchase_order_id) {
                $this->syncPurchaseOrderAmounts($payment->purchase_order_id);
            }

            DB::commit();

            $this->logActivity('UPDATE', 'Payment', "Updated payment: {$payment->payment_number}");

            return response()->json([
                'status' => 'success',
                'message' => 'Payment updated successfully',
                'data' => $payment->load(['supplier', 'purchaseOrder', 'supplierBankAccount', 'paidBy'])
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update payment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $payment = Payment::query()->find($id);
            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found',
                    'data' => [],
                ], 404);
            }

            $poId = $payment->purchase_order_id;

            // Delete bill image file if exists
            if ($payment->bill_image) {
                $this->deleteFile($payment->bill_image);
            }

            $title = $payment->payment_number;
            if (!Payment::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete payment',
                ], 500);
            }

            // Re-sync PO after payment deletion
            if ($poId) {
                $this->syncPurchaseOrderAmounts($poId);
            }

            $this->logActivity('DELETE', 'Payment', "Deleted payment: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Payment deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete payment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Recalculate and persist the PurchaseOrder's amount_paid and amount_due
     * based on all payments with status = 'completed' for that PO.
     * Called automatically after every payment create, update, or delete.
     */
    private function syncPurchaseOrderAmounts(int $purchaseOrderId): void
    {
        $po = PurchaseOrder::find($purchaseOrderId);
        if (! $po) {
            return;
        }

        $totalPaid = Payment::where('purchase_order_id', $purchaseOrderId)
            ->where('status', 'completed')
            ->sum('amount');

        $po->update([
            'amount_paid' => $totalPaid,
            'amount_due'  => max(0, $po->total_amount - $totalPaid),
        ]);
    }
}
