<?php
/**
 * CORS Exception for handling bad/invalid methods which are sent.
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types = 1);

namespace Bairwell\Cors\Exceptions;

/**
 * CORS Exception for handling bad/invalid methods which are sent.
 *
 * For example, in a preflight request the user's browser can send an
 * Access-Control-Request-Method header of "DELETE" and if that is not in our
 * allowed list, we raise this exception.
 */
class MethodNotAllowed extends ExceptionAbstract
{

}//end class
