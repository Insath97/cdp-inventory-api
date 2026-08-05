<?php

namespace App\Services;

use App\Models\StockLedger;
use App\Models\ReorderLevel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Branch;
use App\Services\AlertService;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class StockLedgerService
{
    /**
     * The current running balance for a product, optionally scoped to a
     * branch. Always product+branch scoped, never per-variant — see the
     * note in writeEntry() for why.
     */
    public static function getBalance(int $productId, ?int $branchId = null): float
    {
        return (float) (StockLedger::query()
            ->where('product_id', $productId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->value('balance') ?? 0);
    }

    /**
     * @throws InsufficientStockException
     */
    public static function assertSufficientStock(int $productId, ?int $branchId, float $requestedQty): void
    {
        $available = self::getBalance($productId, $branchId);

        if ($requestedQty > $available) {
            throw new InsufficientStockException($available, $requestedQty);
        }
    }

    public static function recordIn(
        int     $productId,
        ?int    $variantId,
        ?int    $branchId,
        float   $quantity,
        ?int    $unitId,
        string  $referenceType,
        int     $referenceId,
        ?string $transactionDate = null,
        ?int    $createdBy       = null,
    ): ?StockLedger {
        return self::writeEntry(
            productId:       $productId,
            variantId:       $variantId,
            branchId:        $branchId,
            quantityIn:      $quantity,
            quantityOut:     0,
            unitId:          $unitId,
            referenceType:   $referenceType,
            referenceId:     $referenceId,
            transactionDate: $transactionDate,
            createdBy:       $createdBy,
        );
    }

    public static function recordOut(
        int     $productId,
        ?int    $variantId,
        ?int    $branchId,
        float   $quantity,
        ?int    $unitId,
        string  $referenceType,
        int     $referenceId,
        ?string $transactionDate = null,
        ?int    $createdBy       = null,
    ): ?StockLedger {
        return self::writeEntry(
            productId:       $productId,
            variantId:       $variantId,
            branchId:        $branchId,
            quantityIn:      0,
            quantityOut:     $quantity,
            unitId:          $unitId,
            referenceType:   $referenceType,
            referenceId:     $referenceId,
            transactionDate: $transactionDate,
            createdBy:       $createdBy,
        );
    }


    private static function writeEntry(
        int     $productId,
        ?int    $variantId,
        ?int    $branchId,
        float   $quantityIn,
        float   $quantityOut,
        ?int    $unitId,
        string  $referenceType,
        int     $referenceId,
        ?string $transactionDate,
        ?int    $createdBy,
    ): ?StockLedger {
        if (empty($branchId) && auth()->user()?->branch_id) {
            $branchId = auth()->user()->branch_id;
        }

        try {
            return DB::transaction(function () use ($productId, $variantId, $branchId, $quantityIn, $quantityOut, $unitId, $referenceType, $referenceId, $transactionDate, $createdBy) {
                // Lock the variant or product row to serialize concurrent balance calculations
                if ($variantId) {
                    ProductVariant::where('id', $variantId)->lockForUpdate()->first();
                } else {
                    Product::where('id', $productId)->lockForUpdate()->first();
                }

                // Running balance is tracked per product+branch, NOT per variant.
                // GRNs and transfers pass a variant id while check-ins/check-outs
                // cannot (their tables have no variant column), so scoping the
                // lookup by variant made the two write streams read different
                // balances and corrupt each other's running total.
                $lastBalance = self::getBalance($productId, $branchId);

                $newBalance = $lastBalance + $quantityIn - $quantityOut;

                // Guard: warn if balance goes negative (stock integrity risk)
                if ($newBalance < 0) {
                    Log::warning('StockLedger: Balance would go negative — possible stock integrity issue', [
                        'product_id'     => $productId,
                        'variant_id'     => $variantId,
                        'branch_id'      => $branchId,
                        'last_balance'   => $lastBalance,
                        'quantity_in'    => $quantityIn,
                        'quantity_out'   => $quantityOut,
                        'new_balance'    => $newBalance,
                        'reference_type' => $referenceType,
                        'reference_id'   => $referenceId,
                    ]);
                }

                $unitPrice = 0;
                if (str_contains((string)$referenceType, 'Grn')) {
                    $unitPrice = \Illuminate\Support\Facades\DB::table('grn_items')
                        ->where('grn_id', $referenceId)
                        ->where('product_id', $productId)
                        ->value('unit_price') ?? 0;
                }
                if (!$unitPrice && str_contains((string)$referenceType, 'PurchaseReturnNote')) {
                    $unitPrice = \Illuminate\Support\Facades\DB::table('purchase_return_note_items')
                        ->where('purchase_return_note_id', $referenceId)
                        ->where('product_id', $productId)
                        ->value('unit_price') ?? 0;
                }
                if (!$unitPrice && $productId) {
                    $unitPrice = \App\Services\ProductPriceService::resolveCurrentPrice($productId);
                }
                $movedQty = max($quantityIn, $quantityOut);
                $totalAmount = $movedQty * (float)$unitPrice;

                $stockLedger = StockLedger::create([
                    'product_id'         => $productId,
                    'product_variant_id' => $variantId,
                    'branch_id'          => $branchId,
                    'reference_type'     => $referenceType,
                    'reference_id'       => $referenceId,
                    'transaction_date'   => $transactionDate ?? now(),
                    'quantity_in'        => $quantityIn,
                    'quantity_out'       => $quantityOut,
                    'balance'            => $newBalance,
                    'unit_price'         => $unitPrice,
                    'total_amount'       => $totalAmount,
                    'unit_id'            => $unitId,
                    'created_by'         => $createdBy,
                ]);

                $stockLedger->load(['product', 'productVariant', 'branch']);

                // Instant low-stock alert: check reorder level after every OUT entry
                if ($quantityOut > 0 && $branchId) {
                    try {
                        $reorderLevel = ReorderLevel::where('product_id', $productId)
                            ->where('branch_id', $branchId)
                            ->where('is_active', true)
                            ->when($variantId, fn ($q) => $q->where('product_variant_id', $variantId))
                            ->first();

                        Log::info('ReorderLevel alert check', [
                            'product_id' => $productId,
                            'variant_id' => $variantId,
                            'branch_id'  => $branchId,
                            'reorder_level_found' => $reorderLevel ? $reorderLevel->id : null,
                            'min_quantity' => $reorderLevel?->min_quantity,
                            'last_balance' => $lastBalance,
                            'new_balance'  => $newBalance,
                        ]);

                        $minQuantity = $reorderLevel ? floatval($reorderLevel->min_quantity) : null;
                        // Only alert on the transition from above-threshold to
                        // at/below-threshold — not on every subsequent write
                        // while stock is still low, to avoid repeat spam.
                        $wasAboveThreshold = $minQuantity !== null && $lastBalance > $minQuantity;

                        if ($reorderLevel && $wasAboveThreshold && $newBalance <= $minQuantity) {
                            $product = Product::find($productId);
                            $variant = $variantId ? ProductVariant::find($variantId) : null;
                            $branch  = Branch::find($branchId);

                            $productName = $variant
                                ? ($product?->product_name . ' (' . $variant->variant_name . ')')
                                : ($product?->product_name ?? 'Unknown');

                            Log::info('ReorderLevel LOW STOCK ALERT TRIGGERED', [
                                'product' => $productName,
                                'branch'  => $branch?->name,
                                'current_stock' => $newBalance,
                                'min_stock'     => $minQuantity,
                            ]);

                            AlertService::lowStockAlert(
                                productName:  $productName,
                                branchName:   $branch?->name ?? 'Unknown Branch',
                                currentStock: $newBalance,
                                minStock:     $minQuantity,
                                branchId:     $branchId,
                                productId:    $productId,
                            );
                        } elseif ($reorderLevel) {
                            Log::info('ReorderLevel alert NOT triggered', [
                                'reason' => !$wasAboveThreshold
                                    ? "lastBalance ($lastBalance) was NOT above minQuantity ($minQuantity) - already below threshold"
                                    : "newBalance ($newBalance) still above minQuantity ($minQuantity)",
                            ]);
                        }
                    } catch (\Throwable $e) {
                        // Non-fatal — log but don't interrupt the ledger write
                        Log::warning('AlertService::lowStockAlert failed', ['error' => $e->getMessage()]);
                    }
                }

                return $stockLedger;
            });
        } catch (\Throwable $th) {
            Log::error('StockLedgerService write failed', [
                'product_id'     => $productId,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'error'          => $th->getMessage(),
            ]);

            throw $th;
        }
    }
}
