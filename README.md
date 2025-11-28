[![PHP Version Require](http://poser.pugx.org/waffle-commons/http/require/php)](https://packagist.org/packages/waffle-commons/http)
[![PHP CI](https://github.com/waffle-commons/http/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/http/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/http/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/http)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/http/v)](https://packagist.org/packages/waffle-commons/http)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/http/v/unstable)](https://packagist.org/packages/waffle-commons/http)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/http.svg)](https://packagist.org/packages/waffle-commons/http)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/http)](https://github.com/waffle-commons/http/blob/main/LICENSE.md)

Waffle HTTP Component
===============================

A strict, lightweight, and high-performance implementation of PSR-7 (HTTP Message) and PSR-17 (HTTP Factories).

## 📦 Installation

```bash
composer require waffle-commons/http
```

## 🚀 Usage

### 1\. Creating a Request from Globals (Bootstrap)

The GlobalsFactory is designed to capture the current PHP environment (superglobals like `$_SERVER`, `$_POST`, `$_FILES`) and convert it into a PSR-7 ServerRequestInterface. This is typically used at the entry point of your application (index.php).

```php
use Waffle\Commons\Http\Factory\GlobalsFactory;

// Create the factory
$factory = new GlobalsFactory();
// Capture the current request
$request = $factory->createFromGlobals();

echo $request->getMethod();
// e.g., "GET"  echo $request->getUri()->getPath();
// e.g., "/api/users"
```

### 2\. Creating Responses

You can create responses manually or using the PSR-17 ResponseFactory.

**Manual Instantiation:**

```php
use Waffle\Commons\Http\Response;

// Create a 200 OK response with JSON content
$response = new Response(
    200,
    ['Content-Type' => 'application/json'],
    json_encode(['status' => 'ok'])
);
```

**Using Factory (Recommended for decoupling):**

```php
use Waffle\Commons\Http\Factory\ResponseFactory;

$factory = new ResponseFactory();
$response = $factory->createResponse(404, 'Resource Not Found');
```

### 3\. Emitting a Response

To send the response to the client (browser), use the ResponseEmitter.

```php
use Waffle\Commons\Http\Emitter\ResponseEmitter;

$emitter = new ResponseEmitter();
$emitter->emit($response);
```

### 4\. Using PSR-17 Factories

This package provides implementations for all PSR-17 factory interfaces, allowing you to create HTTP objects in a standard way.

*   **Waffle\\Commons\\Http\\Factory\\RequestFactory**: Creates client-side requests.

*   **Waffle\\Commons\\Http\\Factory\\ServerRequestFactory**: Creates server-side requests.

*   **Waffle\\Commons\\Http\\Factory\\ResponseFactory**: Creates responses.

*   **Waffle\\Commons\\Http\\Factory\\StreamFactory**: Creates streams from strings, files, or resources.

*   **Waffle\\Commons\\Http\\Factory\\UriFactory**: Creates URI objects.

*   **Waffle\\Commons\\Http\\Factory\\UploadedFileFactory**: Creates uploaded file objects.


Example creating a stream:

```php
use Waffle\Commons\Http\Factory\StreamFactory;

$factory = new StreamFactory();
$stream = $factory->createStream('Hello World');

echo $stream->getContents(); // "Hello World"
```

Features
--------

*   **PSR-7:** Full implementation of `Request`, `Response`, `ServerRequest`, `Stream`, `Uri`, `UploadedFile`.
*   **PSR-17:** Full implementation of Factories for all HTTP objects.
*   **Response Emitter:** A simple emitter to output PSR-7 responses to the browser.
*   **Lightweight:** Minimal dependencies, focused on performance.
*   **Strict Typing:** Built with PHP 8.4+ strict types for reliability.
*   **Zero Dependencies:** No external dependencies other than psr/http-message and psr/http-factory.
*   **Secure by Design:** Robust handling of headers, file uploads, and streams.


Testing
-------

This component is fully tested with PHPUnit.

```shell
composer tests
```

Contributing
------------

Contributions are welcome! Please refer to [CONTRIBUTING.md](./CONTRIBUTING.md) for details.

License
-------

This project is licensed under the MIT License. See the [LICENSE.md](./LICENSE.md) file for details.