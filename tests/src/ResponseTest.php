<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use InvalidArgumentException;
use Waffle\Commons\Http\Response;
use Waffle\Commons\Http\Stream;

class ResponseTest extends AbstractTestCase
{
    public function testConstructorDefaults(): void
    {
        $response = new Response();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('OK', $response->getReasonPhrase());
        static::assertSame('1.1', $response->getProtocolVersion());
        static::assertSame([], $response->getHeaders());
        static::assertInstanceOf(Stream::class, $response->getBody());
        static::assertSame('', (string) $response->getBody());
    }

    public function testConstructorWithParameters(): void
    {
        $stream = $this->createStream('Hello');
        $headers = ['X-Test' => 'Waffle'];
        $response = new Response(404, $headers, $stream, '2.0', 'Not Found Custom');

        static::assertSame(404, $response->getStatusCode());
        static::assertSame('Not Found Custom', $response->getReasonPhrase());
        static::assertSame('2.0', $response->getProtocolVersion());
        static::assertSame(['X-Test' => ['Waffle']], $response->getHeaders());
        static::assertSame($stream, $response->getBody());
    }

    public function testConstructorCreatesStreamForStringBody(): void
    {
        $response = new Response(200, [], 'Hello World');
        static::assertInstanceOf(Stream::class, $response->getBody());
        static::assertSame('Hello World', (string) $response->getBody());
    }

    public function testConstructorAcceptsResourceBody(): void
    {
        $resource = fopen(filename: 'php://memory', mode: 'r+');
        static::assertIsResource($resource);
        fwrite(stream: $resource, data: 'Resource Content');

        $response = new Response(200, [], $resource);

        static::assertInstanceOf(Stream::class, $response->getBody());
        static::assertSame('Resource Content', (string) $response->getBody());
    }

    public function testConstructorAcceptsStreamInterfaceBody(): void
    {
        $stream = $this->createStream('Stream Content');
        $response = new Response(200, [], $stream);

        // Should use the exact same instance
        static::assertSame($stream, $response->getBody());
    }

    public function testConstructorThrowsExceptionForInvalidBodyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid body type');

        new Response(200, [], 12_345); // @mago-ignore invalid-argument // Int is invalid
    }

    public function testConstructorThrowsExceptionForInvalidStatusCodeLow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Response(99);
    }

    public function testConstructorThrowsExceptionForInvalidStatusCodeHigh(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Response(600);
    }

    public function testWithStatus(): void
    {
        $r1 = new Response();
        $r2 = $r1->withStatus(404);
        $r3 = $r2->withStatus(500, 'Server Error');

        static::assertNotSame($r1, $r2);
        static::assertSame(200, $r1->getStatusCode());
        static::assertSame('OK', $r1->getReasonPhrase());

        static::assertSame(404, $r2->getStatusCode());
        static::assertSame('Not Found', $r2->getReasonPhrase());

        static::assertSame(500, $r3->getStatusCode());
        static::assertSame('Server Error', $r3->getReasonPhrase());
    }

    public function testWithStatusThrowsExceptionForInvalidCode(): void
    {
        $response = new Response();
        $this->expectException(InvalidArgumentException::class);
        $response->withStatus(999);
    }

    public function testHeaderMethodsAreCaseInsensitive(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json']);

        static::assertTrue($response->hasHeader('content-type'));
        static::assertSame(['application/json'], $response->getHeader('content-type'));
        static::assertSame('application/json', $response->getHeaderLine('content-type'));
    }

    public function testImmutabilityWithHeader(): void
    {
        $r1 = new Response();
        $r2 = $r1->withHeader('X-Test', 'Value1');

        static::assertNotSame($r1, $r2);
        static::assertFalse($r1->hasHeader('X-Test'));
        static::assertTrue($r2->hasHeader('X-Test'));
        static::assertSame('Value1', $r2->getHeaderLine('X-Test'));
    }

    public function testWithAddedHeader(): void
    {
        $r1 = new Response(200, ['X-Foo' => 'Bar']);
        $r2 = $r1->withAddedHeader('X-Foo', 'Baz');

        static::assertNotSame($r1, $r2);
        static::assertSame(['Bar'], $r1->getHeader('X-Foo'));
        static::assertSame(['Bar', 'Baz'], $r2->getHeader('X-Foo'));
    }

    public function testWithoutHeader(): void
    {
        $r1 = new Response(200, ['X-Foo' => 'Bar', 'X-Test' => 'Value']);
        $r2 = $r1->withoutHeader('x-foo'); // case-insensitive

        static::assertNotSame($r1, $r2);
        static::assertTrue($r1->hasHeader('X-Foo'));
        static::assertFalse($r2->hasHeader('X-Foo'));
        static::assertTrue($r2->hasHeader('X-Test')); // Other header remains
    }

    public function testWithBody(): void
    {
        $s1 = $this->createStream('Body 1');
        $s2 = $this->createStream('Body 2');
        $r1 = new Response(200, [], $s1);
        $r2 = $r1->withBody($s2);

        static::assertNotSame($r1, $r2);
        static::assertSame($s1, $r1->getBody());
        static::assertSame($s2, $r2->getBody());
    }
}
