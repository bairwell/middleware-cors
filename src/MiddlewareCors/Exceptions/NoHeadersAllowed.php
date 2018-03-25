<?php
/**
 * CORS Exception for handling inbound requests which specify headers will be sent,
 * but we specifically do not allow headers.
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types=1);

namespace Bairwell\MiddlewareCors\Exceptions;

/**
 * CORS Exception for handling inbound requests which specify headers will be sent,
 * but we specifically do not allow headers.
 */
class NoHeadersAllowed extends ExceptionAbstract
{

}
