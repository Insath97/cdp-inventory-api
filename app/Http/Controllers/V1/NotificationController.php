<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class NotificationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
        ];
    }
    /**
     * Display all notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();

            return response()->json([
                'status' => 'success',
                'message' => 'Notifications fetched successfully',
                'data' => $user ? $user->notifications : [],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch notifications',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display unread notifications for the authenticated user.
     */
    public function unread(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();

            return response()->json([
                'status' => 'success',
                'message' => 'Unread notifications fetched successfully',
                'data' => $user ? $user->unreadNotifications : [],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch unread notifications',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $notification = $user ? $user->notifications()->find($id) : null;

            if ($notification) {
                $notification->markAsRead();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Notification marked as read',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Notification not found',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark notification as read',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): JsonResponse
    {
        try {
            $user = auth('api')->user();
            if ($user) {
                $user->unreadNotifications->markAsRead();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'All notifications marked as read',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark all notifications as read',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
