<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

final class FileSecurity
{
    /**
     * Allowed MIME types for uploaded documents (KTP, NPWP, BAST, Payment Proof).
     */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Allowed file extensions.
     */
    public const ALLOWED_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    /**
     * Maximum allowed file size in kilobytes (5 MB).
     */
    public const MAX_FILE_SIZE_KB = 5120;

    /**
     * Get the standard Laravel validation rules array for secure file upload.
     *
     * @param  bool  $required  Whether the file is required
     * @return array<int, string>
     */
    public static function validationRules(bool $required = true): array
    {
        $rules = [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
            'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            'max:'.self::MAX_FILE_SIZE_KB,
        ];

        return $rules;
    }

    /**
     * Validate an UploadedFile object against security standards.
     *
     * @return array{valid: bool, error: string|null}
     */
    public static function validate(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            return ['valid' => false, 'error' => 'File upload error or corrupted file.'];
        }

        $sizeKb = $file->getSize() / 1024;
        if ($sizeKb > self::MAX_FILE_SIZE_KB) {
            return ['valid' => false, 'error' => sprintf('File size exceeds the %d KB limit.', self::MAX_FILE_SIZE_KB)];
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            return ['valid' => false, 'error' => sprintf('MIME type "%s" is not allowed.', $mime)];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return ['valid' => false, 'error' => sprintf('Extension ".%s" is not permitted.', $extension)];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Generate an obfuscated, collision-resistant storage path.
     *
     * @param  string  $category  Folder category (e.g. 'identity', 'payments', 'bast')
     * @param  string  $extension  Original extension
     */
    public static function generateSecurePath(string $category, string $extension): string
    {
        $hash = bin2hex(random_bytes(20));
        $dateFolder = now()->format('Y/m');

        return sprintf('%s/%s/%s.%s', $category, $dateFolder, $hash, strtolower($extension));
    }
}
