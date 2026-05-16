<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Http;

use InvalidArgumentException;
use Waffle\Commons\Http\Uri;

/**
 * Uri test corrected to match the string-only constructor of Uri.php.
 */
class UriTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path?query=1#fragment');

        static::assertSame('https', $uri->getScheme());
        static::assertSame('user:pass', $uri->getUserInfo());
        static::assertSame('example.com', $uri->getHost());
        static::assertSame(8080, $uri->getPort());
        static::assertSame('/path', $uri->getPath());
        static::assertSame('query=1', $uri->getQuery());
        static::assertSame('fragment', $uri->getFragment());
        static::assertSame('user:pass@example.com:8080', $uri->getAuthority());
    }

    public function testToString(): void
    {
        $uriString = 'https://user:pass@example.com/path?query=1#fragment';
        $uri = new Uri($uriString);
        static::assertSame($uriString, (string) $uri);
    }

    public function testToStringOmitsStandardPort(): void
    {
        $uriHttps = new Uri('https://example.com:443/');
        static::assertSame('https://example.com/', (string) $uriHttps);

        $uriHttp = new Uri('http://example.com:80/');
        static::assertSame('http://example.com/', (string) $uriHttp);
    }

    public function testToStringIncludesNonStandardPort(): void
    {
        $uri = new Uri('https://example.com:8080/');
        static::assertSame('https://example.com:8080/', (string) $uri);
    }

    public function testImmutabilityWithScheme(): void
    {
        $uri1 = new Uri('http://example.com');
        $uri2 = $uri1->withScheme('https');

        static::assertNotSame($uri1, $uri2);
        static::assertSame('http', $uri1->getScheme());
        static::assertSame('https', $uri2->getScheme());
    }

    public function testWithUserInfo(): void
    {
        $uri = new Uri('https://example.com');
        $uriWithUser = $uri->withUserInfo('user');
        $uriWithPass = $uriWithUser->withUserInfo('user', 'pass');

        static::assertSame('', $uri->getUserInfo());
        static::assertSame('user', $uriWithUser->getUserInfo());
        static::assertSame('user:pass', $uriWithPass->getUserInfo());
    }

    public function testWithHost(): void
    {
        $uri1 = new Uri('https://example.com');
        $uri2 = $uri1->withHost('waffle-framework.org');

        static::assertNotSame($uri1, $uri2);
        static::assertSame('example.com', $uri1->getHost());
        static::assertSame('waffle-framework.org', $uri2->getHost());
    }

    public function testWithPort(): void
    {
        $uri1 = new Uri('https://example.com');
        $uri2 = $uri1->withPort(8080);
        $uri3 = $uri2->withPort(null);

        static::assertNull($uri1->getPort());
        static::assertSame(8080, $uri2->getPort());
        static::assertNull($uri3->getPort());
    }

    public function testWithInvalidPortThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $uri = new Uri('https://example.com');
        $uri->withPort(70_000); // Port out of range
    }

    public function testWithPath(): void
    {
        $uri1 = new Uri('https://example.com/v1');
        $uri2 = $uri1->withPath('/v2/api');

        static::assertSame('/v1', $uri1->getPath());
        static::assertSame('/v2/api', $uri2->getPath());
    }

    public function testWithQuery(): void
    {
        $uri1 = new Uri('https://example.com');
        $uri2 = $uri1->withQuery('a=1&b=2');

        static::assertSame('', $uri1->getQuery());
        static::assertSame('a=1&b=2', $uri2->getQuery());
    }

    public function testWithFragment(): void
    {
        $uri1 = new Uri('https://example.com');
        $uri2 = $uri1->withFragment('section-1');

        static::assertSame('', $uri1->getFragment());
        static::assertSame('section-1', $uri2->getFragment());
    }
}
