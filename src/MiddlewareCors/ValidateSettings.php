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
        if (($this->validateString($value, $allowed))
            || ($this->validateArray($name, $value, $allowed))
            || ($this->validateCallable($value, $allowed))
            || ($this->validateInt($name, $value, $allowed))
            || ($this->validateBool($value, $allowed))
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
     * @return boolean True if validated, false if not
     */
    final protected function validateBool($value, array $allowed): bool
    {
        return is_bool($value) && in_array('bool', $allowed);
    }//end validateBool()

    /**
     * Validates an string setting.
     *
     * @param mixed $value   The value we are validating.
     * @param array $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return boolean True if validated, false if not
     */
    final protected function validateString($value, array $allowed): bool
    {
        return is_string($value) && in_array('string', $allowed);
    }//end validateString()

    /**
     * Validates an callable setting.
     *
     * @param mixed $value   The value we are validating.
     * @param array $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return boolean True if validated, false if not
     */
    final protected function validateCallable($value, array $allowed): bool
    {
        return is_callable($value) && in_array('callable', $allowed);
    }//end validateCallable()

    /**
     * Validates an int setting.
     *
     * @param string $name    The name of the setting we are validating.
     * @param mixed  $value   The value we are validating.
     * @param array  $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return boolean True if validated, false if not
     */
    final protected function validateInt(string $name, $value, array $allowed): bool
    {
        if (is_int($value) && in_array('int', $allowed)) {
            if ($value >= 0) {
                return true;
            }

            throw new \InvalidArgumentException('Int value for '.$name.' is too low');
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
     * @return boolean True if validated, false if not
     */
    final protected function validateArray(string $name, $value, array $allowed): bool
    {
        if (is_array($value) && in_array('array', $allowed)) {
            if (empty($value)) {
                throw new \InvalidArgumentException('Array for '.$name.' is empty');
            }

            foreach ($value as $line) {
                if (!is_string($line)) {
                    throw new \InvalidArgumentException('Array for '.$name.' contains a non-string item');
                }
            }

            return true;
        }

        return false;
    }//end validateArray()
}//end class
