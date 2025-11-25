<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http\Factory;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Waffle\Commons\Http\Factory\RequestFactory;
use Waffle\Commons\Http\Uri;
use WaffleTests\Commons\Http\AbstractTestCase;

class RequestFactoryTest extends AbstractTestCase
{
    public function testImplementsPsrInterface(): void
    {
        $factory = new RequestFactory();
        static::assertInstanceOf(RequestFactoryInterface::class, $factory);
    }

    public function testCreateRequestWithStringUri(): void
    {
        $factory = new RequestFactory();
        $request = $factory->createRequest('GET', 'https://example.com/api');

        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('GET', $request->getMethod());
        static::assertSame('https', $request->getUri()->getScheme());
        static::assertSame('example.com', $request->getUri()->getHost());
        static::assertSame('/api', $request->getUri()->getPath());
    }

    public function testCreateRequestWithUriObject(): void
    {
        $factory = new RequestFactory();
        $uri = new Uri('https://example.org/test');
        $request = $factory->createRequest('POST', $uri);

        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('POST', $request->getMethod());
        static::assertSame($uri, $request->getUri());
    }
}
