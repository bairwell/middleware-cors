<?php
/**
 * CORS Exception for handling inbound requests which specify that a particular
 * header will be sent, but that we do not allow that header.
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types=1);

namespace Bairwell\MiddlewareCors\Exceptions;

/**
 * CORS Exception for handling inbound requests which specify that a particular
 * header will be sent, but that we do not allow that header.
 */
class HeaderNotAllowed extends ExceptionAbstract
{

}
