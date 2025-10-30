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

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(8080, $uri->getPort());
        $this->assertSame('/path', $uri->getPath());
        $this->assertSame('query=1', $uri->getQuery());
        $this->assertSame('fragment', $uri->getFragment());
        $this->assertSame('user:pass@example.com:8080', $uri->getAuthority());
    }

    public function testToString(): void
    {
        $uriString = 'https://user:pass@example.com/path?query=1#fragment';
        $uri = new Uri($uriString);
        $this->assertSame($uriString, (string) $uri);
    }

    public function testToStringOmitsStandardPort(): void
    {
        $uriHttps = new Uri('https://example.com:443/');
        $this->assertSame('https://example.com/', (string) $uriHttps);

        $uriHttp = new Uri('http://example.com:80/');
        $this->assertSame('http://example.com/', (string) $uriHttp);
    }

    public function testToStringIncludesNonStandardPort(): void
    {
        $uri = new Uri('https://example.com:8080/');
        $this->assertSame('https://example.com:8080/', (string) $uri);
    }

    public function testImmutabilityWithScheme(): void
    {
        $uri1 = new Uri('http://example.com');
        $uri2 = $uri1->withScheme('https');

        $this->assertNotSame($uri1, $uri2);
        $this->assertSame('http', $uri1->getScheme());
        $this->assertSame('https', $uri2->getScheme());
    }

    public function testWithUserInfo(): void
    {
        $uri = new Uri('https://example.com');
        $uriWithUser = $uri->withUserInfo('user');
        $uriWithPass = $uriWithUser->withUserInfo('user', 'pass');

        $this->assertSame('', $uri->getUserInfo());
        $this->assertSame('user', $uriWithUser->getUserInfo());
        $this->assertSame('user:pass', $uriWithPass->getUserInfo());
    }

    public function testWithHost(): void
    {
        $uri1 = new Uri('https://example.com');
        $uri2 = $uri1->withHost('waffle-framework.org');

        $this->assertNotSame($uri1, $uri2);
        $this->assertSame('example.com', $uri1->getHost());
        $this->assertSame('waffle-framework.org', $uri2->getHost());
    }

    public function testWithPort(): void
    {
        $uri1 = new Uri('https://example.com');
        $uri2 = $uri1->withPort(8080);
        $uri3 = $uri2->withPort(null);

        $this->assertNull($uri1->getPort());
        $this->assertSame(8080, $uri2->getPort());
        $this->assertNull($uri3->getPort());
    }

    public function testWithInvalidPortThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $uri = new Uri('https://example.com');
        $uri->withPort(70000); // Port out of range
    }

    public function testWithPath(): void
    {
        $uri1 = new Uri('https://example.com/v1');
        $uri2 = $uri1->withPath('/v2/api');

        $this->assertSame('/v1', $uri1->getPath());
        $this->assertSame('/v2/api', $uri2->getPath());
    }

    public function testWithQuery(): void
    {
        $uri1 = new Uri('https://example.com');
        $uri2 = $uri1->withQuery('a=1&b=2');

        $this->assertSame('', $uri1->getQuery());
        $this->assertSame('a=1&b=2', $uri2->getQuery());
    }

    public function testWithFragment(): void
    {
        $uri1 = new Uri('https://example.com');
        $uri2 = $uri1->withFragment('section-1');

        $this->assertSame('', $uri1->getFragment());
        $this->assertSame('section-1', $uri2->getFragment());
    }
}
