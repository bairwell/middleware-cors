<?php
/**
 * Preflight.
 *
 * All the CORs orientated preflight code.
 *
 * Part of the Bairwell\MiddlewareCors package.
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types = 1);

namespace Bairwell\MiddlewareCors;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Bairwell\MiddlewareCors\Exceptions\NoMethod;
use Bairwell\MiddlewareCors\Exceptions\MethodNotAllowed;
use Bairwell\MiddlewareCors\Exceptions\NoHeadersAllowed;
use Bairwell\MiddlewareCors\Exceptions\HeaderNotAllowed;

/**
 * Preflight.
 * All the CORs orientated preflight code.
 */
class Preflight
{
    use Traits\Parse;

    /**
     * The settings configuration.
     *
     * @var array
     */
    protected $settings;

    /**
     * Callable logger.
     *
     * @var callable
     */
    protected $logger;

    /**
     * Preflight constructor.
     *
     * @param callable $logger A callable logger system.
     */
    public function __construct(callable $logger)
    {
        $this->logger = $logger;
    }//end __construct()


    /**
     * Add a log string if we have a logger.
     *
     * @param string $string String to log.
     *
     * @return boolean True if logged, false if no logger.
     */
    final protected function addLog(string $string) : bool
    {
        $return = call_user_func($this->logger, $string);
        return $return;
    }//end addLog()

    /**
     * Handle the preflight requests for the access control headers.
     *
     * Logic is:
     * - Read in our list of allowed methods, if there aren't any, throw an exception
     *   as that means a bad configuration setting has snuck in.
     * - If the client has not provided an Access-Control-Request-Method, then block
     *   the request by throwing "Invalid options request - no method provided",
     * - If the client provided method is not in our list of allowed methods, then block the
     *   request by throwing "Invalid options request - method not allowed: only ..."
     * - If the client provided an allowed method, then list all the allowed methods on the
     *   header Access-Control-Allow-Methods and return the response object (which should not have
     *   been modified).
     *
     * @param ServerRequestInterface $request  The server request information.
     * @param ResponseInterface      $response The response handler (should be filled in at end or on error).
     * @param array                  $headers  The headers we have already created.
     *
     * @throws \DomainException If there are no configured allowed methods.
     * @throws NoMethod If no method was provided by the user.
     * @throws MethodNotAllowed If the method provided by the user is not allowed.
     * @return ResponseInterface
     */
    final protected function accessControlAllowMethods(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array &$headers
    ) : ResponseInterface
    {
        // check the allow methods
        $allowMethods = $this->parseItem('allowMethods', $request, false);
        if ('' === $allowMethods) {
            // if no methods are allowed, error
            $exception = new \DomainException('No methods configured to be allowed for request');
            throw $exception;
        }

        // explode the allowed methods to trimmed arrays
        $methods = array_map('trim', explode(',', strtoupper((string) $allowMethods)));

        // check they have provided a method
        if ('' === $request->getHeaderLine('access-control-request-method')) {
            // if no methods provided, block it.
            $exception = new NoMethod('No method provided');
            throw $exception;
        }

        // check the requested method is one of our allowed ones. Uppercase it.
        $requestedMethod = strtoupper($request->getHeaderLine('access-control-request-method'));
        // can we find the requested method (we are presuming they are only supplying one as per
        // the CORS specification) in our list of headers.
        if (false === in_array($requestedMethod, $methods)) {
            // no match, throw it.
            $exception = new MethodNotAllowed('Method not allowed');
            $exception->setSent($requestedMethod)
                ->setAllowed($methods);
            throw $exception;
        }

        // if we've got this far, then our access-control-request-method header has been
        // validated so we should add our own outbound header.
        $headers['Access-Control-Allow-Methods'] = $allowMethods;

        // return the response object
        return $response;
    }//end accessControlAllowMethods()

