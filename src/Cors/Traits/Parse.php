<?php
/**
 * Trait Parse.
 *
 * All the CORs orientated parsing code.
 *
 * Part of the Bairwell\Cors package.
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types = 1);

namespace Bairwell\Cors\Traits;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Trait Parse.
 * All the CORs orientated parsing code.
 */
trait Parse
{

    /**
     * Add a log string if we have a logger.
     *
     * @param string $string String to log.
     *
     * @return bool True if logged, false if no logger.
     */
    abstract protected function addLog(string $string) : bool;

    /**
     * Parse an item from string/int/callable/array to an expected value.
     * Generic used function for quite a few possible configuration settings.
     *
     * @param string                 $itemName Which settings item are we accessing?.
     * @param ServerRequestInterface $request  What is the request object? (for callables).
     * @param boolean                $isSingle Are we expecting a single string/int as a return value.
     *
     * @throws \InvalidArgumentException If the item is invalid.
     * @return string
     */
    protected function parseItem(string $itemName, ServerRequestInterface $request, bool $isSingle = false) : string
    {
        $item = $this->settings[$itemName];
        // we allow callables to be set (along with strings) so we can vary things upon requests.
        if (true === is_callable($item)) {
            // all callbacks are made with the request as the second parameter.
            $item = call_user_func($item, $request);
        }

        // if it is a boolean, we may as well return.
        if (false === $item || null === $item) {
            return '';
        } elseif (true === $item) {
            throw new \InvalidArgumentException('Cannot have true as a setting for '.$itemName);
        }

        // if it is a string, convert it into an array based on position of commas - but trim excess white spaces.
        if (true === is_string($item)) {
            $item = array_map('trim', explode(',', (string) $item));
        }

        // are we expecting a single item to be returned?
        if (true === $isSingle) {
            // if we are, and it is an int, return it as a string for type casting
            if (true === is_int($item)) {
                return (string) $item;
            } elseif (count($item) === 1) {
                // if we have a single item, return it.
                return (string) $item[0];
            } else {
                throw new \InvalidArgumentException('Only expected a single string, int or bool');
            }
        }

        // we always want to work on arrays
        // explode the string and trim it.
        if (false === is_array($item)) {
            $item = array_map('trim', explode(',', (string) $item));
        }

        // if it is an array, we want to return a comma space separated list
        $item = implode(', ', $item);

        // return the string setting.
        return $item;
    }//end parseItem()

    /**
     * Parse the allow credentials setting.
     *
     * @param ServerRequestInterface $request What is the request object? (for callables).
     *
     * @throws \InvalidArgumentException If the item is missing from settings or is invalid.
     * @return boolean
     */
    protected function parseAllowCredentials(ServerRequestInterface $request) : bool
    {
        // read in the current setting
        $item = $this->settings['allowCredentials'];
        // we allow callables to be set (along with strings) so we can vary things upon requests.
        if (true === is_callable($item)) {
            // all callbacks are made with the request as the second parameter.
            $item = call_user_func($item, $request);
        }

        // if the credentials are still not a boolean, abort.
        if (false === is_bool($item)) {
            throw new \InvalidArgumentException('allowCredentials should be a boolean value');
        }

        // return the boolean credentials setting
        return $item;
    }//end parseAllowCredentials()

    /**
     * Parse the maxAge setting.
     *
     * @param ServerRequestInterface $request What is the request object? (for callables).
     *
     * @throws \InvalidArgumentException If the item is missing from settings or is invalid.
     * @return integer
     */
    protected function parseMaxAge(ServerRequestInterface $request) : int
    {
        $item = $this->settings['maxAge'];
        // we allow callables to be set (along with strings) so we can vary things upon requests.
        if (true === is_callable($item)) {
            // all callbacks are made with the request as the second parameter.
            $item = call_user_func($item, $request);
        }

        // maxAge needs to be an int - if it isn't, throw an exception.
        if (false === is_int($item)) {
            throw new \InvalidArgumentException('maxAge should be an int value');
        }

        // if it is less than zero, reject it as "faulty"
        if ($item < 0) {
            throw new \InvalidArgumentException('maxAge should be 0 or more');
        }

        // return our integer maximum age to cache.
        return $item;
    }//end parseMaxAge()

