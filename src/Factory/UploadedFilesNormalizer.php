<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use Waffle\Commons\Http\UploadedFile;

/**
 * Normalizes a PHP `$_FILES` super-global structure into a PSR-7 tree of
 * {@see UploadedFileInterface} instances.
 *
 * Extracted from {@see GlobalsFactory} (AXE-2 / ARCH-06) so the recursive
 * `$_FILES` handling — including the nested `<input name="files[]">` layout —
 * is independently unit-testable without driving the whole request factory.
 *
 * Stateless and side-effect-free: it reads the array it is handed and returns a
 * new tree, touching no super-globals of its own.
 */
final class UploadedFilesNormalizer
{
    /**
     * Normalizes a `$_FILES`-shaped array into a (possibly nested) map of
     * uploaded files. An empty input yields an empty array. Each leaf is an
     * {@see UploadedFileInterface}; branches are nested arrays of the same.
     */
    public function normalize(array $files): array
    {
        if ($files === []) {
            return [];
        }
        return $this->normalizeFiles($files);
    }

    /**
     * Recursively normalizes a `$_FILES` branch.
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
                continue;
            }
            if (is_array($value) && array_key_exists('tmp_name', $value)) {
                $normalized[$key] = $this->createUploadedFileFromSpec($value);
                continue;
            }
            if (is_array($value)) {
                $normalized[$key] = $this->normalizeFiles($value);
                continue;
            }
            throw new InvalidArgumentException('Invalid value in $_FILES array.');
        }
        return $normalized;
    }

    /**
     * Creates UploadedFile instances from a normalized $_FILES spec.
     */
    private function createUploadedFileFromSpec(array $spec): UploadedFileInterface|array
    {
        if (is_array($spec['tmp_name'] ?? null)) {
            return $this->normalizeNestedFileSpec($spec);
        }
        $tmpName = (string) ($spec['tmp_name'] ?? '');

        return new UploadedFile(
            $tmpName,
            (int) ($spec['size'] ?? 0),
            (int) ($spec['error'] ?? UPLOAD_ERR_OK),
            array_key_exists('name', $spec) ? (string) $spec['name'] : null,
            array_key_exists('type', $spec) ? (string) $spec['type'] : null,
        );
    }

    /**
     * Handles the nested structure of <input name="files[]">. The per-index spec
     * extraction is delegated to {@see self::nestedSpecAt()} so this method
     * stays a simple loop (Beta-1 hardening: reduce cyclomatic complexity per
     * Roadmap §1.5).
     */
    private function normalizeNestedFileSpec(array $files): array
    {
        $normalized = [];
        $tmpNames = (array) ($files['tmp_name'] ?? []);
        foreach (array_keys($tmpNames) as $key) {
            $normalized[$key] = $this->createUploadedFileFromSpec($this->nestedSpecAt($files, $key));
        }
        return $normalized;
    }

    /**
     * Reads the per-index slice ($key) of a nested PHP $_FILES spec into a flat
     * `array{tmp_name, size, error, name, type}` ready to feed back into
     * {@see self::createUploadedFileFromSpec()}.
     */
    private function nestedSpecAt(array $files, int|string $key): array
    {
        $tmpNames = (array) ($files['tmp_name'] ?? []);

        return [
            'tmp_name' => $tmpNames[$key] ?? null,
            'size' => $this->nestedField($files, 'size', $key, default: 0),
            'error' => $this->nestedField($files, 'error', $key, default: UPLOAD_ERR_OK),
            'name' => $this->nestedField($files, 'name', $key, default: null),
            'type' => $this->nestedField($files, 'type', $key, default: null),
        ];
    }

    /**
     * Reads a single nested field at $key from a $_FILES spec, returning $default
     * when the field is missing or not an array.
     */
    private function nestedField(array $files, string $field, int|string $key, mixed $default): mixed
    {
        $bucket = $files[$field] ?? null;
        if (!is_array($bucket)) {
            return $default;
        }
        return $bucket[$key] ?? $default;
    }
}
