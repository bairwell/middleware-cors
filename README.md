# Bairwell\Middleware-Cors

[![Latest Stable Version](https://poser.pugx.org/bairwell/middleware-cors/v/stable)](https://packagist.org/packages/bairwell/middleware-cors)
[![License](https://poser.pugx.org/bairwell/middleware-cors/license)](https://packagist.org/packages/bairwell/middleware-cors)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/8aea9987-e478-4faa-a3fe-728e9423b4e7/mini.png)](https://insight.sensiolabs.com/projects/8aea9987-e478-4faa-a3fe-728e9423b4e7)
[![Coverage Status](https://coveralls.io/repos/bairwell/middleware-cors/badge.svg?branch=master&service=github)](https://coveralls.io/github/bairwell/middleware-cors?branch=master)
[![Build Status](https://travis-ci.org/bairwell/middleware-cors.svg?branch=master)](https://travis-ci.org/bairwell/middleware-cors)
[![Total Downloads](https://poser.pugx.org/bairwell/middleware-cors/downloads)](https://packagist.org/packages/bairwell/middleware-cors)

This is a PHP 7 [Composer](https://getcomposer.org/) compatible library for providing a [PSR-7]((http://www.php-fig.org/psr/psr-7/) compatible middleware layer for handling
"[CORS](https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS)" (Cross Origin Request Security/Cross-Origin Http Request/HTTP access control) headers and security.

## What does this library provides over other CORs libraries?

* PHP-7 type declarations.
* Works as a piece of [PSR-7](http://www.php-fig.org/psr/psr-7/) middleware making it compatible with many frameworks (such as [Slim 3](http://slimframework.com) and [Symfony](http://symfony.com/blog/psr-7-support-in-symfony-is-here))
* Massively flexibility over configuration settings (most can be strings, arrays or callbacks).
* Follows the [CORs flowchart](http://www.html5rocks.com/static/images/cors_server_flowchart.png) and actively rejects invalid requests.
* Only sends the appropriate headers when necessary.
* On CORs "OPTIONS" request, ensure a blank page 204 "No Content" page is returned instead of returning unwanted content bodies.
* Supports [PSR-3](http://www.php-fig.org/psr/psr-3/) based loggers for debugging purposes.
* Ignores non-CORs "OPTIONS" requests (for example, on REST services). A CORs request is indicated by the presence of the Origin: header on the inbound request.
* Fully unit tested.
* Licensed under the [MIT License](https://opensource.org/licenses/MIT) allowing you to practically do whatever you want.
* Uses namespaces and is 100% object orientated.
* Blocks invalid settings.
* Minimal third party requirements (just the definition files "[psr/http-message](https://github.com/php-fig/http-message)" and "[psr/log](https://github.com/php-fig/log)" as interface definitions, and [PHPUnit](https://phpunit.de/), [PHPCodeSniffer](http://www.squizlabs.com/php-codesniffer), and [Monolog](https://github.com/Seldaek/monolog) for development/testing).

# Installation
Install the latest version with Composer via:

```bash
$ composer require bairwell/middleware-cors
```

or by modifying your `composer.json` file:
````
{
    "require": {
        "bairwell/middleware-cors": "@stable"
    }
}
````

or from the Github repository (which is needed to be able to fork and contribute):
````
$ git clone git://github.com:bairwell/middleware-cors.git
````

# Usage

You can utilise this CORs library as simply as:

```php
$slim=new \Slim\App(); // use Slim3 as it supports PSR7 middleware
$slim->add(new MiddlewareCors()); // add CORs
// add routes
$slim->run(); // get Slim running
```

but that won't really add much (as it allows all hosts origin and methods by default).

You can make it slightly more complex such as:

```php
$slim=new \Slim\App(); // use Slim3 as it supports PSR7 middleware
$config=[
    'origin'=>'*.example.com' // allow all hosts ending example.com
];
$slim->add(new MiddlewareCors($config)); // add CORs
// add routes
$slim->run(); // get Slim running
```

or

```php
$slim=new \Slim\App(); // use Slim3 as it supports PSR7 middleware
$config=[
    'origin'=>['*.example.com','*.example.com.test','example.com','dev.*',
    'allowCredentials'=>true
];
$slim->add(new MiddlewareCors($config)); // add CORs
// add routes
$slim->run(); // get Slim running
```

which will allow all Origins ending .example.com or *.example.com.test, the exact example.com origin or
any host starting with dev. It'll also allow credentials to be allowed.

For a more complicated integration which relies on the Slim router to feed back which methods are actually
allowed per route, see ``tests/MiddlewareCors/FunctionalTests/SlimTest.php``

## Suggested settings
```php
// read the allowed methods for a route
 $corsAllowedMethods = function (ServerRequestInterface $request) use ($container): array {

            // if this closure is called, make sure it has the route available in the container.
            /* @var RouterInterface $router */
            $router = $container->get('router');

            $routeInfo = $router->dispatch($request);
            $methods = [];
            // was the method called allowed?
            if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
                $methods = $routeInfo[1];
            } else {
                // if it was, see if we can get the routes and then the methods from it.
                // @var \Slim\Route $route
                $route = $request->getAttribute('route');
                // has the request get a route defined? is so use that
                if (null !== $route) {
                    $methods = $route->getMethods();
                }
            }

            // if we have methods, let's list them removing the OPTIONs one.
            if (0 === count($methods)) {
                // find the OPTIONs method
                $key = array_search('OPTIONS', $methods,true);
                // and remove it if set.
                if (false !== $key) {
                    unset($methods[$key]);
                    $methods = array_values($methods);
                }
            }

            return $methods;
        };
$cors = new MiddlewareCors(
                [
                    'origin' => ['*.example.com','example.com','*.example.com.test','192.168.*','10.*'],
                    'exposeHeaders' => '',
                    'maxAge' => 120,
                    'allowCredentials' => true,
                    'allowMethods' => $corsAllowedMethods,
                    'allowHeaders' => ['Accept', 'Accept-Language', 'Authorization', 'Content-Type','DNT','Keep-Alive','User-Agent','X-Requested-With','If-Modified-Since','Cache-Control','Origin'],
                ]
            );
$slim->add($cors);
```
## Standards

The following [PHP FIG](http://www.php-fig.org/psr/) standards should be followed:

 * [PSR 1 - Basic Coding Standard](http://www.php-fig.org/psr/psr-1/)
 * [PSR 2 - Coding Style Guide](http://www.php-fig.org/psr/psr-2/)
 * [PSR 3 - Logger Interface](http://www.php-fig.org/psr/psr-3/)
 * [PSR 4 - Autoloading Standard](http://www.php-fig.org/psr/psr-4/)
 * [PSR 5 - PHPDoc Standard](https://github.com/phpDocumentor/fig-standards/tree/master/proposed) - (still in draft)
 * [PSR 7 - HTTP Message Interface](http://www.php-fig.org/psr/psr-7/) 
 * [PSR 12 - Extended Coding Style Guide](https://github.com/php-fig/fig-standards/blob/master/proposed/extended-coding-style-guide.md) - (still in draft)
 
### Standards Checking
[PHP Code Sniffer](https://github.com/squizlabs/PHP_CodeSniffer/) highlights potential coding standards issues.

`vendor/bin/phpcs`

PHP CS will use the configuration in `phpcs.xml.dist` by default.

To see which sniffs are running add "-s"

## Unit Tests
[PHPUnit](http://phpunit.de) is installed for unit testing (tests are in `tests`)

To run unit tests:
`vendor/bin/phpunit`

For a list of the tests that have ran:
`vendor/bin/phpunit --tap`

To restrict the tests run:
`vendor/bin/phpunit --filter 'MiddlewareCors\\Exceptions\\BadOrigin'`

or just

`vendor/bin/phpunit --filter 'ExceptionTest'`

for all tests which have "Exception" in them and:
`vendor/bin/phpunit --filter '(ExceptionTest::testEverything|ExceptionTest::testStub)'`

to test the two testEverything and testStub methods in the ExceptionTest class (for example).

# Licence/License

Licenced under the MIT license. See LICENSE.md for full information.

Bairwell/MiddlewareCors is Copyright (c) Bairwell Ltd/Richard Bairwell 2016.

# Supporting development

You can help support development of this library via a variety of methods:

 * "Sponsorship" via a monthly donation via [Patreon](https://www.patreon.com/rbairwell)
 * [Reporting issues](https://github.com/bairwell/middleware-cors/issues)
 * Making updates via [Github](https://github.com/bairwell/middleware-cors)
 * Spreading the word.
 * Just letting me know what you think of it via [Twitter](http://twitter.com/rbairwell) or via [Bairwell Ltd](http://www.bairwell.com)

