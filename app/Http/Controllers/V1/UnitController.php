<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Unit;
use App\Http\Requests\CreateUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UnitController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Unit Index', only: ['index', 'show']),
            new Middleware('permission:Unit Create', only: ['store']),
            new Middleware('permission:Unit Update', only: ['update']),
            new Middleware('permission:Unit Delete', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
         try {
            $perPage = $request->get('per_page', 15);
            $query = Unit::query();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }


            $units = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Units fetched successfully',
                'data' => $units
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch units',
                'error' => $e->getMessage()
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
    public function store(CreateUnitRequest $request)
    {
         try {
            DB::beginTransaction();

            $data = $request->validated();
            $unit = Unit::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'Unit', "Created unit: {$unit->unit_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Unit created successfully',
                'data' => $unit
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $unit = Unit::query()->find($id);

            if (!$unit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit not found'
                ], 404);
            }

            Log::info('Unit viewed', [
                'user_id' => Auth::id(),
                'unit_id' => $unit->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Unit retrieved successfully',
                'data' => $unit
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to retrieve unit', [
                'user_id' => Auth::id(),
                'error' => $th->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve unit',
                'error' => $th->getMessage()
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
    public function update(UpdateUnitRequest $request, string $id)
    {
        try {
            $unit = Unit::query()->find($id);

            if (!$unit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit not found'
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $unit->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'Unit', "Updated unit: {$unit->unit_name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Unit updated successfully',
                'data' => $unit
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
             return response()->json([
                'status' => 'error',
                'message' => 'Failed to update unit',
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
            $unit = Unit::query()->find($id);
            if (! $unit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit not found',
                    'data' => [],
                ], 404);
            }

            $title = $unit->unit_name;
            if (! Unit::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete unit',
                ], 500);
            }

            $this->logActivity('DELETE', 'Unit', "Deleted unit: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Unit deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete unit',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
