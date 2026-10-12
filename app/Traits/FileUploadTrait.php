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

            // Generate unique filename (slug the prefix like the single-upload
            // variant so a caller-supplied prefix can never traverse paths)
            $safePrefix = $prefix ? Str::slug(pathinfo($prefix, PATHINFO_FILENAME)) : '';
            $fileName = $safePrefix
                ? $safePrefix . '_' . ($index + 1) . '_' . Str::random(8) . '.' . $extension
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
     * Resolve a stored upload path to a real file inside the managed uploads
     * directory under the given root, or null if it points anywhere else.
     * Stored paths come back from the database, where a request could have
     * planted a traversal string — never trust them.
     */
    private function resolveManagedUpload(string $root, string $path): ?string
    {
        $path = str_replace('\\', '/', $path);

        if (!str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
            return null;
        }

        $real = realpath($root . DIRECTORY_SEPARATOR . $path);
        $uploadsRoot = realpath($root . DIRECTORY_SEPARATOR . 'uploads');

        if ($real === false || $uploadsRoot === false) {
            return null;
        }

        if (!str_starts_with($real, $uploadsRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return is_file($real) ? $real : null;
    }

    /**
     * Delete a single file from both storage and public.
     * Only files inside the managed uploads directories are ever deleted.
     */
    public function deleteFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        foreach ([public_path(), storage_path('app/public')] as $root) {
            $target = $this->resolveManagedUpload($root, $path);
            if ($target !== null) {
                File::delete($target);
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
