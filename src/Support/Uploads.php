<?php
declare(strict_types=1);

namespace Cms\Support;

use RuntimeException;

final class Uploads
{
    private const BASE_DIRECTORY = 'public/uploads';

    private const DEFAULT_DATE_PATH = ['YYYY', 'MM'];

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    private const MAX_FILE_BYTES = 8_388_608;

    public static function publicDirectoryPath(): string
    {
        return dirname(__DIR__, 2) . '/' . self::BASE_DIRECTORY;
    }

    public static function storeImage(?array $file, array $options = []): string
    {
        if (!is_array($file) || $file === []) {
            throw new RuntimeException('No image upload was provided.');
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('No image upload was provided.');
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The image upload could not be completed.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_file($tmpName)) {
            throw new RuntimeException('The uploaded image could not be read.');
        }

        $fileSize = (int) ($file['size'] ?? 0);

        if ($fileSize <= 0) {
            throw new RuntimeException('The uploaded image is empty.');
        }

        if ($fileSize > self::MAX_FILE_BYTES) {
            throw new RuntimeException('Images must be 8 MB or smaller.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpName);
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;

        if ($extension === null) {
            throw new RuntimeException('Only JPEG, PNG, GIF, WebP, and AVIF images are supported.');
        }

        $datePath = self::datePath($options);
        $targetDirectory = self::publicDirectoryPath();

        if ($datePath !== '') {
            $targetDirectory .= '/' . $datePath;
        }

        if (!is_dir($targetDirectory) && !mkdir($concurrentDirectory = $targetDirectory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('The uploads directory could not be created.');
        }

        $originalName = (string) ($file['name'] ?? 'image');
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = self::slugify($baseName);
        $slug = $slug !== '' ? $slug : 'image';
        $fileName = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetPath = $targetDirectory . '/' . $fileName;

        if (!self::moveUploadedFile($tmpName, $targetPath)) {
            throw new RuntimeException('The uploaded image could not be stored.');
        }

        return '/uploads' . ($datePath !== '' ? '/' . $datePath : '') . '/' . $fileName;
    }

    private static function datePath(array $options): string
    {
        $segments = [];

        foreach (self::normalizedDatePath($options) as $segment) {
            if ($segment === 'YYYY') {
                $segments[] = gmdate('Y');
                continue;
            }

            if ($segment === 'MM') {
                $segments[] = gmdate('m');
            }
        }

        return implode('/', $segments);
    }

    private static function normalizedDatePath(array $options): array
    {
        $configuredPath = $options['datePath'] ?? self::DEFAULT_DATE_PATH;

        if (!is_array($configuredPath)) {
            return self::DEFAULT_DATE_PATH;
        }

        $normalized = [];

        foreach ($configuredPath as $segment) {
            $rawSegment = trim((string) $segment);

            if ($rawSegment === '') {
                continue;
            }

            if (strtoupper($rawSegment) === 'YYYY' || $rawSegment === 'Y') {
                $normalized[] = 'YYYY';
                continue;
            }

            if (strtoupper($rawSegment) === 'MM' || $rawSegment === 'm') {
                $normalized[] = 'MM';
            }
        }

        return $normalized;
    }

    private static function moveUploadedFile(string $tmpName, string $targetPath): bool
    {
        if (is_uploaded_file($tmpName)) {
            return move_uploaded_file($tmpName, $targetPath);
        }

        if (@rename($tmpName, $targetPath)) {
            return true;
        }

        if (@copy($tmpName, $targetPath)) {
            @unlink($tmpName);

            return true;
        }

        return false;
    }

    private static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

        return trim($value, '-');
    }
}