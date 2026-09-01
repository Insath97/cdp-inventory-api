<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckInRequest;
use App\Http\Requests\UpdateCheckInRequest;
use App\Models\CheckIn;
use App\Services\NotificationRecipientService;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CheckInController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:CheckIn Index', only: ['index', 'show']),
            new Middleware('permission:CheckIn Create', only: ['store']),
            new Middleware('permission:CheckIn Update', only: ['update']),
            new Middleware('permission:CheckIn Delete', only: ['destroy']),
            new Middleware('permission:CheckIn Toggle Status', only: ['activate', 'deactivate', 'toggleStatus']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = CheckIn::with(['product', 'container', 'branch', 'supplier']);

            $user = Auth::user();

            if ($request->has('search') ) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $checkIns = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'CheckIns retrieved successfully',
                'data' => $checkIns,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve check ins',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateCheckInRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $checkIn = CheckIn::create($data);

            if ($checkIn->status === 'completed') {
                $recipientService = app(NotificationRecipientService::class);
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Checked In',
                    'message' => ($checkIn->product?->product_name ?? 'Item') . ' checked in successfully.',
                    'type' => 'checked_in',
                    'module' => 'check-ins',
                    'priority' => 'medium',
                    'reference_id' => $checkIn->id,
                    'reference_type' => CheckIn::class,
                    'url' => '/check-ins/' . $checkIn->id,
                ]);

                foreach ($recipientService->actorAndReportingManager(Auth::user()) as $target) {
                    $target->notify($notification);
                }
            }

            DB::commit();

            $this->logActivity('CREATE', 'CheckIn', "Created check-in: {$checkIn->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'CheckIn created successfully',
                'data' => $checkIn,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create check in',
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
            $checkIn = CheckIn::find($id);
            if (!$checkIn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckIn not found',
                    'data' => [],
                ], 404);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'CheckIn retrieved successfully',
                'data' => $checkIn,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve check in',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCheckInRequest $request, string $id)
    {
        try {
            $checkIn = CheckIn::find($id);
            if (!$checkIn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckIn not found',
                    'data' => [],
                ], 404);
            }
            DB::beginTransaction();
            $previousStatus = $checkIn->status;
            $data = $request->validated();
            $checkIn->update($data);

          
            if ($previousStatus !== 'completed' && $checkIn->status === 'completed') {
                $recipientService = app(NotificationRecipientService::class);
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Checked In',
                    'message' => ($checkIn->product?->product_name ?? 'Item') . ' checked in successfully.',
                    'type' => 'checked_in',
                    'module' => 'check-ins',
                    'priority' => 'medium',
                    'reference_id' => $checkIn->id,
                    'reference_type' => CheckIn::class,
                    'url' => '/check-ins/' . $checkIn->id,
                ]);

                foreach ($recipientService->actorAndReportingManager(Auth::user()) as $target) {
                    $target->notify($notification);
                }
            }

            DB::commit();

            $this->logActivity('UPDATE', 'CheckIn', "Updated check-in: {$checkIn->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'CheckIn updated successfully',
                'data' => $checkIn,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update check in',
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
            $checkIn = CheckIn::find($id);
            if (!$checkIn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckIn not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            $title = "CheckIn {$checkIn->id}";
            if (!CheckIn::destroy($id)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete check in',
                ], 500);
            }

            DB::commit();

            $this->logActivity('DELETE', 'CheckIn', "Deleted check-in: {$title}");
            return response()->json([
                'status' => 'success',
                'message' => 'CheckIn deleted successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete check in',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of the check-in.
     */
    public function toggleStatus(string $id)
    {
        try {
            $checkIn = CheckIn::find($id);
            if (!$checkIn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckIn not found',
                ], 404);
            }
            $checkIn->is_active = !$checkIn->is_active;
            $checkIn->save();
            $this->logActivity('TOGGLE_STATUS', 'CheckIn', "Toggled status for check-in ID {$checkIn->id}");
            return response()->json([
                'status' => 'success',
                'message' => 'CheckIn status updated successfully',
                'data' => [
                    'id' => $checkIn->id,
                    'is_active' => $checkIn->is_active,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle check in status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Activate the check-in.
     */
    public function activate(string $id)
    {
        try {
            $checkIn = CheckIn::find($id);
            if (!$checkIn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckIn not found',
                ], 404);
            }
            if ($checkIn->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'CheckIn is already active',
                    'data' => $checkIn,
                ]);
            }
            $checkIn->update(['is_active' => true]);
            $this->logActivity('ACTIVATE', 'CheckIn', "Activated check-in ID {$checkIn->id}");
            return response()->json([
                'status' => 'success',
                'message' => 'CheckIn activated successfully',
                'data' => $checkIn,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate check in',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Deactivate the check-in.
     */
    public function deactivate(string $id)
    {
        try {
            $checkIn = CheckIn::find($id);
            if (!$checkIn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckIn not found',
                ], 404);
            }
            if (!$checkIn->is_active) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'CheckIn is already inactive',
                    'data' => $checkIn,
                ]);
            }
            $checkIn->update(['is_active' => false]);
            $this->logActivity('DEACTIVATE', 'CheckIn', "Deactivated check-in ID {$checkIn->id}");
            return response()->json([
                'status' => 'success',
                'message' => 'CheckIn deactivated successfully',
                'data' => $checkIn,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate check in',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