    /**
     * Handle the preflight requests for the access control headers.
     *
     * Logic is:
     * - If there are headers configured, but they client hasn't said they are sending them, just
     *   add the list to the Access-Control-Allow-Headers header and return the response
     *   (which should not have been modified).
     * - If there are no headers configured, but they client has said they are sending some, then
     *   call our blockedCallback with the "Invalid options request - header not allowed: (none)"
     *   message, empty any previously set headers (for security) and return the response object
     *   (which may have been modified by the blockedCallback).
     * - If there are headers configured and the client has said they are sending them, go through
     *   each of the headers provided by the client matching up to our allow list. If we encounter
     *   one that is not on our allow  list, call our blockedCallback with the
     *   "Invalid options request - header not allowed: only ..." message listing the allowed
     *   headers, empty any previously set headers (for security) and return the response object
     *   (which may have been modified by the blockedCallback).
     * - If there are provided headers, and they all match our allow list (we may allow more
     *   headers than requested), then add the complete list to the Access-Control-Allow-Headers
     *   header and return the response (which should not have been modified).
     *
     * @param ServerRequestInterface $request  The server request information.
     * @param ResponseInterface      $response The response handler (should be filled in at end or on error).
     * @param array                  $headers  The headers we have already created.
     *
     * @throws NoHeadersAllowed If headers are not allowed.
     * @throws HeaderNotAllowed If a particular header is not allowed.
     * @return ResponseInterface
     */
    final protected function accessControlRequestHeaders(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array &$headers
    ): ResponseInterface
    {
        // allow headers
        $allowHeaders           = $this->parseItem('allowHeaders', $request, false);
        $requestHeaders         = $request->getHeaderLine('access-control-request-headers');
        $originalRequestHeaders = $requestHeaders;
        // they aren't requesting any headers, but let's send them our list
        if ('' === $requestHeaders) {
            $headers['Access-Control-Allow-Headers'] = $allowHeaders;

            // return the response
            return $response;
        }

        // at this point, they are requesting headers, however, we have no headers configured.
        if ('' === $allowHeaders) {
            // no headers configured, so let's block it.
            $exception = new NoHeadersAllowed('No headers are allowed');
            $exception->setSent($requestHeaders);
            throw $exception;
        }

        // now parse the headers for comparison
        // change the string into an array (separated by ,) and trim spaces
        $requestHeaders = array_map('trim', explode(',', strtolower((string) $requestHeaders)));
        // and do the same with our allowed headers
        $allowedHeaders = array_map('trim', explode(',', strtolower((string) $allowHeaders)));
        // loop through each of their provided headers
        foreach ($requestHeaders as $header) {
            // if we are unable to find a match for the header, block it for security.
            if (false === in_array($header, $allowedHeaders, true)) {
                // block it
                $exception = new HeaderNotAllowed(sprintf('Header "%s" not allowed', $header));
                $exception->setAllowed($allowedHeaders)
                    ->setSent($originalRequestHeaders);
                throw $exception;
            }
        }

        // if we've got this far, then our access-control-request-headers header has been
        // validated so we should add our own outbound header.
        $headers['Access-Control-Allow-Headers'] = $allowHeaders;

        // return the response
        return $response;
    }//end accessControlRequestHeaders()

    /**
     * Handle the preflight requests.
     *
     * Logic is:
     * - Receive a list of previously set headers from calling method (which should
     *   include the Origin: and any credentials related headers)
     * - is the access-control-request-method/allowMethods valid (validated via a separate
     *   method preflightAccessControlAllowMethods). If it has emptied the headers, then it
     *   was invalid and we should just return the response (as it probably contains an error
     *   page) and do not set any headers: otherwise it should have added its own header.
     * - is the access-control-request-headers/allowHeaders valid (validated via a separate
     *   method preflightAccessControlRequestHeaders). If it has emptied the headers, then it
     *   was invalid and we should just return the response (as it probably contains an error
     *   page) and do not set any headers: otherwise it should have added its own header.
     * - If there is a maxAge configuration setting, add that as the Access-Control-Max-Age
     *   header
     * - Add all the set headers to the response object.
     * - If the origin is not "*", add a Vary: line to indicate the response may change if
     *   the origin is difference.
     *
     * @param array                  $settings Our settings.
     * @param ServerRequestInterface $request  The server request information.
     * @param ResponseInterface      $response The response handler (should be filled in at end or on error).
     * @param array                  $headers  The headers we have already created.
     * @param string                 $origin   The origin setting we have decided upon.
     *
     * @return ResponseInterface
     */
    public function __invoke(
        array $settings,
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $headers,
        string $origin
    ) : ResponseInterface
    {
        $this->settings = $settings;

        // preflight the access control allow methods
        $response = $this->accessControlAllowMethods($request, $response, $headers);

        // preflight the access control request headers
        $response = $this->accessControlRequestHeaders($request, $response, $headers);

        // set the Access-Control-Max-Age header. Uses maxAge configuration setting.
        $maxAge = $this->parseItem('maxAge', $request, true);
        // this header should only be set if there is an appropriate configuration setting
        if ($maxAge > 0) {
            $headers['Access-Control-Max-Age'] = (int) $maxAge;
        }

        // add all of our set headers
        foreach ($headers as $k => $v) {
            $response = $response->withHeader($k, $v);
        }

        // if the origin is configured and is not * (wildcard), indicate to the client and
        // associated proxy servers that the response may vary based on the Origin setting
        // that was provided.
        if ('*' !== $origin) {
            $response = $response->withAddedHeader('Vary', 'Origin');
        }

        // remove headers and set as no-content
        $response = $response->withStatus(204, 'No Content')
            ->withoutHeader('Content-Type')
            ->withoutHeader('Content-Length');

        // return the response
        return $response;
    }//end __invoke()
}//end class
