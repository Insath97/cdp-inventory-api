<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class DatabaseManagementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('role:Super Admin|SUPER ADMIN'),
        ];
    }

    public function overview(): JsonResponse
    {
        try {
            $database = DB::getDatabaseName();
            $version = DB::select('SELECT VERSION() AS v')[0]->v ?? 'unknown';

            $tablesInfo = DB::select(
                'SELECT table_name AS name, engine AS engine, '
                . 'ROUND((data_length + index_length) / 1024 / 1024, 3) AS size_mb, '
                . 'COALESCE(table_rows, 0) AS estimated_rows '
                . 'FROM information_schema.tables '
                . "WHERE table_schema = ? AND table_type = 'BASE TABLE' "
                . 'ORDER BY table_name',
                [$database]
            );

            $tables = [];
            $totalRows = 0;
            $totalSize = 0.0;

            foreach ($tablesInfo as $t) {
                $count = (int) $t->estimated_rows;
                $totalRows += $count;
                $totalSize += (float) $t->size_mb;
                $tables[] = [
                    'name'    => $t->name,
                    'rows'    => $count,
                    'engine'  => $t->engine,
                    'size_mb' => (float) $t->size_mb,
                ];
            }

            // Largest tables first
            usort($tables, fn ($a, $b) => $b['rows'] <=> $a['rows']);

            return response()->json([
                'status' => 'success',
                'message' => 'Database overview fetched successfully',
                'data' => [
                    'database' => $database,
                    'version' => $version,
                    'total_tables' => count($tables),
                    'total_rows' => $totalRows,
                    'total_size_mb' => round($totalSize, 3),
                    'tables' => $tables,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch database overview',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    
    public function backup(): StreamedResponse|JsonResponse
    {
        try {
            $database = DB::getDatabaseName();
            $tableNames = collect(DB::select('SHOW TABLES'))
                ->map(fn ($row) => array_values((array) $row)[0])
                ->all();

            $filename = $database . '_backup_' . now()->format('Ymd_His') . '.sql';
            $pdo = DB::getPdo();

            return response()->streamDownload(function () use ($tableNames, $pdo, $database) {
                $out = fopen('php://output', 'w');
                fwrite($out, "-- CDP Inventory database backup\n");
                fwrite($out, "-- Database: {$database}\n");
                fwrite($out, '-- Generated: ' . now()->toDateTimeString() . "\n\n");
                fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n\n");

                foreach ($tableNames as $table) {
                    $createRow = (array) DB::select("SHOW CREATE TABLE `{$table}`")[0];
                    $createSql = array_values($createRow)[1];

                    fwrite($out, "-- ----------------------------\n");
                    fwrite($out, "-- Table: {$table}\n");
                    fwrite($out, "-- ----------------------------\n");
                    fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");
                    fwrite($out, $createSql . ";\n\n");

                    $insertValues = [];
                    $batchSize = 250;

                    foreach (DB::table($table)->cursor() as $row) {
                        $values = array_map(
                            fn ($v) => is_null($v) ? 'NULL' : $pdo->quote((string) $v),
                            (array) $row
                        );
                        $insertValues[] = "(" . implode(', ', $values) . ")";

                        if (count($insertValues) >= $batchSize) {
                            fwrite($out, "INSERT INTO `{$table}` VALUES " . implode(",\n", $insertValues) . ";\n");
                            $insertValues = [];
                        }
                    }

                    if (! empty($insertValues)) {
                        fwrite($out, "INSERT INTO `{$table}` VALUES " . implode(",\n", $insertValues) . ";\n");
                    }
                    fwrite($out, "\n");
                }

                fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
                fclose($out);
            }, $filename, [
                'Content-Type' => 'application/sql',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate database backup',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    
    public function clearCache(): JsonResponse
    {
        try {
            Artisan::call('optimize:clear');

            return response()->json([
                'status' => 'success',
                'message' => 'Application caches cleared successfully.',
                'data' => ['output' => trim(Artisan::output())],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear caches',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
