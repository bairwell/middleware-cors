<?php
/**
 * Trait Parse.
 *
 * All the CORs orientated parsing code.
 *
 * Part of the Bairwell\MiddlewareCors package.
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types=1);

namespace Bairwell\MiddlewareCors\Traits;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Trait Parse.
 * All the CORs orientated parsing code.
 */
trait Parse
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Settings
     * @var array
     */
    protected $settings;

    /**
     * Parse an item from string/int/callable/array to an expected value.
     * Generic used function for quite a few possible configuration settings.
     *
     * @param string $itemName Which settings item are we accessing?.
     * @param ServerRequestInterface $request What is the request object? (for callables).
     * @param boolean $isSingle Are we expecting a single string/int as a return value.
     *
     * @throws \InvalidArgumentException If the item is invalid.
     * @return string
     */
    protected function parseItem(string $itemName, ServerRequestInterface $request, bool $isSingle = false): string
    {
        if (!\array_key_exists($itemName, $this->settings)) {
            throw new \InvalidArgumentException('Missing setting for ' . $itemName);
        }
        $item = $this->settings[$itemName];
        // we allow callables to be set (along with strings) so we can vary things upon requests.
        if (true === \is_callable($item)) {
            // all callbacks are made with the request as the second parameter.
            $item=$item($request);
        }

        // if it is a boolean, we may as well return.
        if (false === $item || null === $item) {
            return '';
        }
        if (true === $item) {
            throw new \InvalidArgumentException('Cannot have true as a setting for ' . $itemName);
        }

        // if it is a string, convert it into an array based on position of commas - but trim excess white spaces.
        if (true === \is_string($item)) {
            $item = array_map('trim', explode(',', $item));
        }

        // are we expecting a single item to be returned?
        if (true === $isSingle) {
            // if we are, and it is an int, return it as a string for type casting
            if (true === \is_int($item)) {
                return (string)$item;
            }
            if (1===count($item)) {
                // if we have a single item, return it.
                return (string)$item[0];
            }
            throw new \InvalidArgumentException('Only expected a single string, int or bool');
        }

        // we always want to work on arrays
        // explode the string and trim it.
        if (false === \is_array($item)) {
            $item = array_map('trim', explode(',', (string)$item));
        }

        // if it is an array, we want to return a comma space separated list
        $item = implode(', ', $item);

        // return the string setting.
        return $item;
    }

    /**
     * Parse the allow credentials setting.
     *
     * @param ServerRequestInterface $request What is the request object? (for callables).
     *
     * @throws \InvalidArgumentException If the item is missing from settings or is invalid.
     * @return boolean
     */
    protected function parseAllowCredentials(ServerRequestInterface $request): bool
    {
        if (!\array_key_exists('allowCredentials', $this->settings)) {
            throw new \InvalidArgumentException('Missing setting for allowCredentials');
        }
        // read in the current setting
        $item = $this->settings['allowCredentials'];
        // we allow callables to be set (along with strings) so we can vary things upon requests.
        if (true === \is_callable($item)) {
            // all callbacks are made with the request as the second parameter.
            $item = $item($request);
        }

        // if the credentials are still not a boolean, abort.
        if (false === \is_bool($item)) {
            throw new \InvalidArgumentException('allowCredentials should be a boolean value');
        }

        // return the boolean credentials setting
        return $item;
    }

    /**
     * Parse the maxAge setting.
     *
     * @param ServerRequestInterface $request What is the request object? (for callables).
     *
     * @throws \InvalidArgumentException If the item is missing from settings or is invalid.
     * @return integer
     */
    protected function parseMaxAge(ServerRequestInterface $request): int
    {
        if (!\array_key_exists('maxAge', $this->settings)) {
            throw new \InvalidArgumentException('Missing setting for maxAge');
        }
        $item = $this->settings['maxAge'];
        // we allow callables to be set (along with strings) so we can vary things upon requests.
        if (true === \is_callable($item)) {
            // all callbacks are made with the request as the second parameter.
            $item = $item($request);
        }

        // maxAge needs to be an int - if it isn't, throw an exception.
        if (false === \is_int($item)) {
            throw new \InvalidArgumentException('maxAge should be an int value');
        }

        // if it is less than zero, reject it as "faulty"
        if ($item < 0) {
            throw new \InvalidArgumentException('maxAge should be 0 or more');
        }

        // return our integer maximum age to cache.
        return $item;
    }

    /**
     * Parse the origin setting using wildcards where necessary.
     * Can return * for "all hosts", '' for "no origin/do not allow" or a string/hostname.
     *
     * @param ServerRequestInterface $request The server request with the origin header.
     * @param array|callable $allowedOrigins The returned list of allowed origins found.
     *
     * @return string
     *
     * @throws \InvalidArgumentException If settings are missing.
     */
    protected function parseOrigin(ServerRequestInterface $request, array &$allowedOrigins = []): string
    {
        if (!\array_key_exists('origin', $this->settings)) {
            throw new \InvalidArgumentException('Missing setting for origin');
        }
        // read the client provided origin header
        $origin = $request->getHeaderLine('origin');
        // if it isn't a string or is empty, the return as we will not have a matching
        // origin setting.
        if (false === \is_string($origin) || '' === $origin) {
            $this->logger->debug('Origin is empty or is not a string');
            return '';
        }

        $this->logger->debug('Processing origin of "' . $origin . '"');
        // lowercase the user provided origin for comparison purposes.
        $origin = strtolower($origin);
        $parsed = parse_url($origin);
        $originHost = $origin;
        if (true === \is_array($parsed)) {
            if (true === isset($parsed['host'])) {
                $this->logger->debug('Parsed a hostname from origin: ' . $parsed['host']);
                $originHost = $parsed['host'];
            }
        } else {
            $parsed = [];
        }
        // read the current origin setting
        $originSetting = $this->settings['origin'];

        // see if this is a callback
        if (true === \is_callable($originSetting)) {
            // all callbacks are made with the request as the second parameter.
            $this->logger->debug('Origin server request is being passed to callback');
            $originSetting = $originSetting($request);
        }

        // set a dummy "matched with" setting
        $matched = '';
        // if it is an array (either set via configuration or returned via the call
        // back), look through them.
        if (true === \is_array($originSetting)) {
            $this->logger->debug('Iterating through Origin array');
            /* @var string[] $originSetting */
            foreach ($originSetting as $item) {
                $allowedOrigins[] = $item;
                // see if the origin matches (the parseOriginMatch function supports
                // wildcards)
                $matched = $this->parseOriginMatch($item, $originHost);
                // if anything else but '' was returned, then we have a valid match.
                if ('' !== $matched) {
                    $this->logger->debug('Iterator found a matched origin of ' . $matched);
                    $matched = $this->addProtocolPortIfNeeded($matched, $parsed);
                    return $matched;
                }
            }
        }

        // if we've got this far, than nothing so far has matched, our last attempt
        // is to try to match it as a string (if applicable)
        /* @var string $originSetting */
        if ('' === $matched && true === \is_string($originSetting)) {
            $this->logger->debug('Attempting to match origin as string');
            $allowedOrigins[] = $originSetting;
            $matched = $this->parseOriginMatch($originSetting, $originHost);
        }

        // return the matched setting (may be '' to indicate nothing matched)
        $matched = $this->addProtocolPortIfNeeded($matched, $parsed);
        return $matched;
    }

    /**
     * Returns the protocol if needed.
     *
     * @param string $matched The matched host.
     * @param array $parsed The results of parse_url.
     *
     * @return string
     */
    protected function addProtocolPortIfNeeded(string $matched, array $parsed): string
    {
        if ('' === $matched || '*' === $matched) {
            $return = $matched;

            return $return;
        }
        $protocol = 'https://';
        $port = 0;
        if (true === isset($parsed['scheme'])) {
            $this->logger->debug('Parsed a protocol from origin: ' . $parsed['scheme']);
            $protocol = $parsed['scheme'] . '://';
        } else {
            $this->logger->debug('Unable to parse protocol/scheme from origin');
        }
        if (true === isset($parsed['port'])) {
            $this->logger->debug('Parsed a port from origin: ' . $parsed['port']);
            $port = (int)$parsed['port'];
        } else {
            $this->logger->debug('Unable to parse port from origin');
        }

        if (0 === $port) {
            if ('https://' === $protocol) {
                $port = 443;
            } else {
                $port = 80;
            }
        }

        if (('http://' === $protocol && 80 === $port) || ('https://' === $protocol && 443 === $port)) {
            $return = $protocol . $matched;
        } else {
            $return = $protocol . $matched . ':' . $port;
        }
        return $return;
    }

    /**
     * Check to see if an origin string matches an item (wildcarded or not).
     *
     * @param string $item The string (possible * wildcarded) to compare against.
     * @param string $origin The origin to check.
     *
     * @return string The matching origin (can be *) or '' for empty/not matched
     */
    protected function parseOriginMatch(string $item, string $origin): string
    {
        $this->logger->debug('Checking configuration origin of "' . $item . '" against user "' . $origin . '"');
        if ('' === $item || '*' === $item) {
            $this->logger->debug('Origin is either an empty string or wildcarded star. Returning ' . $item);
            return $item;
        }

        // host names are case insensitive, so lower case it.
        $item = strtolower($item);
        // if the item does NOT contain a star, make a straight comparison
        if (false === strpos($item, '*')) {
            if ($item === $origin) {
                // if we have a match, then return.
                $this->logger->debug('Origin is an exact case insensitive match');
                return $origin;
            }
        } else {
            // item contains one or more stars/wildcards
            // ensure we have no preg characters in the item
            $quoted = preg_quote($item, '/');
            // replace the preg_quote escaped star with .*
            $quoted = str_replace('\*', '.*', $quoted);
            // see if we have a preg_match, and, if we do, return it.
            if (1 === preg_match('/^' . $quoted . '$/', $origin)) {
                $this->logger->debug('Wildcarded origin match with ' . $origin);
                return $origin;
            }
        }

        // if nothing is matched, then return an empty string.
        $this->logger->debug('Unable to match "' . $item . '" against user "' . $origin . '"');
        return '';
    }
}
