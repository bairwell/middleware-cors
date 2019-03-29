<?php
/**
 * CORS Exception for handling bad/invalid origins which are sent.
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bairwell\MiddlewareCors\Exceptions;

/**
 * CORS Exception for handling bad/invalid origins which are sent.
 *
 * A Bad origin is when this is a recognised CORs request, but the user's browser
 * send an origin that was not recognised. If there is NO origin sent, then this
 * is not classed as a CORs request.
 */
class BadOrigin extends ExceptionAbstract
{

}//end class
