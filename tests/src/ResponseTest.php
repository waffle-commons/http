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

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getReasonPhrase());
        $this->assertSame('1.1', $response->getProtocolVersion());
        $this->assertSame([], $response->getHeaders());
        $this->assertInstanceOf(Stream::class, $response->getBody());
        $this->assertSame('', (string) $response->getBody());
    }

    public function testConstructorWithParameters(): void
    {
        $stream = $this->createStream('Hello');
        $headers = ['X-Test' => 'Waffle'];
        $response = new Response(404, $headers, $stream, '2.0', 'Not Found Custom');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found Custom', $response->getReasonPhrase());
        $this->assertSame('2.0', $response->getProtocolVersion());
        $this->assertSame(['X-Test' => ['Waffle']], $response->getHeaders());
        $this->assertSame($stream, $response->getBody());
    }

    public function testConstructorCreatesStreamForStringBody(): void
    {
        $response = new Response(200, [], 'Hello World');
        $this->assertInstanceOf(Stream::class, $response->getBody());
        $this->assertSame('Hello World', (string) $response->getBody());
    }

    public function testConstructorAcceptsResourceBody(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Resource Content');

        $response = new Response(200, [], $resource);

        $this->assertInstanceOf(Stream::class, $response->getBody());
        $this->assertSame('Resource Content', (string) $response->getBody());
    }

    public function testConstructorAcceptsStreamInterfaceBody(): void
    {
        $stream = $this->createStream('Stream Content');
        $response = new Response(200, [], $stream);

        // Should use the exact same instance
        $this->assertSame($stream, $response->getBody());
    }

    public function testConstructorThrowsExceptionForInvalidBodyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid body type');

        new Response(200, [], 12345); // Int is invalid
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

        $this->assertNotSame($r1, $r2);
        $this->assertSame(200, $r1->getStatusCode());
        $this->assertSame('OK', $r1->getReasonPhrase());

        $this->assertSame(404, $r2->getStatusCode());
        $this->assertSame('Not Found', $r2->getReasonPhrase());

        $this->assertSame(500, $r3->getStatusCode());
        $this->assertSame('Server Error', $r3->getReasonPhrase());
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

        $this->assertTrue($response->hasHeader('content-type'));
        $this->assertSame(['application/json'], $response->getHeader('content-type'));
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
    }

    public function testImmutabilityWithHeader(): void
    {
        $r1 = new Response();
        $r2 = $r1->withHeader('X-Test', 'Value1');

        $this->assertNotSame($r1, $r2);
        $this->assertFalse($r1->hasHeader('X-Test'));
        $this->assertTrue($r2->hasHeader('X-Test'));
        $this->assertSame('Value1', $r2->getHeaderLine('X-Test'));
    }

    public function testWithAddedHeader(): void
    {
        $r1 = new Response(200, ['X-Foo' => 'Bar']);
        $r2 = $r1->withAddedHeader('X-Foo', 'Baz');

        $this->assertNotSame($r1, $r2);
        $this->assertSame(['Bar'], $r1->getHeader('X-Foo'));
        $this->assertSame(['Bar', 'Baz'], $r2->getHeader('X-Foo'));
    }

    public function testWithoutHeader(): void
    {
        $r1 = new Response(200, ['X-Foo' => 'Bar', 'X-Test' => 'Value']);
        $r2 = $r1->withoutHeader('x-foo'); // case-insensitive

        $this->assertNotSame($r1, $r2);
        $this->assertTrue($r1->hasHeader('X-Foo'));
        $this->assertFalse($r2->hasHeader('X-Foo'));
        $this->assertTrue($r2->hasHeader('X-Test')); // Other header remains
    }

    public function testWithBody(): void
    {
        $s1 = $this->createStream('Body 1');
        $s2 = $this->createStream('Body 2');
        $r1 = new Response(200, [], $s1);
        $r2 = $r1->withBody($s2);

        $this->assertNotSame($r1, $r2);
        $this->assertSame($s1, $r1->getBody());
        $this->assertSame($s2, $r2->getBody());
    }
}
