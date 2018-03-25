<?php
/**
 * Class MiddlewareCors.
 *
 * A piece of PSR-15 compliant middleware (in that it takes a PSR-15 ServerRequestInterface
 * and RequestHandelerInterface) which adds appropriate CORS
 * (Cross-domain Origin Request System) headers to the response to enable browsers
 * to correctly enforce security.
 *
 * Supports https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types=1);

namespace Bairwell;

use Bairwell\MiddlewareCors\Exceptions\SettingsInvalid;
use Bairwell\MiddlewareCors\ValidateSettings;
use Bairwell\MiddlewareCors\Preflight;
use Bairwell\MiddlewareCors\PreflightInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\NullLogger;
use Bairwell\MiddlewareCors\Traits\Parse;

/**
 * Class MiddlewareCors.
 *
 * A piece of PSR-15 compliant middleware which adds appropriate CORS
 * (Cross-domain Origin Request System) headers to the response to enable browsers
 * to correctly enforce security.
 * Supports https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS
 * What should happen:
 *  - No "Origin" header:
 *     - "next" middleware called [not for us]
 *     - (no CORs related headers generated no matter which method is used)
 *  - "Origin" header called and it is NOT on our allowed origins
 *     - error page (bad origin)
 *  - "Origin" valid and this is a non-OPTIONS request
 *     - Add Access-Control-Allow-Origin
 *     - Add Access-Control-Allow-Credentials if applicable
 *     - Add Access-Control-Expose-Headers if applicable
 *     - "next" middleware called
 *  - "Origin" valid, OPTIONS, and NO Access-Control-Request-Method header
 *     - error page (invalid options request - no method provided)
 *  - "Origin" valid, OPTIONS, and Access-Control-Request-Method header but not allowed
 *     - error page (invalid options request - method not allowed)
 *  - "Origin" valid, OPTIONS,and ACRM header allowed, but Access-Control-Request-Headers provided but not allowed
 *     - error page (invalid options request - header not allowed)
 *  - "Origin" valid, OPTIONS, ACRM header allowed and ACRH provided+valid or not provided
 *     - Add Access-Control-Allow-Origin
 *     - Add Access-Control-Allow-Credentials if applicable
 *     - Add Access-Control-Allow-Methods
 *     - Add Access-Control-Allow-Headers
 *     - Add Access-Control-Max-Age (optional)
 *     - Add Vary:Origin if Origin is not *
 */
class MiddlewareCors implements MiddlewareInterface, LoggerAwareInterface
{

    use Parse;

    /**
     * Preflight system.
     *
     * @var PreflightInterface
     */
    protected $preflight;

    /**
     * @var callable
     */
    private $responseFactory;

    /**
     * @param callable $responseFactory A factory capable of returning an
     *     empty ResponseInterface instance to return for implicit OPTIONS
     *     requests.
     * @param array $settings Any settings.
     * @throws SettingsInvalid If the settings are invalid.
     */
    public function __construct(callable $responseFactory, array $settings = null)
    {
        // Factories is wrapped in a closure in order to enforce return type safety.
        $this->responseFactory = function () use ($responseFactory) : ResponseInterface {
            return $responseFactory();
        };
        $this->logger = new NullLogger();
        $this->settings = ValidateSettings::getDefaults();
        if (null !== $settings) {
            $this->setSettings($settings);
        }
    }


    /**
     * Set the logger.
     *
     * @param LoggerInterface $logger Logger.
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get the settings.
     *
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Set the settings.
     *
     * @param array $settings The new settings to be merged in.
     *
     * @return self
     *
     * @throws SettingsInvalid If settings are invalid.
     */
    public function setSettings(array $settings = []): self
    {
        $this->settings = array_merge($this->settings, $settings);
        ValidateSettings::validate($this->settings);

        return $this;
    }


    /**
     * Process an incoming server request (PS15).
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     *
     * @param ServerRequestInterface $request PS15 request object.
     * @param RequestHandlerInterface $handler PS15 response handling object.
     * @return ResponseInterface
     *
     * @throws \InvalidArgumentException If unable to parse details.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        // if there is no origin header set, then this isn't a CORs related
        // call and we should therefore return.
        if ('' === $request->getHeaderLine('origin')) {
            $this->logger->debug('Request does not have an origin setting');
            return $handler->handle($request);
        }

        $this->logger->debug('Request has an origin setting and is being treated like a CORs request');
        // All CORs related requests should have the origin field
        // and the credentials field returned (if they are applicable).
        // All other fields are "request specific" (either preflight or not).
        // set the Access-Control-Allow-Origin header. Uses origin configuration setting.
        $allowedOrigins = [];
        $origin = $this->parseOrigin($request, $allowedOrigins);
        // check the origin is one of the allowed ones.
        if ('' === $origin) {
            \call_user_func($this->settings['badOriginCallable'], $request, $allowedOrigins);
        }

        $this->logger->debug('Processing with origin of "' . $origin . '"');
        $headers = [];
        $headers['Access-Control-Allow-Origin'] = $origin;
        // sets the Access-Control-Allow-Credentials header if allowCredentials configuration setting is true.
        $allow = $this->parseAllowCredentials($request);
        // if allowCredentials isn't exactly true, we won't allow the header to be set
        if (true === $allow) {
            $this->logger->debug('Adding Access-Control-Allow-Credentials header');
            // set the header if true, omit if not
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        // http://www.html5rocks.com/static/images/cors_server_flowchart.png
        // An "OPTIONS" request is a "Preflight" request and we should
        // add all our appropriate headers.
        if ('OPTIONS' === strtoupper($request->getMethod())) {
            $this->logger->debug('Preflighting');
            if (null === $this->preflight) {
                $this->preflight = new Preflight($this->responseFactory);
                $this->preflight->setLogger($this->logger);
            }
            $this->preflight->setSettings($this->settings)
                ->setOrigin($origin)
                ->setHeaders($headers);
            $return = $this->preflight->handle($request);
            $this->logger->debug('Returning from preflight');
            return $return;
        }

        // if it was a non-OPTIONs request, just
        // set the Access-Control-Expose-Headers header. Uses exposeHeaders configuration setting
        $exposeHeaders = $this->parseItem('exposeHeaders', $request);
        // this header should only be set if there is an appropriate configuration setting
        if ('' !== $exposeHeaders) {
            $this->logger->debug('Adding Access-Control-Expose-Header header');
            $headers['Access-Control-Expose-Headers'] = $exposeHeaders;
        }

        // process the request.
        $response = $handler->handle($request);
        // add the headers.
        foreach ($headers as $k => $v) {
            $response = $response->withHeader($k, $v);
        }

        return $response;
    }
}
