# h4cc/stack-psr7-bridge


[StackPHP](http://stackphp.com/) Middleware for using Symfony HttpKernel Applicationsand PSR-7 Application transparent.

[![Build Status](https://travis-ci.org/h4cc/stack-psr7-bridge.svg)](https://travis-ci.org/h4cc/stack-psr7-bridge)
[![HHVM Status](http://hhvm.h4cc.de/badge/h4cc/stack-psr7-bridge.svg)](http://hhvm.h4cc.de/package/h4cc/stack-psr7-bridge)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/h4cc/stack-psr7-bridge/badges/quality-score.png)](https://scrutinizer-ci.com/g/h4cc/stack-psr7-bridge/)
[![Code Coverage](https://scrutinizer-ci.com/g/h4cc/stack-psr7-bridge/badges/coverage.png)](https://scrutinizer-ci.com/g/h4cc/stack-psr7-bridge/)
[![Project Status](http://stillmaintained.com/h4cc/stack-psr7-bridge.png)](http://stillmaintained.com/h4cc/stack-psr7-bridge)


This Middleware tries to make using Symfony HttpKernel Application and PSR-7 as easy as possible.

Wrapped applications can be:

- Any Symfony HttpKernel
- Any Callback expecting `function(RequestInterface $request, ResponseInterface $response, $next = null)`

It does not matter what kind of application is wrapped, the bridge will convert incoming requests and outgoing responses accordingly
to the used interface.

The implementation this middleware is based on is [https://github.com/symfony/psr-http-message-bridge](https://github.com/symfony/psr-http-message-bridge).


## Usage

By default, the Symfony HttpFoundation and HttpKernel are used.
For PSR-7, the [Zend-Diactoros](https://github.com/zendframework/zend-diactoros) implementation is used.
These implementations can be changed if needed.

### Wrapping a HttpKernel

```php
<?php

$bridge = new Psr7Bridge($yourHttpKernel);

// Handling PSR-7 requests
$psr7Response = $bridge->__invoke($psr7Request, $psr7Response);

// Handling Symfony requests
$symfonyResponse = $bridge->_handle($symfonyRequest);
```

### Wrapping a PSR-7 callback

> The expected PSR-7 callback format is not yet defined by PHP-FIG and might be subject to change!

```php
<?php

$psr7Callback = function(RequestInterface $request, ResponseInterface $response, $next = null) {
  // Creating a PSR-7 Response here ...
};

$bridge = new Psr7Bridge($psr7Callback);

// Handling PSR-7 requests
$psr7Response = $bridge->__invoke($psr7Request, $psr7Response);

// Handling Symfony requests
$symfonyResponse = $bridge->_handle($symfonyRequest);
```


## Installation

The recommended way to install stack-psr7-bridge is through [Composer](http://getcomposer.org/):

``` json
{
    "require": {
        "h4cc/stack-psr7-bridge": "@stable"
    }
}
```

**Protip:** you should browse the [`h4cc/stack-psr7-bridge`](https://packagist.org/packages/h4cc/stack-psr7-bridge)
page to choose a stable version to use, avoid the `@stable` meta constraint.



## License

h4cc/stack-psr7-bridge is released under the MIT License. See the bundled LICENSE file for details.
