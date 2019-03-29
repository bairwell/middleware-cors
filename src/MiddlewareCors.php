<?php
/**
 * Class MiddlewareCors.
 *
 * A piece of PSR7 compliant middleware (in that it takes a PSR7 request and response
 * fields, a "next" field and handles them appropriate) which adds appropriate CORS
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

namespace Bairwell;

use Bairwell\MiddlewareCors\Traits\Parse;
use Bairwell\MiddlewareCors\ValidateSettings;
use Bairwell\MiddlewareCors\Preflight;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Bairwell\MiddlewareCors\Exceptions\BadOrigin;
use Psr\Log\LoggerInterface;

/**
 * Class MiddlewareCors.
 *
 * A piece of PSR7 compliant middleware (in that it takes a PSR7 request and response
 * fields, a "next" field and handles them appropriate) which adds appropriate CORS
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
class MiddlewareCors
{
    use Parse;

    /**
     * The settings configuration.
     *
     * @var array
     */
    protected $settings;

    /**
     * A list of allowed settings and their parameters/types.
     *
     * @var array
     */
    protected $allowedSettings = [
        'exposeHeaders' => ['string', 'array', 'callable'],
        'allowMethods' => ['string', 'array', 'callable'],
        'allowHeaders' => ['string', 'array', 'callable'],
        'origin' => ['string', 'array', 'callable'],
        'maxAge' => ['int', 'callable'],
        'allowCredentials' => ['bool', 'callable']
    ];

    /**
     * Settings validator.
     *
     * @var ValidateSettings
     */
    protected $validateSettings;

    /**
     * Preflight system.
     *
     * @var Preflight
     */
    protected $preflight;

    /**
     * The logger (if we have one set).
     *
     * @var LoggerInterface $logger
     */
    protected $logger = null;

    /**
     * MiddlewareCors constructor.
     *
     * @param array $settings Our list of CORs related settings.
     */
    public function __construct(array $settings = [])
    {
        $this->validateSettings = new ValidateSettings();
        $this->preflight = new Preflight([$this,'addLog']);
        $this->settings = $this->getDefaults();
        $this->setSettings($settings);
    }//end __construct()

    /**
     * Set the logger.
     *
     * @param LoggerInterface $logger Logger.
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }//end setLogger()

    /**
     * Add a log string if we have a logger.
     *
     * @param string $string  String to log.
     * @param array  $logData Additional data to log.
     *
     * @return boolean True if logged, false if no logger.
     */
    public function addLog(string $string, array $logData = []): bool
    {
        if ($this->logger !== null) {
            $this->logger->debug('CORs: '.$string, $logData);
            return true;
        }

        return false;
    }//end addLog()


    /**
     * Get the default settings.
     *
     * @return array
     */
    public function getDefaults(): array
    {
        // our default settings
        $return = [
            'origin' => '*',
            'exposeHeaders' => '',
            'maxAge' => 0,
            'allowCredentials' => false,
            'allowMethods' => 'GET,HEAD,PUT,POST,DELETE',
            'allowHeaders' => '',
        ];

        return $return;
    }//end getDefaults()

    /**
     * Get the settings.
     *
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }//end getSettings()

    /**
     * Set the settings.
     *
     * @param array $settings The new settings to be merged in.
     *
     * @return self
     */
    public function setSettings(array $settings = []): self
    {
        $this->settings = array_merge($this->settings, $settings);
        // loop through checking each setting
        foreach ($this->allowedSettings as $name => $allowed) {
            $this->validateSettings->__invoke($name, $this->settings[$name], $allowed);
        }

        return $this;
    }//end setSettings()

    /**
     * Get the allowed settings.
     *
     * @return array
     */
    public function getAllowedSettings(): array
    {
        return $this->allowedSettings;
    }//end getAllowedSettings()

    /**
     * Invoke middleware.
     *
     * The __invoke call is used to allow this class to be called as a function.
     * PSR7 middleware should work like this.
     *
     * @param ServerRequestInterface $request PSR7 request object.
     * @param ResponseInterface $response PSR7 response object.
     * @param callable $next Next middleware callable.
     *
     * @return ResponseInterface PSR7 response object
     *
     * @throws BadOrigin If the Origin is not set correctly.
     * @throws MiddlewareCors\Exceptions\HeaderNotAllowed
     * @throws MiddlewareCors\Exceptions\MethodNotAllowed
     * @throws MiddlewareCors\Exceptions\NoHeadersAllowed
     * @throws MiddlewareCors\Exceptions\NoMethod
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ): ResponseInterface
    {
        // if there is no origin header set, then this isn't a CORs related
        // call and we should therefore return.
        if ($request->getHeaderLine('origin') === '') {
            $this->addLog('Request does not have an origin setting');
            // return the next bit of middleware
            $next = $next($request, $response);

            return $next;
        }

        $this->addLog('Request has an origin setting and is being treated like a CORs request');

        // All CORs related requests should have the origin field
        // and the credentials field returned (if they are applicable).
        // All other fields are "request specific" (either preflight or not).
        // set the Access-Control-Allow-Origin header. Uses origin configuration setting.
        $allowedOrigins = [];

        $origin = $this->parseOrigin($request, $allowedOrigins);
        // check the origin is one of the allowed ones.
        if ($origin === '') {
            $exception = new BadOrigin('Bad Origin');
            $exception->setSent($request->getHeaderLine('origin'));
            $exception->setAllowed($allowedOrigins);

            throw $exception;
        }

        $this->addLog('Processing with origin of "'.$origin.'"');
        $headers = [];
        $headers['Access-Control-Allow-Origin'] = $origin;

        // sets the Access-Control-Allow-Credentials header if allowCredentials configuration setting is true.
        $allow = $this->parseAllowCredentials($request);

        // if allowCredentials isn't exactly true, we won't allow the header to be set
        if ($allow === true) {
            $this->addLog('Adding Access-Control-Allow-Credentials header');

            // set the header if true, omit if not
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        // http://www.html5rocks.com/static/images/cors_server_flowchart.png
        // An "OPTIONS" request is a "Preflight" request and we should
        // add all our appropriate headers.
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $this->addLog('Preflighting');
            $return = $this->preflight->__invoke($this->settings, $request, $response, $headers, $origin);
            $this->addLog('Returning from preflight');

            return $return;
        }

        // if it was a non-OPTIONs request, just
        // set the Access-Control-Expose-Headers header. Uses exposeHeaders configuration setting
        $exposeHeaders = $this->parseItem('exposeHeaders', $request, false);

        // this header should only be set if there is an appropriate configuration setting
        if ($exposeHeaders !== '') {
            $this->addLog('Adding Access-Control-Expose-Header header');
            $headers['Access-Control-Expose-Headers'] = $exposeHeaders;
        }

        foreach ($headers as $k => $v) {
            $response = $response->withHeader($k, $v);
        }

        $this->addLog('Calling next bit of middleware');
        // return the next bit of middleware
        $next = $next($request, $response);

        return $next;
    }//end __invoke()
}//end class
