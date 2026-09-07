<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AlertService
{
    /**
     * Notify the reporting managers with a message.
     *
     * These alerts fire off a schedule or a stock movement rather than off
     * somebody's action, so there is no actor whose manager we could look up —
     * they go to everyone who is a reporting manager instead. The $extraRoles
     * argument is kept for callers but no longer widens the audience beyond
     * that; nothing in the app passes it today.
     */
    public static function notifyAdmins(
        string $title,
        string $message,
        string $type = 'system_alert',
        array  $extraRoles = []
    ): void {
        try {
            $recipients = app(NotificationRecipientService::class)->reportingManagers();

            foreach ($recipients as $recipient) {
                $recipient->notify(new \App\Notifications\InventoryAlertNotification([
                    'title'   => $title,
                    'message' => $message,
                    'type'    => $type,
                    'module'  => 'system',
                    'priority'=> 'high',
                ]));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AlertService notifyAdmins failed: ' . $e->getMessage());
        }
    }

    /**
     * Fire an expiry alert when an expiry record is created or updated.
     */
    public static function expiryAlert(
        string $productName,
        string $batchNumber,
        string $branchName,
        string $expiryDate
    ): void {
        $daysLeft = max(0, Carbon::today()->diffInDays(Carbon::parse($expiryDate), false));

        if ($daysLeft <= 30) {
            self::notifyAdmins(
                title:   'Expiry Alert',
                message: "Product '{$productName}' (Batch: {$batchNumber}) expires in {$daysLeft} day(s) at branch '{$branchName}'.",
                type:    'expiry_alert',
            );
        }
    }

    /**
     * Fire a low-stock alert when stock drops at or below reorder level.
     *
     * Like the other alerts here this has no acting user, so it goes to the
     * reporting managers. Branch/permission targeting was dropped along with
     * the rest of the team fan-outs — a low stock alert is a prompt to raise a
     * purchase order, which is a manager's call.
     */
    public static function lowStockAlert(
        string $productName,
        string $branchName,
        float  $currentStock,
        float  $minStock,
        ?int   $branchId = null,
        ?int   $productId = null,
    ): void {
        try {
            $targets = app(NotificationRecipientService::class)->reportingManagers();

            $notification = new \App\Notifications\InventoryAlertNotification([
                'title'          => 'Low Stock Alert',
                'message'        => "Stock for '{$productName}' at branch '{$branchName}' is {$currentStock} unit(s), at or below reorder level of {$minStock}. Please raise a purchase order.",
                'type'           => 'low_stock_alert',
                'module'         => 'reorder-levels',
                'priority'       => 'high',
                'reference_id'   => $productId,
                'reference_type' => $productId ? \App\Models\Product::class : null,
                'url'            => '/reorder-levels',
            ]);

            foreach ($targets as $recipient) {
                try {
                    $recipient->notify($notification);
                } catch (\Throwable $e) {
                    Log::error('AlertService lowStockAlert: failed to notify user ' . $recipient->id . ': ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::error('AlertService lowStockAlert failed: ' . $e->getMessage());
        }
    }

    /**
     * Fire a stale transfer alert when a transfer stays in_transit too long.
     */
    public static function staleTransferAlert(
        string $transferNumber,
        string $fromBranch,
        string $toBranch,
        int    $daysInTransit
    ): void {
        self::notifyAdmins(
            title:   'Stale Transfer Alert',
            message: "Stock Transfer '{$transferNumber}' (From: {$fromBranch} → To: {$toBranch}) has been in transit for {$daysInTransit} day(s) without being received.",
            type:    'stale_transfer_alert',
        );
    }
}
