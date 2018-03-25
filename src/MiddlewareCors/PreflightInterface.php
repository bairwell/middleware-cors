<?php
declare(strict_types=1);

namespace Bairwell\MiddlewareCors;

use Bairwell\MiddlewareCors\Exceptions\HeaderNotAllowed;
use Bairwell\MiddlewareCors\Exceptions\MethodNotAllowed;
use Bairwell\MiddlewareCors\Exceptions\NoHeadersAllowed;
use Bairwell\MiddlewareCors\Exceptions\NoMethod;
use Bairwell\MiddlewareCors\Exceptions\SettingsInvalid;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Preflight.
 * All the CORs orientated preflight code.
 */
interface PreflightInterface
{

    /**
     * @param callable $responseFactory A factory capable of returning an
     *                                  empty ResponseInterface instance to return for implicit OPTIONS
     *                                  requests.
     */
    public function __construct(callable $responseFactory);

    /**
     * Set headers.
     *
     * @param  array $headers
     * @return PreflightInterface
     */
    public function setHeaders(array $headers): PreflightInterface;

    /**
     * Set the logger.
     *
     * @param  LoggerInterface $logger Logger.
     * @return PreflightInterface
     */
    public function setLogger(LoggerInterface $logger): PreflightInterface;

    /**
     * Set the origin domain.
     *
     * @param  string $origin
     * @return PreflightInterface
     */
    public function setOrigin(string $origin): PreflightInterface;

    /**
     * Set the settings.
     *
     * @param  array $settings
     * @return PreflightInterface
     * @throws SettingsInvalid If the settings are invalid.
     */
    public function setSettings(array $settings): PreflightInterface;

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
     * @param  ServerRequestInterface $request The server request information.
     * @return ResponseInterface
     *
     * @throws \DomainException If there are no configured allowed methods.
     * @throws NoMethod If no method was provided by the user.
     * @throws MethodNotAllowed If the method provided by the user is not allowed.
     * @throws NoHeadersAllowed If headers are not allowed.
     * @throws HeaderNotAllowed If a particular header is not allowed.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
