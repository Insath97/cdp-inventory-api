<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBranchRequestRequest;
use App\Http\Requests\UpdateBranchRequestRequest;
use App\Models\BranchRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationRecipientService;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Traits\ActivityLogTrait;

class BranchRequestController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Branch Request Index', only: ['index', 'show']),
            new Middleware('permission:Branch Request Create', only: ['store']),
            new Middleware('permission:Branch Request Update', only: ['update']),
            new Middleware('permission:Branch Request Delete', only: ['destroy']),
            new Middleware('permission:Branch Request Toggle Status', only: ['toggleStatus', 'activate', 'deactivate']),
        ];
    }

    /**
     * List branch requests.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = BranchRequest::with(['branch', 'requester', 'approver']);
            $user = Auth::user();

            if ($request->has('search') ) {
                $query->search($request->search);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            $branchRequests = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Branch requests retrieved successfully',
                'data' => $branchRequests,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve branch requests',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a new branch request.
     */
    public function store(CreateBranchRequestRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $branchRequest = BranchRequest::create($data);
            DB::commit();

            $this->logActivity('CREATE', 'BranchRequest', "Created branch request: {$branchRequest->request_no}");

            try {
                $recipientService = app(NotificationRecipientService::class);
                $notification = new \App\Notifications\InventoryAlertNotification([
                    'title' => 'Branch Request Created',
                    'message' => 'Branch request ' . $branchRequest->request_no . ' has been created and is awaiting approval.',
                    'type' => 'branch_request_created',
                    'module' => 'branch-requests',
                    'priority' => 'medium',
                    'reference_id' => $branchRequest->id,
                    'reference_type' => BranchRequest::class,
                    'url' => '/branch-requests/' . $branchRequest->id,
                ]);

                foreach ($recipientService->actorAndReportingManager(Auth::user()) as $target) {
                    $target->notify($notification);
                }
            } catch (\Throwable $notifyErr) {
                Log::error('Failed to send Branch Request creation notification: ' . $notifyErr->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Branch request created successfully',
                'data' => $branchRequest,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create branch request',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Show a specific branch request.
     */
    public function show(string $id)
    {
        try {
            $branchRequest = BranchRequest::find($id);
            if (! $branchRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch request not found',
                    'data' => [],
                ], 404);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Branch request retrieved successfully',
                'data' => $branchRequest,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve branch request',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update an existing branch request.
     */
    public function update(UpdateBranchRequestRequest $request, string $id)
    {
        try {
            $branchRequest = BranchRequest::find($id);
            if (! $branchRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch request not found',
                    'data' => [],
                ], 404);
            }
            DB::beginTransaction();

            $data = $request->validated();
            
            if (isset($data['status']) && in_array($data['status'], ['approved', 'fulfilled']) && !in_array($branchRequest->status, ['approved', 'fulfilled'])) {
                $data['approved_by'] = Auth::id();
                $data['approved_at'] = now();
            }

            $branchRequest->update($data);

            DB::commit();

            $this->logActivity('UPDATE', 'BranchRequest', "Updated branch request: {$branchRequest->request_no}");

            // Notify the Manager who requested it, and their reporting manager
            $requester = User::find($branchRequest->requested_by);
            if ($requester) {
                $statusText = ucfirst($branchRequest->status);

                try {
                    $notification = new \App\Notifications\InventoryAlertNotification([
                        'title'          => "Branch Request {$statusText}",
                        'message'        => "Your Branch Request ({$branchRequest->request_no}) status has been updated to {$statusText}.",
                        'type'           => 'branch_request_status_update',
                        'module'         => 'branch-requests',
                        'priority'       => in_array($branchRequest->status, ['approved', 'fulfilled']) ? 'high' : 'medium',
                        'reference_id'   => $branchRequest->id,
                        'reference_type' => BranchRequest::class,
                        'url'            => '/branch-requests',
                    ]);

                    $recipientService = app(NotificationRecipientService::class);
                    foreach ($recipientService->actorAndReportingManager($requester) as $target) {
                        $target->notify($notification);
                    }
                } catch (\Throwable $notifyErr) {
                    Log::error('Failed to send Branch Request notification to requester: ' . $notifyErr->getMessage());
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Branch request updated successfully',
                'data' => $branchRequest,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update branch request',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete (soft‑delete) a branch request.
     */
    public function destroy(string $id)
    {
        try {
            $branchRequest = BranchRequest::find($id);
            if (! $branchRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch request not found',
                    'data' => [],
                ], 404);
            }
            $title = $branchRequest->request_no;
           if (!BranchRequest::destroy($id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete branch request',
                ], 500);
            }
            $this->logActivity('DELETE', 'BranchRequest', "Deleted branch request: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Branch request deleted successfully',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete branch request',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Activate a branch request (set status to approved).
     */
    public function activate(string $id)
    {
        try {
            $branchRequest = BranchRequest::find($id);
            if (! $branchRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch request not found',
                    'data' => [],
                ], 404);
            }
            $branchRequest->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $recipientService = app(NotificationRecipientService::class);
            $notification = new \App\Notifications\InventoryAlertNotification([
                'title' => 'Branch Request Approved',
                'message' => 'Branch request ' . $branchRequest->request_no . ' has been approved.',
                'type' => 'branch_request_approved',
                'module' => 'branch-requests',
                'priority' => 'medium',
                'reference_id' => $branchRequest->id,
                'reference_type' => BranchRequest::class,
                'url' => '/branch-requests/' . $branchRequest->id,
            ]);

            foreach ($recipientService->actorAndReportingManager($branchRequest->requester) as $target) {
                $target->notify($notification);
            }

            $this->logActivity('ACTIVATE', 'BranchRequest', "Activated branch request: {$branchRequest->request_no}");
            return response()->json([
                'status' => 'success',
                'message' => 'Branch request activated successfully',
                'data' => $branchRequest,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate branch request',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Deactivate a branch request (set status to rejected).
     */
    public function deactivate(string $id)
    {
        try {
            $branchRequest = BranchRequest::find($id);
            if (! $branchRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch request not found',
                    'data' => [],
                ], 404);
            }
            $branchRequest->update(['status' => 'rejected']);

            $recipientService = app(NotificationRecipientService::class);
            $notification = new \App\Notifications\InventoryAlertNotification([
                'title' => 'Branch Request Rejected',
                'message' => 'Branch request ' . $branchRequest->request_no . ' has been rejected.',
                'type' => 'branch_request_rejected',
                'module' => 'branch-requests',
                'priority' => 'medium',
                'reference_id' => $branchRequest->id,
                'reference_type' => BranchRequest::class,
                'url' => '/branch-requests/' . $branchRequest->id,
            ]);

            foreach ($recipientService->actorAndReportingManager($branchRequest->requester) as $target) {
                $target->notify($notification);
            }
            $this->logActivity('DEACTIVATE', 'BranchRequest', "Deactivated branch request: {$branchRequest->request_no}");
            return response()->json([
                'status' => 'success',
                'message' => 'Branch request deactivated successfully',
                'data' => $branchRequest,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate branch request',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle status between pending and approved.
     */
    public function toggleStatus(string $id)
    {
        try {
            $branchRequest = BranchRequest::find($id);
            if (! $branchRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch request not found',
                    'data' => [],
                ], 404);
            }
            $newStatus = $branchRequest->status === 'approved' ? 'pending' : 'approved';
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'approved') {
                $updateData['approved_by'] = Auth::id();
                $updateData['approved_at'] = now();
            }
            $branchRequest->update($updateData);
            $this->logActivity('TOGGLE_STATUS', 'BranchRequest', "Toggled status for branch request: {$branchRequest->request_no}");
            return response()->json([
                'status' => 'success',
                'message' => 'Branch request status toggled successfully',
                'data' => $branchRequest,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

}
