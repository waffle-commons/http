<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Factory;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;
use Waffle\Commons\Http\Factory\UploadedFilesNormalizer;
use Waffle\Commons\Http\UploadedFile;

class UploadedFilesNormalizerTest extends TestCase
{
    private UploadedFilesNormalizer $normalizer;

    #[\Override]
    protected function setUp(): void
    {
        $this->normalizer = new UploadedFilesNormalizer();
    }

    public function testEmptyInputReturnsEmptyArray(): void
    {
        static::assertSame([], $this->normalizer->normalize([]));
    }

    public function testFlatSpecWithAllFields(): void
    {
        $result = $this->normalizer->normalize([
            'avatar' => [
                'tmp_name' => '/tmp/php-avatar',
                'size' => 1234,
                'error' => UPLOAD_ERR_OK,
                'name' => 'photo.png',
                'type' => 'image/png',
            ],
        ]);

        $file = $result['avatar'];
        static::assertInstanceOf(UploadedFileInterface::class, $file);
        static::assertSame('photo.png', $file->getClientFilename());
        static::assertSame('image/png', $file->getClientMediaType());
        static::assertSame(1234, $file->getSize());
        static::assertSame(UPLOAD_ERR_OK, $file->getError());
    }

    public function testFlatSpecWithMissingOptionalFieldsUsesDefaults(): void
    {
        $result = $this->normalizer->normalize([
            'doc' => ['tmp_name' => '/tmp/php-doc'],
        ]);

        $file = $result['doc'];
        static::assertInstanceOf(UploadedFileInterface::class, $file);
        // For a flat spec the name/type keys are absent, so array_key_exists()
        // is false and the client filename/type stay null.
        static::assertNull($file->getClientFilename());
        static::assertNull($file->getClientMediaType());
        // size/error `?? default` branches:
        static::assertSame(0, $file->getSize());
        static::assertSame(UPLOAD_ERR_OK, $file->getError());
    }

    public function testAlreadyNormalizedInstanceIsPassedThrough(): void
    {
        $existing = new UploadedFile('/tmp/php-existing', 0, UPLOAD_ERR_OK, null, null);

        $result = $this->normalizer->normalize(['x' => $existing]);

        static::assertSame($existing, $result['x']);
    }

    public function testNestedGroupRecursesIntoSubTree(): void
    {
        $result = $this->normalizer->normalize([
            'group' => [
                'nested' => ['tmp_name' => '/tmp/php-nested'],
            ],
        ]);

        $group = $result['group'];
        static::assertIsArray($group);
        $nested = $group['nested'];
        static::assertInstanceOf(UploadedFileInterface::class, $nested);
    }

    public function testNestedFileSpecYieldsMultipleFiles(): void
    {
        $result = $this->normalizer->normalize([
            'files' => [
                'tmp_name' => ['/tmp/php-a', '/tmp/php-b'],
                'size' => [11, 22],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'name' => ['a.txt', 'b.txt'],
                'type' => ['text/plain', 'text/csv'],
            ],
        ]);

        $files = $result['files'];
        static::assertIsArray($files);
        static::assertCount(2, $files);

        $first = $files[0];
        $second = $files[1];
        static::assertInstanceOf(UploadedFileInterface::class, $first);
        static::assertInstanceOf(UploadedFileInterface::class, $second);
        static::assertSame('a.txt', $first->getClientFilename());
        static::assertSame('text/csv', $second->getClientMediaType());
        static::assertSame(22, $second->getSize());
    }

    public function testNestedFileSpecWithMissingFieldBucketsUsesDefaults(): void
    {
        // Only tmp_name is present; size/error/name/type buckets are absent, so
        // nestedField() takes its `!is_array($bucket)` default branch. Because
        // nestedSpecAt() still emits the name/type keys (as null), the resulting
        // client filename/type are empty strings, not null.
        $result = $this->normalizer->normalize([
            'files' => [
                'tmp_name' => ['/tmp/php-a'],
            ],
        ]);

        $files = $result['files'];
        static::assertIsArray($files);
        $file = $files[0];
        static::assertInstanceOf(UploadedFileInterface::class, $file);
        static::assertSame('', $file->getClientFilename());
        static::assertSame('', $file->getClientMediaType());
        static::assertSame(0, $file->getSize());
        static::assertSame(UPLOAD_ERR_OK, $file->getError());
    }

    public function testNestedFileSpecWithShortFieldArrayFallsBackPerIndex(): void
    {
        // The 'name' bucket is an array but shorter than 'tmp_name', so the
        // second index hits nestedField()'s `$bucket[$key] ?? $default` fallback.
        $result = $this->normalizer->normalize([
            'files' => [
                'tmp_name' => ['/tmp/php-a', '/tmp/php-b'],
                'name' => ['only-first.txt'],
            ],
        ]);

        $files = $result['files'];
        static::assertIsArray($files);
        $first = $files[0];
        $second = $files[1];
        static::assertInstanceOf(UploadedFileInterface::class, $first);
        static::assertInstanceOf(UploadedFileInterface::class, $second);
        static::assertSame('only-first.txt', $first->getClientFilename());
        static::assertSame('', $second->getClientFilename());
    }

    public function testInvalidScalarValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value in $_FILES array.');

        $this->normalizer->normalize(['bad' => 'not-a-file']);
    }
}
