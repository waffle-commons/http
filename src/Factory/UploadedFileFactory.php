<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Waffle\Commons\Http\UploadedFile;

class UploadedFileFactory implements UploadedFileFactoryInterface
{
    #[\Override]
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ): UploadedFileInterface {
        if ($size === null) {
            $size = $stream->getSize();
        }

        // We need a temporary file path for UploadedFile because its constructor expects one
        // This is a slight limitation of relying on native $_FILES structure which uses paths
        $meta = $stream->getMetadata('uri');

        // If stream is a real file, use its path
        if (is_string($meta) && file_exists($meta)) {
            $path = $meta;
        } else {
            // Otherwise, copy stream content to a temp file
            $path = tempnam(sys_get_temp_dir(), 'waffle_upload_factory');
            if ($path === false) {
                throw new \RuntimeException('Unable to create temporary file for UploadedFile.');
            }
            file_put_contents($path, (string) $stream);
        }

        return new UploadedFile($path, (int) $size, $error, $clientFilename, $clientMediaType);
    }
}
