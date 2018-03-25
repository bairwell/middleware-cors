<?php
/**
 * Validate.
 *
 * All the CORs orientated settings validation code.
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

use Bairwell\MiddlewareCors\Exceptions\BadOrigin;
use Bairwell\MiddlewareCors\Exceptions\SettingsInvalid;
use Psr\Http\Message\RequestInterface;

/**
 * Validate settings.
 *
 * All the CORs orientated validation code.
 */
class ValidateSettings
{
    /**
     * A list of allowed settings and their parameters/types.
     *
     * @var array
     */
    protected static $allowedSettings = [
        'exposeHeaders' => ['string', 'array', 'callable'],
        'allowMethods' => ['string', 'array', 'callable'],
        'allowHeaders' => ['string', 'array', 'callable'],
        'origin' => ['string', 'array', 'callable'],
        'maxAge' => ['int', 'callable'],
        'allowCredentials' => ['bool', 'callable'],
        'badOriginCallable'=>['callable']
    ];

    /**
     * Get the default settings.
     *
     * @return array
     *
     */
    public static function getDefaults(): array
    {
        // our default settings
        $return = [
            'origin' => '*',
            'exposeHeaders' => '',
            'maxAge' => 0,
            'allowCredentials' => false,
            'allowMethods' => 'GET,HEAD,PUT,POST,DELETE',
            'allowHeaders' => '',
            'badOriginCallable'=>function (RequestInterface $request, array $allowedOrigins) {
                $exception = new BadOrigin('Bad Origin');
                $exception->setSent($request->getHeaderLine('origin'));
                $exception->setAllowed($allowedOrigins);
                throw $exception;
            }
        ];

        return $return;
    }

    /**
     * Validate settings.
     *
     * @param array $settings The settings.
     *
     * @throws SettingsInvalid If the data is incorrect.
     */
    public static function validate(array $settings = [])
    {
        foreach (self::$allowedSettings as $name => $allowed) {
            if (!array_key_exists($name, $settings)) {
                throw new SettingsInvalid(
                    'Missing setting: ' . $name
                );
            }
            $value = $settings[$name];
            if ((false === self::validateString($value, $allowed))
                && (false === self::validateArray($name, $value, $allowed))
                && (false === self::validateCallable($value, $allowed))
                && (false === self::validateInt($name, $value, $allowed))
                && (false === self::validateBool($value, $allowed))
            ) {
                $type = \gettype($value);
                if (\is_object($value)) {
                    $type = \get_class($value);
                }
                throw new SettingsInvalid(\sprintf(
                    'Unable to validate settings for %1$s: got type of %2$s - expected one of: %3$s',
                    $name,
                    $type,
                    implode(', ', $allowed)
                ));
            }
        }
    }

    /**
     * Validates an bool setting.
     *
     * @param mixed $value The value we are validating.
     * @param array $allowed Which items are allowed.
     *
     * @return bool True if validated, false if not
     */
    final protected static function validateBool($value, array $allowed): bool
    {
        return (true === \is_bool($value) && true === \in_array('bool', $allowed, true));
    }

    /**
     * Validates an string setting.
     *
     * @param mixed $value The value we are validating.
     * @param array $allowed Which items are allowed.
     *
     * @return bool True if validated, false if not
     */
    final protected static function validateString($value, array $allowed): bool
    {
        return (true === \is_string($value) && true === \in_array('string', $allowed, true)) ;
    }

    /**
     * Validates an callable setting.
     *
     * @param mixed $value The value we are validating.
     * @param array $allowed Which items are allowed.
     *
     * @return bool True if validated, false if not
     */
    final protected static function validateCallable($value, array $allowed): bool
    {
        return (true === \is_callable($value) && true === \in_array('callable', $allowed, true));
    }

    /**
     * Validates an int setting.
     *
     * @param string $name The name of the setting we are validating.
     * @param mixed $value The value we are validating.
     * @param array $allowed Which items are allowed.
     *
     * @throws SettingsInvalid If the data is inaccurate/incorrect.
     * @return bool True if validated, false if not
     */
    final protected static function validateInt(string $name, $value, array $allowed): bool
    {
        if (true === \is_int($value) && true === \in_array('int', $allowed, true)) {
            if ($value >= 0) {
                return true;
            }
                throw new SettingsInvalid('Int value for ' . $name . ' is too low');
        }

        return false;
    }

    /**
     * Validates an array setting.
     *
     * @param string $name The name of the setting we are validating.
     * @param mixed $value The value we are validating.
     * @param array $allowed Which items are allowed.
     *
     * @throws SettingsInvalid If the data is inaccurate/incorrect.
     * @return bool True if validated, false if not
     */
    final protected static function validateArray(string $name, $value, array $allowed): bool
    {
        if (true === \is_array($value) && true === \in_array('array', $allowed, true)) {
            if (true === empty($value)) {
                throw new SettingsInvalid('Array for ' . $name . ' is empty');
            }
                /* @var string[] $value */
            foreach ($value as $line) {
                if (false === \is_string($line)) {
                    throw new SettingsInvalid('Array for ' . $name . ' contains a non-string item');
                }
            }
                return true;
        }

        return false;
    }
}