    /**
     * Parse the origin setting using wildcards where necessary.
     * Can return * for "all hosts", '' for "no origin/do not allow" or a string/hostname.
     *
     * @param ServerRequestInterface $request The server request with the origin header.
     *
     * @return string
     */
    protected function parseOrigin(ServerRequestInterface $request) : string
    {
        // read the client provided origin header
        $origin = $request->getHeaderLine('origin');
        // if it isn't a string or is empty, the return as we will not have a matching
        // origin setting.
        if (false === is_string($origin) || '' === $origin) {
            $this->addLog('Origin is empty or is not a string');
            return '';
        }

        $this->addLog('Processing origin of "'.$origin.'"');
        // lowercase the user provided origin for comparison purposes.
        $origin = strtolower($origin);
        // read the current origin setting
        $originSetting = $this->settings['origin'];

        // see if this is a callback
        if (true === is_callable($originSetting)) {
            // all callbacks are made with the request as the second parameter.
            $this->addLog('Origin server request is being passed to callback');
            $originSetting = call_user_func($originSetting, $request);
        }

        // set a dummy "matched with" setting
        $matched = '';
        // if it is an array (either set via configuration or returned via the call
        // back), look through them.
        if (true === is_array($originSetting)) {
            $this->addLog('Iterating through Origin array');
            foreach ($originSetting as $item) {
                // see if the origin matches (the parseOriginMatch function supports
                // wildcards)
                $matched = $this->parseOriginMatch($item, $origin);
                // if anything else but '' was returned, then we have a valid match.
                if ('' !== $matched) {
                    $this->addLog('Iterator found a matched origin of '.$matched);
                    return $matched;
                }
            }
        }

        // if we've got this far, than nothing so far has matched, our last attempt
        // is to try to match it as a string (if applicable)
        if ('' === $matched && true === is_string($originSetting)) {
            $this->addLog('Attempting to match origin as string');
            $matched = $this->parseOriginMatch($originSetting, $origin);
        }

        // return the matched setting (may be '' to indicate nothing matched)
        return $matched;
    }//end parseOrigin()

    /**
     * Check to see if an origin string matches an item (wildcarded or not).
     *
     * @param string $item   The string (possible * wildcarded) to compare against.
     * @param string $origin The origin to check.
     *
     * @return string The matching origin (can be *) or '' for empty/not matched
     */
    protected function parseOriginMatch(string $item, string $origin) :string
    {
        $this->addLog('Checking configuration origin of "'.$item.'" against user "'.$origin.'"');
        if ('' === $item || '*' === $item) {
            $this->addLog('Origin is either an empty string or wildcarded star. Returning '.$item);
            return $item;
        }

        // host names are case insensitive, so lower case it.
        $item = strtolower($item);
        // if the item does NOT contain a star, make a straight comparison
        if (false === strpos($item, '*')) {
            if ($item === $origin) {
                // if we have a match, then return.
                $this->addLog('Origin is an exact case insensitive match');
                return $origin;
            }
        } else {
            // item contains one or more stars/wildcards
            // ensure we have no preg characters in the item
            $quoted = preg_quote($item, '/');
            // replace the preg_quote escaped star with .*
            $quoted = str_replace('\*', '.*', $quoted);
            // see if we have a preg_match, and, if we do, return it.
            if (1 === preg_match('/^'.$quoted.'$/', $origin)) {
                $this->addLog('Wildcarded origin match with '.$origin);
                return $origin;
            }
        }

        // if nothing is matched, then return an empty string.
        $this->addLog('Unable to match "'.$item.'" against user "'.$origin.'"');
        return '';
    }//end parseOriginMatch()
}
