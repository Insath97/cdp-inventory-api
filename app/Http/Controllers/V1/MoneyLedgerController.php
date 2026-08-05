<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Grn;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturnNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;

class MoneyLedgerController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:StockLedger Index|StockTake Index|CheckIn Index|CheckOut Index|Grn Index|PurchaseOrder Index', only: ['index']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $page    = $request->get('page', 1);
            $entries = collect();

            $user = Auth::user();

            $paymentQuery = Payment::query()->with(['supplier', 'purchaseOrder.branch']);

            $paymentQuery->get()->each(function ($payment) use ($entries) {
                $entries->push([
                    'id' => 'payment-' . $payment->id,
                    'type' => 'payment',
                    'type_label' => 'Supplier Payment',
                    'direction' => 'out',
                    'date' => optional($payment->payment_date)->format('Y-m-d')
                        ?? optional($payment->created_at)->format('Y-m-d'),
                    'reference' => $payment->payment_number,
                    'party' => optional($payment->supplier)->supplier_name ?? '—',
                    'branch' => optional(optional($payment->purchaseOrder)->branch)->name ?? '—',
                    'amount' => (float) $payment->amount,
                    'status' => $payment->status,
                    'description' => $payment->purchaseOrder
                        ? ('Payment for PO ' . $payment->purchaseOrder->po_number)
                        : 'Payment to supplier',
                ]);
            });

            $poQuery = PurchaseOrder::query()->with(['supplier', 'branch']);
            if (!empty($subordinateIds)) {
                $poQuery->where(function($q) use ($subordinateIds, $branchIds) {
                    $q->whereIn('created_by', $subordinateIds);
                    if (!empty($branchIds)) {
                        $q->orWhereIn('branch_id', $branchIds);
                    }
                });
            }
            $poQuery->get()->each(function ($po) use ($entries) {
                $entries->push([
                    'id' => 'po-' . $po->id,
                    'type' => 'purchase_order',
                    'type_label' => 'Purchase Order',
                    'direction' => 'out',
                    'date' => optional($po->order_date)->format('Y-m-d')
                        ?? optional($po->created_at)->format('Y-m-d'),
                    'reference' => $po->po_number,
                    'party' => optional($po->supplier)->supplier_name ?? '—',
                    'branch' => optional($po->branch)->name ?? '—',
                    'amount' => (float) $po->total_amount,
                    'status' => $po->status,
                    'description' => 'Purchase order total',
                ]);
            });

            $prnQuery = PurchaseReturnNote::query()->with(['supplier', 'branch', 'items']);
            if (!empty($subordinateIds)) {
                $prnQuery->where(function($q) use ($subordinateIds, $branchIds) {
                    $q->whereIn('created_by', $subordinateIds);
                    if (!empty($branchIds)) {
                        $q->orWhereIn('branch_id', $branchIds);
                    }
                });
            }
            $prnQuery->get()->each(function ($prn) use ($entries) {
                $amount = $prn->items->sum(fn ($item) => (float) $item->quantity_returned * (float) $item->unit_price);
                $entries->push([
                    'id' => 'prn-' . $prn->id,
                    'type' => 'purchase_return',
                    'type_label' => 'Purchase Return',
                    'direction' => 'in',
                    'date' => optional($prn->return_date)->format('Y-m-d')
                        ?? optional($prn->created_at)->format('Y-m-d'),
                    'reference' => $prn->prn_number,
                    'party' => optional($prn->supplier)->supplier_name ?? '—',
                    'branch' => optional($prn->branch)->name ?? '—',
                    'amount' => round($amount, 2),
                    'status' => $prn->status,
                    'description' => 'Returned goods credit',
                ]);
            });

            $grnQuery = Grn::query()->with(['supplier', 'branch', 'items']);
            if (!empty($subordinateIds)) {
                $grnQuery->where(function($q) use ($subordinateIds, $branchIds) {
                    $q->whereIn('received_by', $subordinateIds);
                    if (!empty($branchIds)) {
                        $q->orWhereIn('branch_id', $branchIds);
                    }
                });
            }
            $grnQuery->get()->each(function ($grn) use ($entries) {
                $amount = $grn->items->sum(fn ($item) => (float) $item->quantity_received * (float) $item->unit_price);
                $entries->push([
                    'id' => 'grn-' . $grn->id,
                    'type' => 'grn',
                    'type_label' => 'GRN Received',
                    'direction' => 'in',
                    'date' => optional($grn->received_date)->format('Y-m-d')
                        ?? optional($grn->created_at)->format('Y-m-d'),
                    'reference' => $grn->grn_number,
                    'party' => optional($grn->supplier)->supplier_name ?? '—',
                    'branch' => optional($grn->branch)->name ?? '—',
                    'amount' => round($amount, 2),
                    'status' => $grn->status,
                    'description' => 'Goods received value',
                ]);
            });

            $stockLedgerQuery = \App\Models\StockLedger::query()->with(['product', 'branch']);
            if (!empty($subordinateIds)) {
                $stockLedgerQuery->where(function($q) use ($subordinateIds, $branchIds) {
                    $q->whereIn('created_by', $subordinateIds);
                    if (!empty($branchIds)) {
                        $q->orWhereIn('branch_id', $branchIds);
                    }
                });
            }
            $stockLedgerQuery->get()->each(function ($sl) use ($entries) {
                $qtyIn = (float) $sl->quantity_in;
                $qtyOut = (float) $sl->quantity_out;
                $unitPrice = (float) ($sl->unit_price > 0 ? $sl->unit_price : ($sl->product?->cost_price ?? $sl->product?->buying_price ?? $sl->product?->selling_price ?? 0));
                $amount = ($qtyIn > 0 ? $qtyIn : $qtyOut) * $unitPrice;
                $direction = $qtyIn > 0 ? 'in' : 'out';

                $rawRefType = $sl->reference_type ?? "";
                $refName = class_basename($rawRefType);

                $entries->push([
                    'id' => 'sl-' . $sl->id,
                    'type' => $qtyIn > 0 ? 'grn' : 'payment',
                    'type_label' => $qtyIn > 0 ? 'Stock In Value' : 'Stock Out Value',
                    'direction' => $direction,
                    'date' => optional($sl->transaction_date)->format('Y-m-d')
                        ?? optional($sl->created_at)->format('Y-m-d'),
                    'reference' => $refName ? ($refName . ' #' . $sl->reference_id) : ('SL #' . $sl->id),
                    'party' => $sl->product?->product_name ?? '—',
                    'branch' => optional($sl->branch)->name ?? '—',
                    'amount' => round($amount, 2),
                    'status' => 'completed',
                    'description' => ($qtyIn > 0 ? 'Stock In: ' : 'Stock Out: ') . ($sl->product?->product_name ?? 'Item'),
                ]);
            });

            $sorted = $entries->sortByDesc('date')->values();

            $summary = [
                'total_entries'  => $sorted->count(),
                'total_in'       => round($sorted->where('direction', 'in')->sum('amount'), 2),
                'total_out'      => round($sorted->where('direction', 'out')->sum('amount'), 2),
                'by_type'        => [
                    'payment'         => round($sorted->where('type', 'payment')->sum('amount'), 2),
                    'purchase_order'  => round($sorted->where('type', 'purchase_order')->sum('amount'), 2),
                    'purchase_return' => round($sorted->where('type', 'purchase_return')->sum('amount'), 2),
                    'grn'             => round($sorted->where('type', 'grn')->sum('amount'), 2),
                ],
            ];
            $summary['net'] = round($summary['total_in'] - $summary['total_out'], 2);

            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $sorted->forPage($page, $perPage)->values(),
                $sorted->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Money activities fetched successfully',
                'data'    => $paginated,
                'summary' => $summary,
            ]);
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch money activities: ' . $th->getMessage() . "\n" . $th->getTraceAsString());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch money activities',
                'error'   => $th->getMessage(),
            ], 500);
        }
    }
}
