<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * FileUploadService — Secure image inspection, hashing, and storage
 *
 * Usage:
 *   $result = FileUploadService::handleUpload($_FILES['image'], 'items');
 *   // $result = ['ok' => bool, 'path' => string|null, 'filename' => string|null, 'error' => string|null]
 */

require_once __DIR__ . '/../config/config.php';

class FileUploadService
{
    /** Allowed real MIME types (verified via byte signature, not Content-Type header) */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Map MIME → safe file extension */
    private const MIME_EXT_MAP = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Validate and store a single uploaded image file.
     *
     * @param array  $file     Entry from $_FILES (e.g. $_FILES['image'])
     * @param string $subdir   Subdirectory under uploads/ ('items' or 'proofs')
     * @return array           ['ok' => bool, 'path' => string|null, 'filename' => string|null, 'error' => string|null]
     *                         'path' is the relative web path (e.g. 'uploads/items/abc123.jpg')
     *                         'filename' is the bare filename (e.g. 'abc123.jpg')
     */
    public static function handleUpload(array $file, string $subdir = 'items'): array
    {
        // 1. Check that a file was actually submitted
        if (empty($file) || !isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'path' => null, 'filename' => null, 'error' => null]; // Optional upload — not an error
        }

        // 2. Check PHP upload error codes
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = self::uploadErrorMessage((int) $file['error']);
            return ['ok' => false, 'path' => null, 'filename' => null, 'error' => $msg];
        }

        // 3. Confirm the file was actually uploaded via HTTP POST
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'path' => null, 'filename' => null, 'error' => 'Invalid upload source detected.'];
        }

        // 4. Enforce file size limit
        if ($file['size'] > MAX_UPLOAD_SIZE_BYTES) {
            $maxMb = round(MAX_UPLOAD_SIZE_BYTES / 1024 / 1024, 1);
            return ['ok' => false, 'path' => null, 'filename' => null, 'error' => "File exceeds the maximum allowed size of {$maxMb} MB."];
        }

        // 5. Verify real MIME type via finfo byte-signature inspection (do NOT trust browser Content-Type)
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($realMime, self::ALLOWED_MIMES, true)) {
            return [
                'ok'       => false,
                'path'     => null,
                'filename' => null,
                'error'    => 'Only JPEG, PNG, and WebP images are accepted. Executable files are strictly prohibited.',
            ];
        }

        // 6. Double-check: getimagesize() must succeed (rejects disguised non-images)
        if (@getimagesize($file['tmp_name']) === false) {
            return ['ok' => false, 'path' => null, 'filename' => null, 'error' => 'Uploaded file does not appear to be a valid image.'];
        }

        // 7. Build a safe, random filename — original filename is never used
        $ext         = self::MIME_EXT_MAP[$realMime];
        $randomName  = bin2hex(random_bytes(16)) . '.' . $ext;

        // 8. Resolve target directory and ensure it is writable
        $targetDir = UPLOADS_PATH . DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                return ['ok' => false, 'path' => null, 'filename' => null, 'error' => 'Upload directory could not be created.'];
            }
        }

        if (!is_writable($targetDir)) {
            return ['ok' => false, 'path' => null, 'filename' => null, 'error' => 'Upload directory is not writable. Check server permissions.'];
        }

        // 9. Move the temp file to permanent storage
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $randomName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['ok' => false, 'path' => null, 'filename' => null, 'error' => 'Failed to save the uploaded file. Please try again.'];
        }

        // 10. Return the relative web path and bare filename
        $relativePath = 'uploads/' . $subdir . '/' . $randomName;
        return [
            'ok'       => true,
            'path'     => $relativePath,
            'filename' => $randomName,
            'error'    => null,
        ];
    }

    /**
     * Delete a previously uploaded file by its relative path or filename.
     *
     * @param string|null $relativePath e.g. 'uploads/items/abc123.jpg' or 'abc123.jpg'
     * @param string      $subdir       Subdirectory under uploads/ ('items' by default)
     */
    public static function deleteFile(?string $relativePath, string $subdir = 'items'): void
    {
        if (empty($relativePath)) {
            return;
        }

        $normalized = str_replace('\\', '/', $relativePath);
        if (!str_starts_with($normalized, 'uploads/')) {
            $normalized = 'uploads/' . $subdir . '/' . ltrim($normalized, '/');
        }

        // Security: only allow deletion of files within the uploads directory
        $fullPath = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $realPath = realpath($fullPath);
        $uploadsReal = realpath(UPLOADS_PATH);

        if ($realPath && $uploadsReal && str_starts_with($realPath, $uploadsReal) && is_file($realPath)) {
            @unlink($realPath);
        }
    }

    /**
     * Translate PHP upload error codes to user-friendly messages.
     */
    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the maximum allowed size (2 MB).',
            UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary storage is missing. Please contact the administrator.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded file. Check directory permissions.',
            UPLOAD_ERR_EXTENSION  => 'A server extension blocked the upload.',
            default               => 'An unexpected upload error occurred (code: ' . $code . ').',
        };
    }
}
