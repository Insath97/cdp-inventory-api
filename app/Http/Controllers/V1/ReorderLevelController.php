<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Models\ReorderLevel;
use App\Models\StockLedger;
use App\Http\Requests\CreateReorderLevelRequest;
use App\Http\Requests\UpdateReorderLevelRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;
use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\TogglesActiveStatus;

class ReorderLevelController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    use TogglesActiveStatus;

    /**
     * Look up the latest stock_ledger running balance for a reorder level's
     * product(+variant)+branch — same "most recent by transaction_date, id"
     * lookup StockLedgerService::writeEntry() uses when writing a new entry —
     * and attach it as current_stock so the frontend can show real "Triggered"
     * status instead of a static threshold with no live comparison.
     */
    private function attachCurrentStock(ReorderLevel $reorderLevel): ReorderLevel
    {
        // Product+branch scoped, matching StockLedgerService — see the note there
        // on why variant is deliberately not part of the running-balance key.
        $balance = StockLedger::query()
            ->where('product_id', $reorderLevel->product_id)
            ->where('branch_id', $reorderLevel->branch_id)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->value('balance');

        $reorderLevel->current_stock = $balance !== null ? (float) $balance : 0.0;

        return $reorderLevel;
    }

    /**
     * Define the middleware for permissions.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Reorder Level Index', only: ['index', 'show']),
            new Middleware('permission:Reorder Level Create', only: ['store']),
            new Middleware('permission:Reorder Level Update', only: ['update']),
            new Middleware('permission:Reorder Level Delete', only: ['destroy']),
            new Middleware('permission:Reorder Level Toggle Status', only: ['toggleStatus', 'activate', 'deactivate']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ReorderLevel::with(['product', 'productVariant', 'branch']);

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('product_variant_id')) {
                $query->where('product_variant_id', $request->product_variant_id);
            }

            $reorderLevels = $query->paginate($perPage);
            $reorderLevels->getCollection()->transform(fn ($level) => $this->attachCurrentStock($level));

            return response()->json([
                'status' => 'success',
                'message' => 'Reorder levels fetched successfully',
                'data' => $reorderLevels
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch reorder levels',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateReorderLevelRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $reorderLevel = ReorderLevel::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'ReorderLevel', "Created reorder level configuration ID: {$reorderLevel->id} for Product ID: {$reorderLevel->product_id}");

            $reorderLevel->load(['product', 'productVariant', 'branch']);
            $this->attachCurrentStock($reorderLevel);

            return response()->json([
                'status' => 'success',
                'message' => 'Reorder level created successfully',
                'data' => $reorderLevel
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create reorder level',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $reorderLevel = ReorderLevel::with(['product', 'productVariant', 'branch'])->find($id);

            if (!$reorderLevel) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reorder level not found',
                    'data' => []
                ], 404);
            }

            $this->attachCurrentStock($reorderLevel);

            return response()->json([
                'status' => 'success',
                'message' => 'Reorder level retrieved successfully',
                'data' => $reorderLevel
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve reorder level',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReorderLevelRequest $request, string $id)
    {
        try {
            $reorderLevel = ReorderLevel::query()->find($id);

            if (!$reorderLevel) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reorder level not found',
                    'data' => []
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $reorderLevel->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'ReorderLevel', "Updated reorder level ID: {$reorderLevel->id}");

            $reorderLevel->load(['product', 'productVariant', 'branch']);
            $this->attachCurrentStock($reorderLevel);

            return response()->json([
                'status' => 'success',
                'message' => 'Reorder level updated successfully',
                'data' => $reorderLevel
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update reorder level',
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
            $reorderLevel = ReorderLevel::query()->find($id);
            if (!$reorderLevel) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reorder level not found',
                    'data' => [],
                ], 404);
            }

            $reorderLevelId = $reorderLevel->id;
             if (! ReorderLevel::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete purchase return note item',
                ], 500);
            }

            $this->logActivity('DELETE', 'ReorderLevel', "Deleted reorder level configuration ID: {$reorderLevelId}");

            return response()->json([
                'status' => 'success',
                'message' => 'Reorder level deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete reorder level',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of the reorder level.
     */
    public function toggleStatus(string $id)
    {
        return $this->setActiveState(ReorderLevel::class, $id, null, [
            'not_found' => 'Reorder level not found',
            'success' => 'Reorder level status updated successfully',
            'failed' => 'Failed to toggle reorder level status',
        ], [
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($reorderLevel) {
                Log::info('Reorder level status toggled', [
                    'user_id' => Auth::id(),
                    'reorder_level_id' => $reorderLevel->id,
                    'new_status' => $reorderLevel->is_active
                ]);
            },
        ]);
    }

    /**
     * Activate the reorder level.
     */
    public function activate(string $id)
    {
        return $this->setActiveState(ReorderLevel::class, $id, true, [
            'not_found' => 'Reorder level not found',
            'already' => 'Reorder level is already active',
            'success' => 'Reorder level activated successfully',
            'failed' => 'Failed to activate reorder level',
        ], [
            'already' => 'error',
            'with' => ['product', 'productVariant', 'branch'],
            'log' => function ($reorderLevel) {
                $this->logActivity('ACTIVATE', 'ReorderLevel', "Activated reorder level configuration ID: {$reorderLevel->id}");
            },
        ]);
    }

    /**
     * Deactivate the reorder level.
     */
    public function deactivate(string $id)
    {
        return $this->setActiveState(ReorderLevel::class, $id, false, [
            'not_found' => 'Reorder level not found',
            'already' => 'Reorder level is already inactive',
            'success' => 'Reorder level deactivated successfully',
            'failed' => 'Failed to deactivate reorder level',
        ], [
            'already' => 'error',
            'with' => ['product', 'productVariant', 'branch'],
            'log' => function ($reorderLevel) {
                $this->logActivity('DEACTIVATE', 'ReorderLevel', "Deactivated reorder level configuration ID: {$reorderLevel->id}");
            },
        ]);
    }
}
