<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Factory;

use Waffle\Commons\Http\Factory\ServerRequestHeadersMapper;
use WaffleTests\Commons\Http\AbstractTestCase;

class ServerRequestHeadersMapperTest extends AbstractTestCase
{
    private ServerRequestHeadersMapper $mapper;

    #[\Override]
    protected function setUp(): void
    {
        $this->mapper = new ServerRequestHeadersMapper();
    }

    public function testMapsHttpPrefixedAndContentHeaders(): void
    {
        $headers = $this->mapper->map([
            'HTTP_HOST' => 'example.test',
            'HTTP_X_CUSTOM' => 'value',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '42',
            'REQUEST_METHOD' => 'GET', // not a header → must be skipped
        ]);

        static::assertSame('example.test', $headers['host']);
        static::assertSame('value', $headers['x-custom']);
        static::assertSame('application/json', $headers['content-type']);
        static::assertSame('42', $headers['content-length']);
        static::assertArrayNotHasKey('request-method', $headers);
    }

    public function testExistingAuthorizationHeaderTakesPrecedence(): void
    {
        $headers = $this->mapper->map([
            'HTTP_AUTHORIZATION' => 'Bearer token',
            'PHP_AUTH_USER' => 'ignored',
        ]);

        static::assertSame('Bearer token', $headers['authorization']);
    }

    public function testResolvesRedirectAuthorization(): void
    {
        $headers = $this->mapper->map(['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirected']);

        static::assertSame('Bearer redirected', $headers['authorization']);
    }

    public function testSynthesisesBasicAuthorizationFromPhpAuthUser(): void
    {
        $headers = $this->mapper->map([
            'PHP_AUTH_USER' => 'alice',
            'PHP_AUTH_PW' => 'secret',
        ]);

        static::assertSame('Basic ' . base64_encode('alice:secret'), $headers['authorization']);
    }

    public function testResolvesDigestAuthorization(): void
    {
        $headers = $this->mapper->map(['PHP_AUTH_DIGEST' => 'Digest username="bob"']);

        static::assertSame('Digest username="bob"', $headers['authorization']);
    }
}
