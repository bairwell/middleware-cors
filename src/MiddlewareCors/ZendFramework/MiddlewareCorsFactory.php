<?php

declare(strict_types=1);

namespace Bairwell\MiddlewareCors\ZendFramework;

use Bairwell\MiddlewareCors;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Bairwell\MiddlewareCors\Exceptions\MissingDependencyException;
use Zend\Expressive\Router\RouteResult;

/**
 * Create and return a MiddlewareCors instance.
 *
 * This factory depends on one other service:
 *
 * - Psr\Http\Message\ResponseInterface, which should resolve to a callable
 *   that will produce an empty Psr\Http\Message\ResponseInterface instance.
 */
class MiddlewareCorsFactory
{
    /**
     * Creates the cors middleware.
     *
     * @param ContainerInterface $container Container.
     * @return MiddlewareCors
     * @throws MissingDependencyException if the Psr\Http\Message\ResponseInterface
     *     service or middleware-cors-settings is missing.
     * @throws MiddlewareCors\Exceptions\SettingsInvalid If settings is invalid.
     * @throws \Psr\Container\ContainerExceptionInterface If the container throws an error.
     * @throws \Psr\Container\NotFoundExceptionInterface If necessary items are not in the container.
     * @throws \RuntimeException If middleware-cors-settings is not an array.
     */
    public function __invoke(ContainerInterface $container): MiddlewareCors
    {
        if (!$container->has(ResponseInterface::class)) {
            throw MissingDependencyException::dependencyForService(
                ResponseInterface::class,
                MiddlewareCors::class
            );
        }
        if (!$container->has('config')) {
            throw MissingDependencyException::dependencyForService(
                'config',
                MiddlewareCors::class
            );
        }
        $config = $container->get('config');
        if (!array_key_exists('middleware-cors-settings', $config)) {
            throw MissingDependencyException::dependencyForService(
                'config[\'middleware-cors-settings\']',
                MiddlewareCors::class
            );
        }
        if (!\is_array($config['middleware-cors-settings'])) {
            throw new \RuntimeException('config[\'middleware-cors-settings\'] is not an array');
        }
        $settings = $config['middleware-cors-settings'];
        if (!isset($settings['allowMethods'])) {
            $corsAllowedMethods = function (ServerRequestInterface $request): array {

                /* @var RouteResult|null $routeResult */
                $routeResult = $request->getAttribute(RouteResult::class);

                $methods = [];
                // was the method called allowed?
                if (!$routeResult->isMethodFailure()) {
                    // if it was, see if we can get the routes and then the methods from it.
                    $methods = $routeResult->getAllowedMethods();
                }

                // if we have methods, let's list them removing the OPTIONs one.
                if (0 === count($methods)) {
                    // find the OPTIONs method
                    /* @var int $key */
                    $key = array_search('OPTIONS', $methods, true);
                    // and remove it if set.
                    if (false !== $key) {
                        unset($methods[$key]);
                        $methods = array_values($methods);
                    }
                }

                return $methods;
            };
            $settings['allowMethods'] = $corsAllowedMethods;
        }
        $middleware = new MiddlewareCors(
            $container->get(ResponseInterface::class),
            $settings
        );
        return $middleware;
    }
}
