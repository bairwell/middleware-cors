# Zend Expressive Integration details

This is a simple guide to get Middleware-cors working with Zend Expressive.

First add to your project's Composer settings:
```
$ composer require bairwell/middleware-cors
```

then in your project's `config/autoload` folder add a file (suggested name `middleware-cors-global.php`) with the following contents:
```php
&lt;?php

declare(strict_types=1);

use Bairwell\MiddlewareCors;
use Bairwell\MiddlewareCors\ZendFramework\MiddlewareCorsFactory;

return [
    'dependencies' => [
        'factories' => [
            MiddlewareCors::class => MiddlewareCorsFactory::class,
        ],
        ],
    'middleware-cors-settings' => [
        'origin' => ['*.example.com', 'example.com', '*.example.com.test', '192.168.*', '10.*'],
        'exposeHeaders' => '',
        'maxAge' => 120,
        'allowCredentials' => true,
        'allowHeaders' => ['Accept', 'Accept-Language', 'Authorization', 'Content-Type', 'DNT', 'Keep-Alive', 'User-Agent', 'X-Requested-With', 'If-Modified-Since', 'Cache-Control', 'Origin'],
    ]
];
```
Replacing the example origins with yours.

In `config/pipeline.php`, remove the now uneeded:
```php
$app->pipe(ImplicitOptionsMiddleware::class);
```
entry and after the:
```php
$app->pipe(RouteMiddleware::class);
```
entry add:
```php
$app->pipe(MiddlewareCors::class);
```