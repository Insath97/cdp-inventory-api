<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\ActivityLogTrait;
use App\Models\ReportingManager as ReportingManagerModel;
use App\Http\Requests\CreateReportingManagerRequest;
use App\Http\Requests\UpdateReportingManagerRequest;
use Illuminate\Support\Facades\DB;


use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReportingManagerController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:ReportingManager Index', only: ['index', 'show']),
            new Middleware('permission:ReportingManager Create', only: ['store']),
            new Middleware('permission:ReportingManager Update', only: ['update']),
            new Middleware('permission:ReportingManager Delete', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ReportingManagerModel::query();

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_reporting_manager')) {
                $query->where('is_reporting_manager', $request->boolean('is_reporting_manager'));
            }

            if ($request->has('is_default')) {
                $query->where('is_default', $request->boolean('is_default'));
            }

            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            $reportingManagers = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Reporting managers fetched successfully',
                'data' => $reportingManagers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch reporting managers',
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
    public function store(CreateReportingManagerRequest $request)
    {
         try {
            DB::beginTransaction();

            $data = $request->validated();
            $reportingManager = ReportingManagerModel::create($data);

            DB::commit();

            $this->logActivity('CREATE', 'ReportingManager', "Created reporting manager: {$reportingManager->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Reporting manager created successfully',
                'data' => $reportingManager
            ], 201);
        }  catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create reporting manager',
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
            $reportingManager = ReportingManagerModel::query()->find($id);

            if (!$reportingManager) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reporting manager not found'
                ], 404);
            }

            Log::info('Reporting manager viewed', [
                'user_id' => Auth::id(),
                'reporting_manager_id' => $reportingManager->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Reporting manager retrieved successfully',
                'data' => $reportingManager
            ]);
        } catch (\Throwable $th) {
            Log::error('Failed to retrieve reporting manager', [
                'user_id' => Auth::id(),
                'error' => $th->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve reporting manager',
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
    public function update(UpdateReportingManagerRequest $request, string $id)
    {
        try {
            $reportingManager = ReportingManagerModel::query()->find($id);

            if (!$reportingManager) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reporting manager not found'
                ], 404);
            }

            DB::beginTransaction();

            $data = $request->validated();
            $reportingManager->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'ReportingManager', "Updated reporting manager: {$reportingManager->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Reporting manager updated successfully',
                'data' => $reportingManager
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
             return response()->json([
                'status' => 'error',
                'message' => 'Failed to update reporting manager',
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
            $reportingManager = ReportingManagerModel::query()->find($id);
            if (! $reportingManager) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reporting manager not found',
                    'data' => [],
                ], 404);
            }

            $title = $reportingManager->name;
            if (! ReportingManagerModel::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete reporting manager',
                ], 500);
            }

            $this->logActivity('DELETE', 'ReportingManager', "Deleted reporting manager: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Reporting manager deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete reporting manager',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

        public function toggleStatus(string $id)
    {
        try {
            $reportingManager = ReportingManagerModel::query()->find($id);

            if (!$reportingManager) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reporting manager not found'
                ], 404);
            }

            $reportingManager->is_active = !$reportingManager->is_active;
            $reportingManager->save();

            Log::info('Reporting manager status toggled', [
                'user_id' => Auth::id(),
                'reporting_manager_id' => $reportingManager->id,
                'new_status' => $reportingManager->is_active
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Reporting manager status updated successfully',
                'data' => [
                    'id' => $reportingManager->id,
                    'is_active' => $reportingManager->is_active
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle reporting manager status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

     public function activate(string $id)
    {
        try {
            $reportingManager = ReportingManagerModel::query()->find($id);

            if (! $reportingManager) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reporting manager not found',
                ], 404);
            }

            if ($reportingManager->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reporting manager is already active',
                ], 422);
            }

            $reportingManager->update(['is_active' => true]);

            Log::info('Reporting manager activated', [
                'user_id' => Auth::id(),
                'reporting_manager_id' => $reportingManager->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Reporting manager activated successfully',
                'data' => [
                    'id' => $reportingManager->id,
                    'is_active' => $reportingManager->is_active,
                ]
            ]);
        } catch (
            \Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate reporting manager',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function deactivate(string $id)
    {
        try {
            $reportingManager = ReportingManagerModel::query()->find($id);

            if (! $reportingManager) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reporting manager not found',
                ], 404);
            }

            if (! $reportingManager->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reporting manager is already inactive',
                ], 422);
            }

            $reportingManager->update(['is_active' => false]);

            Log::info('Reporting manager deactivated', [
                'user_id' => Auth::id(),
                'reporting_manager_id' => $reportingManager->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Reporting manager deactivated successfully',
                'data' => [
                    'id' => $reportingManager->id,
                    'is_active' => $reportingManager->is_active,
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate reporting manager',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
