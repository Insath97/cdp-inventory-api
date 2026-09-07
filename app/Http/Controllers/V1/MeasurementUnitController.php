<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MeasurementUnit;
use App\Http\Requests\CreateMeasurementUnitRequest;
use App\Http\Requests\UpdateMeasurementUnitRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\TogglesActiveStatus;

class MeasurementUnitController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    use TogglesActiveStatus;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Measurement Index|MeasurementUnit Index', only: ['index', 'show']),
            new Middleware('permission:Measurement Create|MeasurementUnit Create', only: ['store']),
            new Middleware('permission:Measurement Update|MeasurementUnit Update', only: ['update']),
            new Middleware('permission:Measurement Delete|MeasurementUnit Delete', only: ['destroy']),
            new Middleware('permission:Measurement Toggle Status', only: ['toggleStatus', 'activate', 'deactivate']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = MeasurementUnit::query();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }


            $units = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Measurement Units fetched successfully',
                'data' => $units
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch measurement units',
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
    public function store(CreateMeasurementUnitRequest $request)
    {
         try {
            DB::beginTransaction();

            $data = $request->validated();
            $unit = MeasurementUnit::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'MeasurementUnit', "Created measurement unit: {$unit->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Measurement unit created successfully',
                'data' => $unit
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create measurement unit',
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
            $measurementunit = MeasurementUnit::query()->find($id);

            if (!$measurementunit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Measurement unit not found'
                ], 404);
            }

            Log::info('Measurement unit viewed', [
                'user_id' => Auth::id(),
                'measurement_unit_id' => $measurementunit->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Measurement unit retrieved successfully',
                'data' => $measurementunit
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to retrieved measurement unit', [
                'user_id' => Auth::id(),
                'error' => $th->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve measurement unit',
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
    public function update(UpdateMeasurementUnitRequest $request, string $id)
    {
         try {
            $measurementunit = MeasurementUnit::query()->find($id);

            if (!$measurementunit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Measurement unit not found'
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $measurementunit->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'MeasurementUnit', "Updated measurement unit: {$measurementunit->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Measurement unit updated successfully',
                'data' => $measurementunit
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
             return response()->json([
                'status' => 'error',
                'message' => 'Failed to update measurement unit',
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
            $measurementunit = MeasurementUnit::query()->find($id);
            if (! $measurementunit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Measurement unit not found',
                    'data' => [],
                ], 404);
            }

            $title = $measurementunit->name;
            if (! MeasurementUnit::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete measurement unit',
                ], 500);
            }

            $this->logActivity('DELETE', 'MeasurementUnit', "Deleted measurement unit: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Measurement unit deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete measurement unit',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }


     public function toggleStatus(string $id)
    {
        return $this->setActiveState(MeasurementUnit::class, $id, null, [
            'not_found' => 'Measurement unit not found',
            'success' => 'Measurement unit status updated successfully',
            'failed' => 'Failed to toggle measurement unit status',
        ], [
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($measurementUnit) {
                Log::info('Measurement unit status toggled', [
                    'user_id' => Auth::id(),
                    'measurement_unit_id' => $measurementUnit->id,
                    'new_status' => $measurementUnit->is_active
                ]);
            },
        ]);
    }

     public function activate(string $id)
    {
        return $this->setActiveState(MeasurementUnit::class, $id, true, [
            'not_found' => 'Measurement unit not found',
            'already' => 'Measurement unit is already active',
            'success' => 'Measurement unit activated successfully',
            'failed' => 'Failed to activate measurement unit',
        ], [
            'already' => 'error',
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($measurementUnit) {
                Log::info('Measurement unit activated', [
                    'user_id' => Auth::id(),
                    'measurement_unit_id' => $measurementUnit->id,
                ]);
            },
        ]);
    }

    public function deactivate(string $id)
    {
        return $this->setActiveState(MeasurementUnit::class, $id, false, [
            'not_found' => 'Measurement unit not found',
            'already' => 'Measurement unit is already inactive',
            'success' => 'Measurement unit deactivated successfully',
            'failed' => 'Failed to deactivate measurement unit',
        ], [
            'already' => 'error',
            'data' => 'subset',
            'raw_error' => true,
            'log' => function ($measurementUnit) {
                Log::info('Measurement unit deactivated', [
                    'user_id' => Auth::id(),
                    'measurement_unit_id' => $measurementUnit->id,
                ]);
            },
        ]);
    }
}
