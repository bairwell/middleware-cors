<?php
/**
 * Trait Validate.
 *
 * All the CORs orientated validation code.
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

/**
 * Trait Validate.
 *
 * All the CORs orientated validation code.
 */
trait Validate
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
    protected function validateSetting(string $name, $value, array $allowed)
    {
        if ((true === $this->validateSettingString($name, $value, $allowed))
            || (true === $this->validateSettingArray($name, $value, $allowed))
            || (true === $this->validateSettingCallable($name, $value, $allowed))
            || (true === $this->validateSettingInt($name, $value, $allowed))
            || (true === $this->validateSettingBool($name, $value, $allowed))
        ) {
            return;
        }

        throw new \InvalidArgumentException(
            'Unable to validate settings for '.$name.': allowed types: '.implode(', ', $allowed)
        );
    }//end validateSetting()

    /**
     * Validates an bool setting.
     *
     * @param string $name    The name of the setting we are validating.
     * @param mixed  $value   The value we are validating.
     * @param array  $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return bool True if validated, false if not
     */
    protected function validateSettingBool(string $name, $value, array $allowed) : bool
    {
        if (true === in_array('bool', $allowed)) {
            if (true === is_bool($value)) {
                return true;
            }
        }

        return false;
    }//end validateSettingBool()

    /**
     * Validates an string setting.
     *
     * @param string $name    The name of the setting we are validating.
     * @param mixed  $value   The value we are validating.
     * @param array  $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return bool True if validated, false if not
     */
    protected function validateSettingString(string $name, $value, array $allowed) : bool
    {
        if (true === in_array('string', $allowed)) {
            if (true === is_string($value)) {
                return true;
            }
        }

        return false;
    }//end validateSettingString()

    /**
     * Validates an callable setting.
     *
     * @param string $name    The name of the setting we are validating.
     * @param mixed  $value   The value we are validating.
     * @param array  $allowed Which items are allowed.
     *
     * @throws \InvalidArgumentException If the data is inaccurate/incorrect.
     * @return bool True if validated, false if not
     */
    protected function validateSettingCallable(string $name, $value, array $allowed) : bool
    {
        if (true === in_array('callable', $allowed)) {
            if (true === is_callable($value)) {
                return true;
            }
        }

        return false;
    }//end validateSettingCallable()

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
    protected function validateSettingInt(string $name, $value, array $allowed) : bool
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
    }//end validateSettingInt()

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
    protected function validateSettingArray(string $name, $value, array $allowed) : bool
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
    }//end validateSettingArray()
}
