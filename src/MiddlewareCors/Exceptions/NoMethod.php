<?php
/**
 * CORS Exception for handling inbound requests which do not have a method specified.
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bairwell\MiddlewareCors\Exceptions;

/**
 * CORS Exception for handling inbound requests which do not have a method specified.
 *
 * In CORs requests, the user's browser should be sending a Access-Control-Request-Method header.
 * If one is not sent, then this exception is raised.
 */
class NoMethod extends ExceptionAbstract
{

}//end class
