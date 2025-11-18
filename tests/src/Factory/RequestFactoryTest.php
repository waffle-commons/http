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
        $this->assertInstanceOf(RequestFactoryInterface::class, $factory);
    }

    public function testCreateRequestWithStringUri(): void
    {
        $factory = new RequestFactory();
        $request = $factory->createRequest('GET', 'https://example.com/api');

        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https', $request->getUri()->getScheme());
        $this->assertSame('example.com', $request->getUri()->getHost());
        $this->assertSame('/api', $request->getUri()->getPath());
    }

    public function testCreateRequestWithUriObject(): void
    {
        $factory = new RequestFactory();
        $uri = new Uri('https://example.org/test');
        $request = $factory->createRequest('POST', $uri);

        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame($uri, $request->getUri());
    }
}
