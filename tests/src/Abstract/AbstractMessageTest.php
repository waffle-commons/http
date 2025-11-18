<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Abstract;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Waffle\Commons\Http\Abstract\AbstractMessage;
use Waffle\Commons\Http\Response;
use WaffleTests\Commons\Http\AbstractTestCase;

class AbstractMessageTest extends AbstractTestCase
{
    private $message;

    protected function setUp(): void
    {
        // Use an anonymous class to test the abstract class logic
        $this->message = new class extends AbstractMessage {
            public function __construct()
            {
                $this->body = new \Waffle\Commons\Http\Stream(fopen('php://temp', 'r+'));
            }
        };
    }

    public function testWithProtocolVersion(): void
    {
        $new = $this->message->withProtocolVersion('2.0');

        $this->assertNotSame($this->message, $new);
        $this->assertSame('1.1', $this->message->getProtocolVersion());
        $this->assertSame('2.0', $new->getProtocolVersion());
    }

    public function testWithProtocolVersionReturnsSameInstanceIfUnchanged(): void
    {
        $new = $this->message->withProtocolVersion('1.1');
        $this->assertSame($this->message, $new);
    }

    public function testWithProtocolVersionThrowsExceptionForInvalidVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported protocol version "9.9".');

        $this->message->withProtocolVersion('9.9');
    }

    public function testWithHeaderThrowsExceptionForInvalidHeaderName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header name "Invalid Name@".');

        $this->message->withHeader('Invalid Name@', 'value');
    }

    public function testWithHeaderThrowsExceptionForInvalidHeaderValueType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header value must be a string or array of strings.');

        $this->message->withHeader('X-Test', new \stdClass());
    }

    public function testWithHeaderThrowsExceptionForInvalidHeaderValueCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->message->withHeader('X-Test', "Value\r\nInjection");
    }

    public function testWithHeaderThrowsExceptionForEmptyArrayValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header value must not be an empty array.');

        $this->message->withHeader('X-Test', []);
    }

    public function testGetHeaderReturnsEmptyArrayForNonExistentHeader(): void
    {
        $this->assertSame([], $this->message->getHeader('Non-Existent'));
    }

    public function testHasHeaderIsCaseInsensitive(): void
    {
        $new = $this->message->withHeader('Content-Type', 'application/json');

        $this->assertTrue($new->hasHeader('content-type'));
        $this->assertTrue($new->hasHeader('CONTENT-TYPE'));
    }

    public function testWithAddedHeaderMergesValues(): void
    {
        $msg = $this->message->withHeader('X-Foo', 'Bar');
        $msg2 = $msg->withAddedHeader('X-Foo', 'Baz');

        $this->assertSame(['Bar', 'Baz'], $msg2->getHeader('X-Foo'));
        $this->assertSame('Bar, Baz', $msg2->getHeaderLine('X-Foo'));
    }

    public function testWithBodyReturnsClone(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $new = $this->message->withBody($stream);

        $this->assertNotSame($this->message, $new);
        $this->assertSame($stream, $new->getBody());
    }

    public function testWithBodyReturnsSameInstanceIfBodyUnchanged(): void
    {
        $stream = $this->message->getBody();
        $new = $this->message->withBody($stream);

        $this->assertSame($this->message, $new);
    }

    // This test covers protected normalizeHeaders via simulating Response constructor logic
    public function testNormalizeHeadersThrowsExceptionForNonStringKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header name must be a string');

        new Response(200, [0 => 'Invalid Key']);
    }
}
