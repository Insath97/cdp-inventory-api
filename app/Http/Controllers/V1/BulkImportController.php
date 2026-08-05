<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\BulkImportService;
use App\Jobs\ImportCsvJob;
use Illuminate\Routing\Controllers\Middleware;

class BulkImportController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('role:Super Admin|SUPER ADMIN'),
        ];
    }
    public function __construct(
        protected BulkImportService $importService
    ) {
    }

    /**
     * Display a listing of the resource (exposed master-data tables).
     */
    public function index(): JsonResponse
    {
        $list = collect($this->importService->tables())->map(function ($config, $key) {
            return [
                'table' => $key,
                'label' => $config['label'],
                'note' => $config['note'] ?? null,
                'required' => $config['required'] ?? [],
                'columns' => $config['columns'],
            ];
        })->values()->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Importable tables fetched successfully',
            'data' => $list,
        ]);
    }

    /**
     * Download a CSV template (header row) for a given table.
     */
    public function template(string $table): StreamedResponse|JsonResponse
    {
        $config = $this->importService->tables()[$table] ?? null;

        if (!$config) {
            return response()->json(['status' => 'error', 'message' => 'Unknown table'], 404);
        }

        $columns = $config['columns'];
        $filename = $table . '_template.csv';

        return response()->streamDownload(function () use ($columns) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens it cleanly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import an uploaded CSV/TXT file into the given table (Queued).
     */
    public function import(Request $request, string $table): JsonResponse
    {
        $config = $this->importService->tables()[$table] ?? null;

        if (!$config) {
            return response()->json(['status' => 'error', 'message' => 'Unknown table'], 404);
        }

        $request->validate(['file' => 'required|file|max:10240']); // 10MB

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only CSV or TXT files are supported.',
            ], 422);
        }

        // Store the file temporarily inside the local storage path
        $path = $file->store('imports');
        if (!$path) {
            return response()->json(['status' => 'error', 'message' => 'Failed to store uploaded file.'], 500);
        }

        $userId = $request->user()?->id ?? optional(\App\Models\User::first())->id;

        // Dispatch background queue job
        ImportCsvJob::dispatch($path, $table, $userId);

        return response()->json([
            'status' => 'success',
            'message' => 'The file has been uploaded and queued for importing. You will receive an in-app and email notification when it completes.',
            'data' => [
                'table' => $table,
                'status' => 'queued',
            ],
        ], 202);
    }
}
