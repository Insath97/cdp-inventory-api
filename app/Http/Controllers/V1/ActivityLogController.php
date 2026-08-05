<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ActivityLogController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Activity Log Index', only: ['index']),
            new Middleware('permission:Activity Log Show', only: ['show']),
        ];
    }

    /**
     * Display a listing of the activity logs.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ActivityLog::with('user:id,name,email');

            $user = Auth::user();
            if ($user && !$user->can('Activity Log View All')) {
                $subordinateIds = User::where('reporting_manager_id', $user->id)
                    ->orWhere('parent_user_id', $user->id)
                    ->pluck('id')
                    ->push($user->id)
                    ->toArray();

                $query->whereIn('user_id', $subordinateIds);
            }

           if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('module') && $request->module != '') {
                $query->where('module', $request->module);
            }

            if ($request->has('action') && $request->action != '') {
                $query->where('action', $request->action);
            }

            if ($request->has('start_date') && $request->start_date != '') {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date') && $request->end_date != '') {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            $query->orderBy('created_at', 'desc');

            $logs = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Activity logs retrieved successfully',
                'data' => $logs
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve activity logs',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified activity log.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $log = ActivityLog::with('user:id,name,email')->find($id);

            if (!$log) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Activity log not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Activity log retrieved successfully',
                'data' => $log
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve activity log',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
