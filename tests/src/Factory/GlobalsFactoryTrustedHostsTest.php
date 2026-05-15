<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Factory;

use InvalidArgumentException;
use Waffle\Commons\Http\Factory\GlobalsFactory;
use WaffleTests\Commons\Http\AbstractTestCase;

/**
 * Tests for GlobalsFactory trusted hosts functionality.
 */
class GlobalsFactoryTrustedHostsTest extends AbstractTestCase
{
    private array $serverBackup;
    private array $getBackup;
    private array $postBackup;
    private array $cookieBackup;
    private array $filesBackup;

    #[\Override]
    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->cookieBackup = $_COOKIE;
        $this->filesBackup = $_FILES;
    }

    #[\Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_COOKIE = $this->cookieBackup;
        $_FILES = $this->filesBackup;
    }

    private function setGlobals(
        array $server = [],
        array $get = [],
        array $post = [],
        array $cookie = [],
        array $files = [],
    ): void {
        $_SERVER = $server
        + [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
        ];
        $_GET = $get;
        $_POST = $post;
        $_COOKIE = $cookie;
        $_FILES = $files;
    }

    public function testItAcceptsTrustedHost(): void
    {
        $this->setGlobals(server: ['HTTP_HOST' => 'trusted.com']);

        $factory = new GlobalsFactory(trustedHosts: ['trusted.com']);
        $request = $factory->createFromGlobals();

        static::assertSame('trusted.com', $request->getUri()->getHost());
    }

    public function testItAcceptsTrustedHostWithPort(): void
    {
        $this->setGlobals(server: ['HTTP_HOST' => 'trusted.com:8080']);

        $factory = new GlobalsFactory(trustedHosts: ['trusted.com']);
        $request = $factory->createFromGlobals();

        static::assertSame('trusted.com', $request->getUri()->getHost());
        static::assertSame(8080, $request->getUri()->getPort());
    }

    public function testItRejectsUntrustedHost(): void
    {
        $this->setGlobals(server: ['HTTP_HOST' => 'evil.com']);

        $factory = new GlobalsFactory(trustedHosts: ['trusted.com', 'localhost']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Untrusted Host "evil.com"');

        $factory->createFromGlobals();
    }

    public function testItRejectsMissingHostHeaderWhenTrustedHostsEnabled(): void
    {
        $server = ['REQUEST_METHOD' => 'GET'];
        $_SERVER = $server;

        $factory = new GlobalsFactory(trustedHosts: ['trusted.com']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Host header');

        $factory->createFromGlobals();
    }

    public function testItAllowsAnyHostIfTrustedListIsEmpty(): void
    {
        $this->setGlobals(server: ['HTTP_HOST' => 'anything.com']);

        $factory = new GlobalsFactory(trustedHosts: []);
        $request = $factory->createFromGlobals();

        static::assertSame('anything.com', $request->getUri()->getHost());
    }
}
