# Bairwell\Cors

[![Latest Stable Version](https://poser.pugx.org/bairwell/cors/v/stable)](https://packagist.org/packages/bairwell/cors)
[![License](https://poser.pugx.org/bairwell/cors/license)](https://packagist.org/packages/bairwell/cors)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/8aea9987-e478-4faa-a3fe-728e9423b4e7/mini.png)](https://insight.sensiolabs.com/projects/8aea9987-e478-4faa-a3fe-728e9423b4e7)
[![Coverage Status](https://coveralls.io/repos/bairwell/cors/badge.svg?branch=master&service=github)](https://coveralls.io/github/bairwell/cors?branch=master)
[![Build Status](https://travis-ci.org/bairwell/cors.svg?branch=master)](https://travis-ci.org/bairwell/cors)
[![Total Downloads](https://poser.pugx.org/bairwell/cors/downloads)](https://packagist.org/packages/bairwell/cors)

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
* Minimal third party requirements (just the definition files "psr/http-message" and "psr/log" for main, and PHPUnit, PHPCodeSniffer, SlimFramework and Monolog for development/testing).

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
`vendor/bin/phpunit --filter 'Cors\\Exceptions\\BadOrigin'`

or just

`vendor/bin/phpunit --filter 'ExceptionTest'`

for all tests which have "Exception" in them and:
`vendor/bin/phpunit --filter '(ExceptionTest::testEverything|ExceptionTest::testStub)'`

to test the two testEverything and testStub methods in the ExceptionTest class.

