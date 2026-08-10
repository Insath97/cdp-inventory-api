<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductReturn;
use App\Http\Requests\CreateProductReturnRequest;
use App\Http\Requests\UpdateProductReturnRequest;
use App\Services\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\ActivityLogTrait;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductReturnController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:ProductReturn Index|CustomerReturn Index|PurchaseReturnNote Index|Grn Index', only: ['index', 'show']),
            new Middleware('permission:ProductReturn Create|CustomerReturn Create|PurchaseReturnNote Create|Grn Create', only: ['store']),
            new Middleware('permission:ProductReturn Update|CustomerReturn Update|PurchaseReturnNote Update|Grn Update', only: ['update']),
            new Middleware('permission:ProductReturn Delete|CustomerReturn Delete|PurchaseReturnNote Delete|Grn Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ProductReturn::query();

            if ($request->has('search') ) {
                $query->search($request->search);
            }

            $returns = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Product returns fetched successfully',
                'data' => $returns
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch product returns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateProductReturnRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            if (empty($data['branch_name'])) {
                if (!empty($data['branch_id'])) {
                    $data['branch_name'] = \App\Models\Branch::find($data['branch_id'])?->name ?? 'Main Branch';
                } else {
                    $data['branch_name'] = 'Main Branch';
                }
            }

            $insufficientError = $this->checkSufficientStock($data['products'], $data['branch_id']);
            if ($insufficientError) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => $insufficientError,
                ], 422);
            }

            // Auto-fill unique return_code (include soft-deleted records to prevent 1062 duplicate entry)
            $maxId = ProductReturn::withTrashed()->max('id') ?? 0;
            $nextSeq = $maxId + 1;
            do {
                $code = 'RT-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
                $exists = ProductReturn::withTrashed()->where('return_code', $code)->exists();
                if ($exists) {
                    $nextSeq++;
                }
            } while ($exists);
            $data['return_code'] = $code;

            $productReturn = ProductReturn::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'Product Return', "Created product return: {$productReturn->return_code} for {$productReturn->person_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product return created successfully',
                'data' => $productReturn
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product return',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $productReturn = ProductReturn::find($id);

            if (!$productReturn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product return not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product return retrieved successfully',
                'data' => $productReturn
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product return',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductReturnRequest $request, string $id)
    {
        try {
            $productReturn = ProductReturn::find($id);

            if (!$productReturn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product return not found',
                    'data' => []
                ], 404);
            }

            $data = $request->validated();

            if (array_key_exists('products', $data)) {
                $branchId = $data['branch_id'] ?? \App\Models\Branch::where('name', $productReturn->branch_name)->value('id');
                $insufficientError = $this->checkSufficientStock($data['products'], $branchId);
                if ($insufficientError) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $insufficientError,
                    ], 422);
                }
            }

            $productReturn->update($data);

            $this->logActivity('UPDATE', 'Product Return', "Updated product return: {$productReturn->return_code}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product return updated successfully',
                'data' => $productReturn
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update product return',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $productReturn = ProductReturn::find($id);

            if (!$productReturn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product return not found',
                    'data' => [],
                ], 404);
            }

            $code = $productReturn->return_code;
            if (!$productReturn->delete()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete product return',
                ], 500);
            }

            $this->logActivity('DELETE', 'Product Return', "Deleted product return: {$code}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product return deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product return',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Validate each return line's quantity against the branch's current
     * stock balance. Returns an error message for the first line that
     * exceeds it, or null if every line is within range.
     */
    private function checkSufficientStock(array $products, ?int $branchId): ?string
    {
        foreach ($products as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = floatval($item['quantity'] ?? 0);

            if (!$productId || $quantity <= 0) {
                continue;
            }

            $available = StockLedgerService::getBalance((int) $productId, $branchId);

            if ($quantity > $available) {
                return "Insufficient stock. Only {$available} units are available in this branch.";
            }
        }

        return null;
    }
}
