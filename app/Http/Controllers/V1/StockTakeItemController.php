<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStockTakeItemRequest;
use App\Http\Requests\UpdateStockTakeItemRequest;
use App\Models\StockTakeItem;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class StockTakeItemController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:StockTakeItem Index', only: ['index', 'show']),
            new Middleware('permission:StockTakeItem Create', only: ['store']),
            new Middleware('permission:StockTakeItem Update', only: ['update']),
            new Middleware('permission:StockTakeItem Delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = StockTakeItem::query()->with(['product', 'productVariant', 'unit', 'stockTake']);

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->filled('stock_take_id')) {
                $query->where('stock_take_id', $request->stock_take_id);
            }

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->filled('product_variant_id')) {
                $query->where('product_variant_id', $request->product_variant_id);
            }

            if ($request->filled('unit_id')) {
                $query->where('unit_id', $request->unit_id);
            }

            $stockTakeItems = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Stock take items fetched successfully',
                'data' => $stockTakeItems,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stock take items',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function store(CreateStockTakeItemRequest $request)
    {
        try {
            DB::beginTransaction();

            $stockTakeItem = StockTakeItem::create($request->validated());

            DB::commit();

            $this->logActivity('CREATE', 'StockTakeItem', "Created stock take item: {$stockTakeItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock take item created successfully',
                'data' => $stockTakeItem,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create stock take item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $stockTakeItem = StockTakeItem::with(['stockTake', 'product', 'productVariant', 'unit'])->find($id);

            if (! $stockTakeItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock take item not found',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Stock take item retrieved successfully',
                'data' => $stockTakeItem,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stock take item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function update(UpdateStockTakeItemRequest $request, string $id)
    {
        try {
            $stockTakeItem = StockTakeItem::query()->find($id);

            if (! $stockTakeItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock take item not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            $stockTakeItem->update($request->validated());

            DB::commit();

            $this->logActivity('UPDATE', 'StockTakeItem', "Updated stock take item: {$stockTakeItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock take item updated successfully',
                'data' => $stockTakeItem,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock take item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $stockTakeItem = StockTakeItem::query()->find($id);

            if (! $stockTakeItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stock take item not found',
                    'data' => [],
                ], 404);
            }

            if (! StockTakeItem::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete stock take item',
                ], 500);
            }

            $this->logActivity('DELETE', 'StockTakeItem', "Deleted stock take item: {$stockTakeItem->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Stock take item deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete stock take item',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
