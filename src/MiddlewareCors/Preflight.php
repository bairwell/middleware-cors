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
declare (strict_types=1);

namespace Bairwell\MiddlewareCors;

use Bairwell\MiddlewareCors\Exceptions\SettingsInvalid;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Bairwell\MiddlewareCors\Exceptions\NoMethod;
use Bairwell\MiddlewareCors\Exceptions\MethodNotAllowed;
use Bairwell\MiddlewareCors\Exceptions\NoHeadersAllowed;
use Bairwell\MiddlewareCors\Exceptions\HeaderNotAllowed;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;

/**
 * Preflight.
 * All the CORs orientated preflight code.
 */
class Preflight implements LoggerAwareInterface, RequestHandlerInterface, PreflightInterface
{
    use Traits\Parse;

    /**
     * @var \Closure $responseFactory A factory capable of returning an
     *     empty ResponseInterface instance to return for implicit OPTIONS
     *     requests.
     */
    protected $responseFactory;

    /**
     * @var array Headers.
     */
    protected $headers = [];

    /**
     * Origin
     * @var string
     */
    protected $origin;

    /**
     * @param callable $responseFactory A factory capable of returning an
     *     empty ResponseInterface instance to return for implicit OPTIONS
     *     requests.
     */
    public function __construct(callable $responseFactory)
    {
        // Factories is wrapped in a closure in order to enforce return type safety.
        $this->responseFactory = function () use ($responseFactory) : ResponseInterface {
            return $responseFactory();
        };
        $this->logger = new NullLogger();
        $this->settings = ValidateSettings::getDefaults();
        $this->origin = '*';
        $this->headers = [];
    }

    /**
     * Set the headers.
     *
     * @param array $headers
     * @return PreflightInterface
     */
    public function setHeaders(array $headers): PreflightInterface
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set the logger.
     *
     * @param LoggerInterface $logger Logger.
     * @return PreflightInterface
     */
    public function setLogger(LoggerInterface $logger): PreflightInterface
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Set the origin domain.
     *
     * @param string $origin
     * @return PreflightInterface
     */
    public function setOrigin(string $origin): PreflightInterface
    {
        $this->origin = $origin;
        return $this;
    }

    /**
     * Set the settings.
     * @param array $settings
     * @return PreflightInterface
     * @throws SettingsInvalid If the settings are invalid.
     */
    public function setSettings(array $settings): PreflightInterface
    {
        $this->settings = array_merge($this->settings, $settings);
        ValidateSettings::validate($settings);
        return $this;
    }

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
     * @param ServerRequestInterface $request The server request information.
     * @param array $headers The headers we have already created.
     *
     * @throws \DomainException If there are no configured allowed methods.
     * @throws NoMethod If no method was provided by the user.
     * @throws MethodNotAllowed If the method provided by the user is not allowed.
     * @throws \InvalidArgumentException If dependent details are incorrect.
     * @return array Headers
     */
    final protected function accessControlAllowMethods(
        ServerRequestInterface $request,
        array $headers
    ): array {
        // check the allow methods
        $allowMethods = $this->parseItem('allowMethods', $request);
        if ('' === $allowMethods) {
            // if no methods are allowed, error
            $exception = new \DomainException('No methods configured to be allowed for request');
            throw $exception;
        }

        // explode the allowed methods to trimmed arrays
        $methods = array_map('trim', explode(',', strtoupper($allowMethods)));

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
        if (false === \in_array($requestedMethod, $methods, true)) {
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
        return $headers;
    }

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
     * @param ServerRequestInterface $request The server request information.
     * @param array $headers The headers we have already created.
     * @return array New headers.
     *
     * @throws NoHeadersAllowed If headers are not allowed.
     * @throws HeaderNotAllowed If a particular header is not allowed.
     * @throws \InvalidArgumentException
     */
    final protected function accessControlRequestHeaders(
        ServerRequestInterface $request,
        array $headers
    ): array {
        // allow headers
        $allowHeaders = $this->parseItem('allowHeaders', $request);
        $requestHeaders = $request->getHeaderLine('access-control-request-headers');
        $originalRequestHeaders = $requestHeaders;
        // they aren't requesting any headers, but let's send them our list
        if ('' === $requestHeaders) {
            $headers['Access-Control-Allow-Headers'] = $allowHeaders;

            // return the response
            return $headers;
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
        $requestHeaders = array_map('trim', explode(',', strtolower($requestHeaders)));
        // and do the same with our allowed headers
        $allowedHeaders = array_map('trim', explode(',', strtolower($allowHeaders)));
        // loop through each of their provided headers
        foreach ($requestHeaders as $header) {
            // if we are unable to find a match for the header, block it for security.
            if (false === \in_array($header, $allowedHeaders, true)) {
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
        return $headers;
    }

    /**
     * Handle the preflight requests and produces a response.
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
     * @param ServerRequestInterface $request The server request information.
     * @return ResponseInterface
     *
     * @throws \DomainException If there are no configured allowed methods.
     * @throws NoMethod If no method was provided by the user.
     * @throws MethodNotAllowed If the method provided by the user is not allowed.
     * @throws NoHeadersAllowed If headers are not allowed.
     * @throws HeaderNotAllowed If a particular header is not allowed.
     * @throws \InvalidArgumentException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {

        // preflight the access control allow methods
        $headers = $this->accessControlAllowMethods($request, []);

        // preflight the access control request headers

        $headers = $this->accessControlRequestHeaders($request, $headers);

        // set the Access-Control-Max-Age header. Uses maxAge configuration setting.
        $maxAge = $this->parseItem('maxAge', $request, true);
        // this header should only be set if there is an appropriate configuration setting
        if ($maxAge > 0) {
            $headers['Access-Control-Max-Age'] = (int)$maxAge;
        }

        /* @var \Psr\Http\Message\ResponseInterface $response */
        $response = ($this->responseFactory)();
        // add all of our set headers
        foreach ($headers as $k => $v) {
            $response = $response->withHeader($k, $v);
        }

        // if the origin is configured and is not * (wildcard), indicate to the client and
        // associated proxy servers that the response may vary based on the Origin setting
        // that was provided.
        if ('*' !== $this->origin) {
            $response = $response->withAddedHeader('Vary', 'Origin');
        }

        // remove headers and set as no-content
        $response = $response->withStatus(204, 'No Content')
            ->withoutHeader('Content-Type')
            ->withoutHeader('Content-Length');

        // add the headers.
        foreach ($this->headers as $k => $v) {
            $response = $response->withHeader($k, $v);
        }

        // return the response
        return $response;
    }
}
