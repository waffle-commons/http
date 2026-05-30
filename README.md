[![Discord](https://img.shields.io/discord/755288001592033391?logo=discord)](https://discord.gg/eKgywnfXr2)
[![PHP Version Require](http://poser.pugx.org/waffle-commons/http/require/php)](https://packagist.org/packages/waffle-commons/http)
[![PHP CI](https://github.com/waffle-commons/http/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/http/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/http/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/http)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/http/v)](https://packagist.org/packages/waffle-commons/http)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/http/v/unstable)](https://packagist.org/packages/waffle-commons/http)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/http.svg)](https://packagist.org/packages/waffle-commons/http)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/http)](https://github.com/waffle-commons/http/blob/main/LICENSE.md)

Waffle HTTP Component
=====================

> **Release:** `v0.1.0-beta2` &nbsp;|&nbsp; [`CHANGELOG.md`](./CHANGELOG.md)
> **PSR Compliance:** PSR-7 (HTTP Messages), PSR-17 (HTTP Factories)

A strict, immutable PSR-7/17 implementation tuned for FrankenPHP worker mode. No singletons, no superglobal touching outside the explicit `GlobalsFactory`. Streams are seekable-aware; the `ResponseEmitter` chunks bodies to avoid loading large payloads into memory.

## 📦 Installation

```bash
composer require waffle-commons/http
```

## 🧱 Surface

| Class | PSR | Role |
| :--- | :--- | :--- |
| `Waffle\Commons\Http\Request` | PSR-7 | Outbound HTTP request message. |
| `Waffle\Commons\Http\ServerRequest` | PSR-7 | Inbound HTTP request (the kernel input). |
| `Waffle\Commons\Http\Response` | PSR-7 | HTTP response message. |
| `Waffle\Commons\Http\Stream` | PSR-7 | Resource-backed `StreamInterface`. |
| `Waffle\Commons\Http\Uri` | PSR-7 | Immutable URI. |
| `Waffle\Commons\Http\UploadedFile` | PSR-7 | File-upload representation. |
| `Waffle\Commons\Http\Abstract\AbstractMessage` | — | Shared base for `Request` / `ServerRequest` / `Response`. |
| `Waffle\Commons\Http\Factory\RequestFactory` | PSR-17 | `createRequest()`. |
| `Waffle\Commons\Http\Factory\ServerRequestFactory` | PSR-17 | `createServerRequest()`. |
| `Waffle\Commons\Http\Factory\ResponseFactory` | PSR-17 | `createResponse()`. |
| `Waffle\Commons\Http\Factory\StreamFactory` | PSR-17 | `createStream()`, `createStreamFromFile()`, `createStreamFromResource()`. |
| `Waffle\Commons\Http\Factory\UriFactory` | PSR-17 | `createUri()`. |
| `Waffle\Commons\Http\Factory\UploadedFileFactory` | PSR-17 | `createUploadedFile()`. |
| `Waffle\Commons\Http\Factory\GlobalsFactory` | — | Framework-specific: builds a PSR-7 `ServerRequest` from `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, and `php://input`. |
| `Waffle\Commons\Http\Emitter\ResponseEmitter` | — | Implements `ResponseEmitterInterface`: sends status line, headers and chunked body. |

## 🚀 Bootstrap a server request

```php
use Waffle\Commons\Http\Factory\GlobalsFactory;

$factory = new GlobalsFactory();
$request = $factory->createFromGlobals(); // PSR-7 ServerRequestInterface
```

The factory takes an optional `(callable(): StreamInterface) $bodyStreamFactory` so tests can inject a synthetic body without touching `php://input`:

```php
public function __construct(?callable $bodyStreamFactory = null)
```

> **Security note.** `GlobalsFactory` does **not** enforce trusted hosts. Host-header anti-poisoning is handled one layer up by `Waffle\Commons\Pipeline\Middleware\TrustedHostMiddleware`, which sits between `ErrorHandlerMiddleware` and `CoreRoutingMiddleware` in the PSR-15 stack.

## 📤 Emit a response

```php
use Waffle\Commons\Http\Emitter\ResponseEmitter;
use Waffle\Commons\Http\Factory\ResponseFactory;

$response = (new ResponseFactory())
    ->createResponse(200)
    ->withHeader('Content-Type', 'application/json');

(new ResponseEmitter())->emit($response);
```

The emitter:

- Throws `\RuntimeException` if headers are already sent.
- Sends one `header()` per header value (combining for non-`Set-Cookie` headers).
- Reads the response body in 8 KiB chunks via `StreamInterface::read()`, so streaming large payloads is memory-bounded.

## 🐘 PHP 8.5 features used

- **Immutable, named-argument-friendly factories** — every `with…()` accessor returns a clone.
- **Typed properties + constructor promotion** across messages.
- **Strict types** in every file (`declare(strict_types=1);`).
- The `ResponseEmitter::emit()` signature uses `#[\Override]` to assert the contract.

## 🧭 Architectural boundary (`mago guard`)

An active dependency **perimeter** is enforced on every CI run by `vendor/bin/mago guard` (bundled into `composer mago`; zero baselines). The rules live in [`mago.toml`](./mago.toml) under `[guard.perimeter]` — a forbidden `use` statement fails the build, not a reviewer.

Production code under `Waffle\Commons\Http` may depend **only** on:

- `Waffle\Commons\Http\**` — itself
- `Waffle\Commons\Contracts\**` — the shared contracts package, the **only** Waffle dependency permitted
- `Psr\**` — PSR interfaces (PSR-7 / PSR-17)
- `@global` + `Psl\**` — PHP core and the PHP Standard Library

Test code under `WaffleTests\Commons\Http` is unrestricted (`@all`); the `php-mock` bootstrap fixtures noted under Testing are listed in `[guard].excludes` because they intentionally re-declare the production namespace. Structural rules are guarded too: interfaces must be named `*Interface`, `Exception\**` classes must end in `*Exception`, and any `Enum\**` namespace may hold only `enum` declarations.

Contract-first, component-agnostic by construction: components compose through `waffle-commons/contracts`, never directly through one another.

## 🧪 Testing

```bash
docker exec -w /waffle-commons/http waffle-dev composer tests
```

Mock-bootstrap files under `tests/src/StreamTest.php`, `tests/src/UploadedFileTest.php`, `tests/src/Factory/StreamFactoryTest.php`, and `tests/src/Emitter/ResponseEmitterTest.php` intentionally declare the production namespace to override built-in PHP functions via `php-mock-phpunit`. They are listed in `mago.toml [guard].excludes` for that reason.

## 📄 License

MIT — see [LICENSE.md](./LICENSE.md).
