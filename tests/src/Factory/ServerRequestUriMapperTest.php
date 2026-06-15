<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Factory;

use Waffle\Commons\Http\Factory\ServerRequestUriMapper;
use WaffleTests\Commons\Http\AbstractTestCase;

class ServerRequestUriMapperTest extends AbstractTestCase
{
    private ServerRequestUriMapper $mapper;

    #[\Override]
    protected function setUp(): void
    {
        $this->mapper = new ServerRequestUriMapper();
    }

    public function testBuildsHttpUriWithPathAndQuery(): void
    {
        $uri = $this->mapper->map([
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/path?x=1',
            'QUERY_STRING' => 'x=1',
        ]);

        static::assertSame('http', $uri->getScheme());
        static::assertSame('example.com', $uri->getHost());
        static::assertSame('/path', $uri->getPath());
        static::assertSame('x=1', $uri->getQuery());
    }

    public function testDetectsHttpsFromOnFlag(): void
    {
        $uri = $this->mapper->map(['HTTP_HOST' => 'example.com', 'HTTPS' => 'on', 'REQUEST_URI' => '/']);

        static::assertSame('https', $uri->getScheme());
    }

    public function testDetectsHttpsFromNumericFlag(): void
    {
        $uri = $this->mapper->map(['HTTP_HOST' => 'h', 'HTTPS' => '1', 'REQUEST_URI' => '/']);

        static::assertSame('https', $uri->getScheme());
    }

    public function testSplitsExplicitPortFromHost(): void
    {
        $uri = $this->mapper->map(['HTTP_HOST' => 'example.com:8080', 'REQUEST_URI' => '/']);

        static::assertSame('example.com', $uri->getHost());
        static::assertSame(8080, $uri->getPort());
    }

    public function testFallsBackToServerPort(): void
    {
        $uri = $this->mapper->map(['HTTP_HOST' => 'h', 'SERVER_PORT' => '9000', 'REQUEST_URI' => '/']);

        static::assertSame(9000, $uri->getPort());
    }

    public function testFallsBackToServerNameWhenHostMissing(): void
    {
        $uri = $this->mapper->map(['SERVER_NAME' => 'fallback.local', 'REQUEST_URI' => '/']);

        static::assertSame('fallback.local', $uri->getHost());
    }

    public function testBuildsUserInfoFromBasicAuthCredentials(): void
    {
        $uri = $this->mapper->map([
            'HTTP_HOST' => 'h',
            'PHP_AUTH_USER' => 'alice',
            'PHP_AUTH_PW' => 'secret',
            'REQUEST_URI' => '/',
        ]);

        static::assertSame('alice:secret', $uri->getUserInfo());
    }

    public function testBuildsUserInfoWithoutPassword(): void
    {
        $uri = $this->mapper->map([
            'HTTP_HOST' => 'h',
            'PHP_AUTH_USER' => 'bob',
            'REQUEST_URI' => '/',
        ]);

        static::assertSame('bob', $uri->getUserInfo());
    }

    public function testDefaultsToLocalhostRootWhenServerIsEmpty(): void
    {
        $uri = $this->mapper->map([]);

        static::assertSame('http', $uri->getScheme());
        static::assertSame('localhost', $uri->getHost());
        static::assertSame('/', $uri->getPath());
    }
}
