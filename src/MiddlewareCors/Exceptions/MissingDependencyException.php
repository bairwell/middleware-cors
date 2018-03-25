<?php
declare(strict_types=1);

namespace Bairwell\MiddlewareCors\Exceptions;

use RuntimeException;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class MissingDependencyException.
 *
 * Used for Zend routes.
 *
 * @package Bairwell\MiddlewareCors\Exceptions
 */
class MissingDependencyException extends RuntimeException implements NotFoundExceptionInterface
{

    public static function dependencyForService(string $dependency, string $service): self
    {
        return new self(\sprintf(
            'Missing dependency "%s" for service "%2$s"; please make sure it is'
            . ' registered in your container. Refer to the %2$s class and/or its'
            . ' factory to determine what the service should return.',
            $dependency,
            $service
        ));
    }
}
