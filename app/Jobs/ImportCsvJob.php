<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\BulkImportService;
use App\Models\User;
use App\Notifications\InventoryAlertNotification;

class ImportCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Delete the job if its models no longer exist.
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $filePath,
        protected string $table,
        protected int $userId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(BulkImportService $importService): void
    {
        $absolutePath = Storage::path($this->filePath);

        try {
            Log::info("Starting background import of CSV.", [
                'file' => $this->filePath,
                'table' => $this->table,
                'user_id' => $this->userId,
            ]);

            if (!Storage::exists($this->filePath)) {
                throw new \Exception("Temporary CSV import file does not exist at: {$this->filePath}");
            }

            $result = $importService->execute($absolutePath, $this->table, $this->userId);

            Log::info("Background import of CSV completed successfully.", [
                'table' => $this->table,
                'imported' => $result['imported'],
                'failed' => $result['failed'] ?? 0,
            ]);

            $this->notifyUser([
                'success' => true,
                'imported' => $result['imported'],
                'failed' => $result['failed'] ?? 0,
                'errors' => $result['errors'] ?? [],
            ]);

        } catch (\Throwable $th) {
            Log::error("Background import of CSV failed.", [
                'table' => $this->table,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            $this->notifyUser([
                'success' => false,
                'error' => $th->getMessage(),
            ]);
        } finally {
            // Clean up temporary file
            if (Storage::exists($this->filePath)) {
                Storage::delete($this->filePath);
            }
        }
    }

    /**
     * Send in-app & email notification to the user.
     */
    protected function notifyUser(array $details): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        $tableName = ucwords(str_replace('_', ' ', $this->table));

        if ($details['success']) {
            $msg = "Your import of {$tableName} has completed successfully. Imported: {$details['imported']} records.";
            if ($details['failed'] > 0) {
                $msg .= " Failed: {$details['failed']} records. First 50 error messages are stored in the system logs.";
            }

            $user->notify(new InventoryAlertNotification([
                'title' => "Bulk Import Finished: {$tableName}",
                'message' => $msg,
                'type' => 'bulk_import_completed',
                'module' => 'bulk-upload',
                'priority' => 'high',
                'url' => '/bulk-upload',
            ]));
        } else {
            $user->notify(new InventoryAlertNotification([
                'title' => "Bulk Import Failed: {$tableName}",
                'message' => "Your import of {$tableName} failed with error: {$details['error']}",
                'type' => 'bulk_import_failed',
                'module' => 'bulk-upload',
                'priority' => 'high',
                'url' => '/bulk-upload',
            ]));
        }
    }
}
