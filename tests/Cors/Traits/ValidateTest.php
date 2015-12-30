<?php
/**
 * Class CorsTest validate.
 *
 * Tests the CORs middleware layer.
 */
declare (strict_types = 1);

namespace Bairwell\Cors\Traits;

use Bairwell\Cors;

/**
 * Class CorsTest validate.
 *
 * Tests the CORs middleware layer.
 *
 * @uses \Bairwell\Cors
 * @uses \Bairwell\Cors\Traits\Parse
 * @uses \Bairwell\Cors\Traits\Validate
 */
class ValidateTest extends \PHPUnit_Framework_TestCase
{
    /**
     * The allowed settings.
     *
     * @var array
     */
    private $allowedSettings;

    /**
     * Setup for PHPUnit.
     */
    public function setUp()
    {
        $this->allowedSettings = [
            'exposeHeaders'    => ['string', 'array', 'callable'],
            'allowMethods'     => ['string', 'array', 'callable'],
            'allowHeaders'     => ['string', 'array', 'callable'],
            'origin'           => ['string', 'array', 'callable'],
            'maxAge'           => ['int', 'callable'],
            'allowCredentials' => ['bool', 'callable'],
            'blockedCallback'  => ['callable']
        ];
    }//end setUp()
    /**
     * Covers checking the validation settings.
     *
     * @test
     * @covers \Bairwell\Cors::validateSetting
     * @covers \Bairwell\Cors::validateSettingString
     * @covers \Bairwell\Cors::validateSettingArray
     * @covers \Bairwell\Cors::validateSettingCallable
     * @covers \Bairwell\Cors::validateSettingInt
     * @covers \Bairwell\Cors::validateSettingBool
     */
    public function testValidateSettings()
    {
        $sut              = new Cors();
        $reflection       = new \ReflectionClass(get_class($sut));
        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        $method = $reflection->getMethod('validateSetting');
        $method->setAccessible(true);
        // general tests
        $testData = [
            ['notOn' => 'string', 'value' => 'abc'],
            ['notOn' => 'string', 'value' => '123'],
            ['notOn' => 'int', 'value' => 123],
            ['notOn' => 'bool', 'value' => true],
            ['notOn' => 'bool', 'value' => false],
            [
                'notOn' => 'callable',
                'value' => function () {
                }
            ],
            ['notOn' => 'array', 'value' => ['abc', 'def']]
        ];
        foreach ($this->allowedSettings as $k => $allowed) {
            foreach ($testData as $data) {
                try {
                    $method->invokeArgs($sut, [$k, $data['value'], $allowed]);
                } catch (\InvalidArgumentException $e) {
                    if (true === in_array($data['notOn'], $allowed)) {
                        $this->fail('Failed to test '.$k.' correctly: rejected when passing value:'.$e->getMessage());
                    } else {
                        $expected = 'Unable to validate settings for '.$k.': allowed types: '.implode(', ', $allowed);
                        $this->assertSame($expected, $e->getMessage());
                    }
                }
            }
        }

        // specific tests
        try {
            $method->invokeArgs($sut, ['test', [], ['array']]);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Array for test is empty', $e->getMessage());
        }

        // non-stringed array (containing another array)
        try {
            $method->invokeArgs($sut, ['test', ['abc', '123', []], ['array']]);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Array for test contains a non-string item', $e->getMessage());
        }

        // non-stringed array (containing int)
        try {
            $method->invokeArgs($sut, ['test', ['abc', 123], ['array']]);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Array for test contains a non-string item', $e->getMessage());
        }

        // int is too low
        try {
            $method->invokeArgs($sut, ['test', -1, ['int']]);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Int value for test is too low', $e->getMessage());
        }
    }//end testValidateSettings()
}//end class
