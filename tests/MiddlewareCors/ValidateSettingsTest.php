<?php
/**
 * Class MiddlewareCorsTest validate.
 *
 * Tests the MiddlewareCors middleware layer.
 */

namespace Bairwell\MiddlewareCors\Traits;

use Bairwell\MiddlewareCors;

/**
 * Class MiddlewareCorsTest validate.
 *
 * Tests the MiddlewareCors middleware layer.
 *
 * @uses \Bairwell\MiddlewareCors\ValidateSettings
 */
class ValidateSettingsTest extends \PHPUnit_Framework_TestCase
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
            'exposeHeaders' => ['string', 'array', 'callable'],
            'allowMethods' => ['string', 'array', 'callable'],
            'allowHeaders' => ['string', 'array', 'callable'],
            'origin' => ['string', 'array', 'callable'],
            'maxAge' => ['int', 'callable'],
            'allowCredentials' => ['bool', 'callable'],
            'blockedCallback' => ['callable']
        ];
    }//end setUp()
    /**
     * Covers checking the validation settings.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\ValidateSettings::__invoke
     * @covers \Bairwell\MiddlewareCors\ValidateSettings::validateString
     * @covers \Bairwell\MiddlewareCors\ValidateSettings::validateArray
     * @covers \Bairwell\MiddlewareCors\ValidateSettings::validateCallable
     * @covers \Bairwell\MiddlewareCors\ValidateSettings::validateInt
     * @covers \Bairwell\MiddlewareCors\ValidateSettings::validateBool
     */
    public function testValidateSettings()
    {
        $sut = new MiddlewareCors\ValidateSettings();
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
                    $sut($k, $data['value'], $allowed);
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
            $sut('test', [], ['array']);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Array for test is empty', $e->getMessage());
        }

        // non-stringed array (containing another array)
        try {
            $sut('test', ['abc', '123', []], ['array']);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Array for test contains a non-string item', $e->getMessage());
        }

        // non-stringed array (containing int)
        try {
            $sut('test', ['abc', 123], ['array']);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Array for test contains a non-string item', $e->getMessage());
        }

        // int is too low
        try {
            $sut('test', -1, ['int']);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Int value for test is too low', $e->getMessage());
        }
    }//end testValidateSettings()
}//end class
