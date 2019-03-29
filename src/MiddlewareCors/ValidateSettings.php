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
declare (strict_types = 1);

namespace Bairwell\MiddlewareCors;

/**
 * Validate settings.
 *
 * All the CORs orientated validation code.
 */
class ValidateSettings
{

    /**
     * Validate a setting.
     *
     * @param string $name    The name of the setting we are validating.
     * @param mixed  $value   The value we are validating.
     * @param array  $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is incorrect.
     */
    public function __invoke(string $name, $value, array $allowed)
    {
        if ((true === $this->validateString($value, $allowed))
            || (true === $this->validateArray($name, $value, $allowed))
            || (true === $this->validateCallable($value, $allowed))
            || (true === $this->validateInt($name, $value, $allowed))
            || (true === $this->validateBool($value, $allowed))
        ) {
            return;
        }

        throw new \InvalidArgumentException(
            'Unable to validate settings for '.$name.': allowed types: '.implode(', ', $allowed)
        );
    }//end __invoke()

    /**
     * Validates an bool setting.
     *
     * @param mixed $value   The value we are validating.
     * @param array $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return bool True if validated, false if not
     */
    final protected function validateBool($value, array $allowed) : bool
    {
        if (true === in_array('bool', $allowed)) {
            if (true === is_bool($value)) {
                return true;
            }
        }

        return false;
    }//end validateBool()

    /**
     * Validates an string setting.
     *
     * @param mixed $value   The value we are validating.
     * @param array $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return bool True if validated, false if not
     */
    final protected function validateString($value, array $allowed) : bool
    {
        if (true === in_array('string', $allowed)) {
            if (true === is_string($value)) {
                return true;
            }
        }

        return false;
    }//end validateString()

    /**
     * Validates an callable setting.
     *
     * @param mixed $value   The value we are validating.
     * @param array $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return bool True if validated, false if not
     */
    final protected function validateCallable($value, array $allowed) : bool
    {
        if (true === in_array('callable', $allowed)) {
            if (true === is_callable($value)) {
                return true;
            }
        }

        return false;
    }//end validateCallable()

    /**
     * Validates an int setting.
     *
     * @param string $name    The name of the setting we are validating.
     * @param mixed  $value   The value we are validating.
     * @param array  $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return bool True if validated, false if not
     */
    final protected function validateInt(string $name, $value, array $allowed) : bool
    {
        if (true === in_array('int', $allowed)) {
            if (true === is_int($value)) {
                if ($value >= 0) {
                    return true;
                } else {
                    throw new \InvalidArgumentException('Int value for '.$name.' is too low');
                }
            }
        }

        return false;
    }//end validateInt()

    /**
     * Validates an array setting.
     *
     * @param string $name    The name of the setting we are validating.
     * @param mixed  $value   The value we are validating.
     * @param array  $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return bool True if validated, false if not
     */
    final protected function validateArray(string $name, $value, array $allowed) : bool
    {
        if (true === in_array('array', $allowed)) {
            if (true === is_array($value)) {
                if (true === empty($value)) {
                    throw new \InvalidArgumentException('Array for '.$name.' is empty');
                } else {
                    foreach ($value as $line) {
                        if (false === is_string($line)) {
                            throw new \InvalidArgumentException('Array for '.$name.' contains a non-string item');
                        }
                    }

                    return true;
                }
            }
        }

        return false;
    }//end validateArray()
}//end class
