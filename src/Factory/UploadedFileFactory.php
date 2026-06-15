<?php

declare(strict_types=1);

namespace Waffle\Commons\Http\Factory;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Waffle\Commons\Http\UploadedFile;

final class UploadedFileFactory implements UploadedFileFactoryInterface
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

        // STATE-02: never create a temporary file in the shared system temp dir. If
        // the stream already wraps a real on-disk file, hand its path to UploadedFile so
        // a move can use rename()/move_uploaded_file(); otherwise keep the stream
        // itself — its content is copied straight to the destination on moveTo().
        $meta = $stream->getMetadata('uri');
        $streamOrFile = is_string($meta) && $meta !== '' && file_exists($meta) ? $meta : $stream;

        return new UploadedFile($streamOrFile, (int) $size, $error, $clientFilename, $clientMediaType);
    }
}
