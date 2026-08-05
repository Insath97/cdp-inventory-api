<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

trait FileUploadTrait
{
    public function handleFileUpload(
        Request $request,
        string $fieldName,
        ?string $oldPath = null,
        string $module = 'general',
        string $prefix = ''
    ): ?string {
        if (!$request->hasFile($fieldName)) {
            return null;
        }

        // Delete old file if exists
        $this->deleteFile($oldPath);

        $file = $request->file($fieldName);
        $clientExt = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $guessedExt = strtolower($file->guessExtension() ?? '');

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
        $forbidden = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat', 'cmd', 'cgi', 'pl', 'asp', 'aspx', 'jsp'];

        // Determine safe extension
        if (in_array($clientExt, $forbidden, true) || in_array($guessedExt, $forbidden, true)) {
            $extension = 'bin';
        } elseif (in_array($guessedExt, $allowedExtensions, true)) {
            $extension = $guessedExt;
        } elseif (in_array($clientExt, $allowedExtensions, true)) {
            $extension = $clientExt;
        } else {
            $extension = 'bin';
        }

        // Sanitize prefix to prevent path traversal or extension manipulation
        $safePrefix = $prefix ? Str::slug(pathinfo($prefix, PATHINFO_FILENAME)) : Str::random(25);
        $fileName = $safePrefix . '_' . Str::random(8) . '.' . $extension;

        $directory = "uploads/{$module}";
        $filePath = "{$directory}/{$fileName}";

        // Create directory if not exists
        if (!File::exists(public_path($directory))) {
            File::makeDirectory(public_path($directory), 0755, true);
        }

        $file->move(public_path($directory), $fileName);

        return $filePath;
    }

    /**
     * Handle multiple file uploads
     */
    public function handleMultipleFileUpload(
        Request $request,
        string $fieldName,
        array $oldPaths = [],
        string $module = 'general',
        string $prefix = ''
    ): array {
        if (!$request->hasFile($fieldName)) {
            return [];
        }

        $files = $request->file($fieldName);

        // Ensure it's an array
        if (!is_array($files)) {
            $files = [$files];
        }

        $uploadedPaths = [];

        foreach ($files as $index => $file) {
            // Skip if file is not valid
            if (!$file->isValid()) {
                continue;
            }

            $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
            $forbidden = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat', 'cmd', 'cgi', 'pl', 'asp', 'aspx', 'jsp'];
            if (in_array($extension, $forbidden)) {
                $extension = 'bin';
            }

            // Generate unique filename
            $fileName = $prefix
                ? $prefix . '_' . ($index + 1) . '.' . $extension
                : Str::random(25) . '_' . ($index + 1) . '.' . $extension;

            $directory = "uploads/{$module}";
            $filePath = "{$directory}/{$fileName}";

            // Create directory if not exists
            $this->createDirectory($directory);

            $file->move(public_path($directory), $fileName);
            $uploadedPaths[] = $filePath;
        }

        // Delete old files if new ones were uploaded
        if (!empty($uploadedPaths) && !empty($oldPaths)) {
            foreach ($oldPaths as $oldPath) {
                $this->deleteFile($oldPath);
            }
        }

        return $uploadedPaths;
    }

    /**
     * Helper method to create directory
     */
    private function createDirectory(string $directory): void
    {
        if (!File::exists(public_path($directory))) {
            File::makeDirectory(public_path($directory), 0755, true, true);
        }
    }

    /**
     * Delete a single file from both storage and public
     */
    public function deleteFile(?string $path): void
    {
        if ($path) {
            if (File::exists(public_path($path))) {
                File::delete(public_path($path));
            }
            if (File::exists(storage_path("app/public/{$path}"))) {
                File::delete(storage_path("app/public/{$path}"));
            }
        }
    }

    /**
     * Delete multiple files
     */
    public function deleteMultipleFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deleteFile($path);
        }
    }
}
