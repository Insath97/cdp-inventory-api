<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckOutRequest;
use App\Http\Requests\UpdateCheckOutRequest;
use App\Models\CheckOut;
use App\Services\NotificationRecipientService;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Traits\TogglesActiveStatus;

class CheckOutController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    use TogglesActiveStatus;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:CheckOut Index', only: ['index', 'show', 'getStats']),
            new Middleware('permission:CheckOut Create', only: ['store']),
            new Middleware('permission:CheckOut Update', only: ['update']),
            new Middleware('permission:CheckOut Delete', only: ['destroy']),
            new Middleware('permission:CheckOut Toggle Status', only: ['activate', 'deactivate', 'toggleStatus']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = CheckOut::with(['product', 'container', 'branch']);

            $user = Auth::user();

            if ($request->has('search') ) {
                $query->search($request->search);
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $checkOuts = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'CheckOuts retrieved successfully',
                'data' => $checkOuts,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve check outs',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateCheckOutRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            if (empty($data['status'])) {
                $data['status'] = 'completed';
            }

            $checkOut = CheckOut::create($data);

            $recipientService = app(NotificationRecipientService::class);
            $notification = new \App\Notifications\InventoryAlertNotification([
                'title' => 'Checked Out',
                'message' => ($checkOut->product?->product_name ?? 'Item') . ' checked out successfully.',
                'type' => 'checked_out',
                'module' => 'check-outs',
                'priority' => 'medium',
                'reference_id' => $checkOut->id,
                'reference_type' => CheckOut::class,
                'url' => '/check-outs/' . $checkOut->id,
            ]);

            foreach ($recipientService->actorAndReportingManager(Auth::user()) as $target) {
                $target->notify($notification);
            }

            DB::commit();

            $this->logActivity('CREATE', 'CheckOut', "Created check-out: {$checkOut->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut created successfully',
                'data' => $checkOut,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create check out',
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
            $checkOut = CheckOut::find($id);
            if (!$checkOut) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckOut not found',
                    'data' => [],
                ], 404);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut retrieved successfully',
                'data' => $checkOut,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve check out',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCheckOutRequest $request, string $id)
    {
        try {
            $checkOut = CheckOut::find($id);
            if (!$checkOut) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckOut not found',
                    'data' => [],
                ], 404);
            }
            DB::beginTransaction();

            $data = $request->validated();
            $checkOut->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'CheckOut', "Updated check-out: {$checkOut->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut updated successfully',
                'data' => $checkOut,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update check out',
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
            $checkOut = CheckOut::find($id);
            if (!$checkOut) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CheckOut not found',
                    'data' => [],
                ], 404);
            }

            DB::beginTransaction();

            $title = "CheckOut {$checkOut->id}";
            if (!CheckOut::destroy($id)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete check out',
                ], 500);
            }

            DB::commit();

            $this->logActivity('DELETE', 'CheckOut', "Deleted check-out: {$title}");
            return response()->json([
                'status' => 'success',
                'message' => 'CheckOut deleted successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete check out',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of the check-out.
     */
    public function toggleStatus(string $id)
    {
        return $this->setActiveState(CheckOut::class, $id, null, [
            'not_found' => 'CheckOut not found',
            'success' => 'CheckOut status updated successfully',
            'failed' => 'Failed to toggle check out status',
        ], [
            'data' => 'subset',
            'log' => function ($checkOut) {
                $this->logActivity('TOGGLE_STATUS', 'CheckOut', "Toggled status for check-out ID {$checkOut->id}");
            },
        ]);
    }

    /**
     * Activate the check-out.
     */
    public function activate(string $id)
    {
        return $this->setActiveState(CheckOut::class, $id, true, [
            'not_found' => 'CheckOut not found',
            'already' => 'CheckOut is already active',
            'success' => 'CheckOut activated successfully',
            'failed' => 'Failed to activate check out',
        ], [
            'log' => function ($checkOut) {
                $this->logActivity('ACTIVATE', 'CheckOut', "Activated check-out ID {$checkOut->id}");
            },
        ]);
    }

    /**
     * Deactivate the check-out.
     */
    public function deactivate(string $id)
    {
        return $this->setActiveState(CheckOut::class, $id, false, [
            'not_found' => 'CheckOut not found',
            'already' => 'CheckOut is already inactive',
            'success' => 'CheckOut deactivated successfully',
            'failed' => 'Failed to deactivate check out',
        ], [
            'log' => function ($checkOut) {
                $this->logActivity('DEACTIVATE', 'CheckOut', "Deactivated check-out ID {$checkOut->id}");
            },
        ]);
    }
}
