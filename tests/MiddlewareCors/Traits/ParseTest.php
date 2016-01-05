<?php
/**
 * Class MiddlewareCorsTest parse..
 *
 * Tests the MiddlewareCors middleware layer.
 */
declare (strict_types = 1);

namespace Bairwell\MiddlewareCors\Traits;

use Bairwell\MiddlewareCors;
use Psr\Log\LoggerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

/**
 * Class MiddlewareCorsTest.
 * Tests the MiddlewareCors middleware layer.
 *
 * @uses \Bairwell\MiddlewareCors
 * @uses \Bairwell\MiddlewareCors\Traits\Parse
 * @uses \Bairwell\MiddlewareCors\Preflight
 * @uses \Bairwell\MiddlewareCors\ValidateSettings
 */
class ParseTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Logger.
     *
     * @var LoggerInterface $logger
     */
    protected $logger;

    /**
     * Test handler logger.
     *
     * @var \Monolog\Handler\TestHandler $testLogger
     */
    protected $testLogger;

    /**
     * Test the parse item section - strings
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseItem
     */
    public function testParseItemArrays()
    {
        // first allow multiple requests
        $this->parseItem(['fred', 'george', 'jane'], 'fred, george, jane', false);
        // now only allow one.
        $this->parseItem(['hello'], 'hello', true);
        try {
            $this->parseItem(['hello', 'my', 'honey'], 'hello,my,honey', true);
            $this->fail('Should have blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Only expected a single string, int or bool', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '.$e->getMessage());
        }
    }//end testParseItemArrays()

    /**
     * Test the parse item section - strings
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseItem
     */
    public function testParseItemStrings()
    {
        // first allow multiple requests
        $this->parseItem('hello, my, honey', 'hello, my, honey', false);
        $this->parseItem('hello, my,honey', 'hello, my, honey', false);
        $this->parseItem('rain,bow,climbing', 'rain, bow, climbing', false);
        // now only allow one.
        $this->parseItem('hello', 'hello', true);
        try {
            $this->parseItem('hello,my,honey', 'hello,my,honey', true);
            $this->fail('Should have blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Only expected a single string, int or bool', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '.$e->getMessage());
        }
    }//end testParseItemStrings()

    /**
     * Test the parse item section - bools
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseItem
     */
    public function testParseItemBools()
    {
        // first allow multiple requests
        $this->parseItem(false, '', false);
        try {
            $this->parseItem(true, '', false);
            $this->fail('Should have blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Cannot have true as a setting for testItem', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '.$e->getMessage());
        }

        // now only allow one: doesn't really make a difference for bools
        $this->parseItem(false, '', true);
        try {
            $this->parseItem(true, '', true);
            $this->fail('Should have blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Cannot have true as a setting for testItem', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '.$e->getMessage());
        }
    }//end testParseItemBools()

    /**
     * Test the parse item section - ints
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseItem
     */
    public function testParseItemInts()
    {
        // first allow multiple requests
        $this->parseItem(3, '3', false);

        $this->parseItem(123, '123', false);
        // now only allow one: doesn't really make a difference for ints
        $this->parseItem(13, '13', true);

        $this->parseItem(12993, '12993', true);
    }//end testParseItemInts()

    /**
     * Test the parse item section - callables
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseItem
     */
    public function testParseItemCallablesAsStrings()
    {
        // first allow multiple requests
        $callable = function () {
            return 'hello, my,honey';
        };
        $this->parseItem($callable, 'hello, my, honey', false);

        $callable = function () {
            return ['fred', 'george', 'jane'];
        };
        $this->parseItem($callable, 'fred, george, jane', false);

        $callable = function () {
            return 'rain,bow,climbing';
        };
        $this->parseItem($callable, 'rain, bow, climbing', false);
        // now only allow one.
        $callable = function () {
            return 'hello';
        };
        $this->parseItem($callable, 'hello', true);
        try {
            $callable = function () {
                return 'hello,my,honey';
            };
            $this->parseItem($callable, 'hello,my,honey', true);
            $this->fail('Should have blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Only expected a single string, int or bool', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '.$e->getMessage());
        }

        try {
            $callable = function () {
                return ['hello', 'my', 'honey'];
            };
            $this->parseItem($callable, 'hello,my,honey', true);
            $this->fail('Should have blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Only expected a single string, int or bool', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '.$e->getMessage());
        }
    }//end testParseItemCallablesAsStrings()

    /**
     * Test the parse item section - callables
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseItem
     */
    public function testParseItemCallablesAsInts()
    {
        // first allow multiple requests
        $callable = function () {
            return '1,2,3';
        };
        $this->parseItem($callable, '1, 2, 3', false);

        $callable = function () {
            return 2;
        };
        $this->parseItem($callable, '2', false);

        $callable = function () {
            return ['4', '5', '6'];
        };
        $this->parseItem($callable, '4, 5, 6', false);

        $callable = function () {
            return '7, 8, 9';
        };
        $this->parseItem($callable, '7, 8, 9', false);
        // now only allow one.
        $callable = function () {
            return '1';
        };
        $this->parseItem($callable, '1', true);
        $callable = function () {
            return 3;
        };
        $this->parseItem($callable, '3', true);
        try {
            $callable = function () {
                return '5,6,7';
            };
            $this->parseItem($callable, 'hello,my,honey', true);
            $this->fail('Should have blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Only expected a single string, int or bool', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '.$e->getMessage());
        }

        try {
            $callable = function () {
                return ['9', '10', '11'];
            };
            $this->parseItem($callable, 'hello,my,honey', true);
            $this->fail('Should have blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Only expected a single string, int or bool', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '.$e->getMessage());
        }
    }//end testParseItemCallablesAsInts()

    /**
     * Test the parse item section - callables
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseItem
     */
    public function testParseItemCallablesAsBools()
    {
        // first allow multiple requests
        $callable = function () {
            return false;
        };
        $this->parseItem($callable, '', false);
        $callable = function () {
            return;
        };
        $this->parseItem($callable, '', false);
        try {
            $callable = function () {
                return true;
            };
            $this->parseItem($callable, '', false);
            $this->fail('Should have blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Cannot have true as a setting for testItem', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '.$e->getMessage());
        }

        // now only allow one: doesn't really make a difference for bools
        $callable = function () {
            return false;
        };
        $this->parseItem($callable, '', true);
        $callable = function () {
            return;
        };
        $this->parseItem($callable, '', true);
        try {
            $callable = function () {
                return true;
            };
            $this->parseItem($callable, '', true);
            $this->fail('Should have blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Cannot have true as a setting for testItem', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '.$e->getMessage());
        }
    }//end testParseItemCallablesAsBools()

    /**
     * Test the parseAllowCredentials with callables
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseAllowCredentials
     */
    public function testParseAllowCredentialsCallables()
    {
        $sut              = new MiddlewareCors();
        $reflection       = new \ReflectionClass(get_class($sut));
        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        $method = $reflection->getMethod('parseAllowCredentials');
        $method->setAccessible(true);
        $request     = $this->getMockBuilder('Psr\Http\Message\ServerRequestInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $settingName = 'allowCredentials';

        $settingValue = function () {
            return true;
        };

        $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
        $result = $method->invokeArgs($sut, [$request]);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);

        $settingValue = function () {
            return false;
        };

        $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
        $result = $method->invokeArgs($sut, [$request]);
        $this->assertInternalType('bool', $result);
        $this->assertFalse($result);

        // check failures
        $values = ['abc', '123', null, '-1', -1];
        foreach ($values as $value) {
            try {
                $settingValue = function () use ($value) {
                    return $value;
                };

                $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
                $result = $method->invokeArgs($sut, [$request]);
                $this->fail('Should have rejected value:'.$value);
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('allowCredentials should be a boolean value', $e->getMessage());
            } catch (\Exception $e) {
                $this->fail('Invalid exception: '.$e->getMessage());
            }
        }
    }//end testParseAllowCredentialsCallables()

    /**
     * Test the parseAllowCredentials with values
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseAllowCredentials
     */
    public function testParseAllowCredentialValues()
    {
        $sut              = new MiddlewareCors();
        $reflection       = new \ReflectionClass(get_class($sut));
        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        $method = $reflection->getMethod('parseAllowCredentials');
        $method->setAccessible(true);
        $request     = $this->getMockBuilder('Psr\Http\Message\ServerRequestInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $settingName = 'allowCredentials';

        // good settings
        $settingsProperty->setValue($sut, [$settingName => true, 'def' => '567', 'ghi' => '911']);
        $result = $method->invokeArgs($sut, [$request]);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result, 'Checking true');
        $settingsProperty->setValue($sut, [$settingName => false, 'def' => '567', 'ghi' => '911']);
        $result = $method->invokeArgs($sut, [$request]);
        $this->assertInternalType('bool', $result);
        $this->assertFalse($result, 'Checking false');
        // check failures
        $values = ['abc', '123', '-1', -1];
        foreach ($values as $settingValue) {
            try {
                $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
                $result = $method->invokeArgs($sut, [$request]);
                $this->fail('Should have rejected value:'.$settingValue);
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('allowCredentials should be a boolean value', $e->getMessage());
            } catch (\Exception $e) {
                $this->fail('Invalid exception: '.$e->getMessage());
            }
        }
    }//end testParseAllowCredentialValues()

    /**
     * Test the parseAllowCredentials with callables
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseMaxAge
     */
    public function testParseMaxAgeCallables()
    {
        $sut              = new MiddlewareCors();
        $reflection       = new \ReflectionClass(get_class($sut));
        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        $method = $reflection->getMethod('parseMaxAge');
        $method->setAccessible(true);
        $request     = $this->getMockBuilder('Psr\Http\Message\ServerRequestInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $settingName = 'maxAge';

        $settingValue = function () {
            return 123;
        };

        $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
        $result = $method->invokeArgs($sut, [$request]);
        $this->assertInternalType('int', $result);
        $this->assertSame(123, $result);

        $settingValue = function () {
            return 456;
        };

        $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
        $result = $method->invokeArgs($sut, [$request]);
        $this->assertInternalType('int', $result);
        $this->assertSame(456, $result);

        // check failures
        try {
            $settingValue = function () {
                return;
            };
            $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
            $result = $method->invokeArgs($sut, [$request]);
            $this->fail('Should have rejected null');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('maxAge should be an int value', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Invalid exception: '.$e->getMessage());
        }

        try {
            $settingValue = function () {
                return -1;
            };
            $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
            $result = $method->invokeArgs($sut, [$request]);
            $this->fail('Should have rejected null');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('maxAge should be 0 or more', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Invalid exception: '.$e->getMessage());
        }

        $values = ['abc', '123', true, false, '-1'];
        foreach ($values as $value) {
            try {
                $settingValue = function () use ($value) {
                    return $value;
                };

                $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
                $result = $method->invokeArgs($sut, [$request]);
                $this->fail('Should have rejected value:'.$value);
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('maxAge should be an int value', $e->getMessage());
            } catch (\Exception $e) {
                $this->fail('Invalid exception: '.$e->getMessage());
            }
        }
    }//end testParseMaxAgeCallables()

    /**
     * Test the parseOrigin with values
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     */
    public function testParseOriginEmptyString()
    {
        $sut              = new MiddlewareCors();
        // setup the logger
        $this->logger  = new Logger('test');
        $this->testLogger = new TestHandler();
        $this->logger->pushHandler($this->testLogger);
        $sut->setLogger($this->logger);
        //
        $reflection       = new \ReflectionClass(get_class($sut));
        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        $method = $reflection->getMethod('parseOrigin');
        $method->setAccessible(true);
        $request = $this->getMockBuilder('Psr\Http\Message\ServerRequestInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('origin')
            ->willReturn('');
        $result = $method->invokeArgs($sut, [$request]);
        $this->assertSame('', $result);
        // check the logger
        $this->assertTrue($this->testLogger->hasDebugThatContains('Origin is empty or is not a string'));
    }//end testParseOriginEmptyString()

    /**
     * Test the parseOrigin with values
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     */
    public function testParseOriginInvalidString()
    {
        $sut              = new MiddlewareCors();
        // setup the logger
        $this->logger  = new Logger('test');
        $this->testLogger = new TestHandler();
        $this->logger->pushHandler($this->testLogger);
        $sut->setLogger($this->logger);
        //
        $reflection       = new \ReflectionClass(get_class($sut));
        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        $method = $reflection->getMethod('parseOrigin');
        $method->setAccessible(true);
        $request = $this->getMockBuilder('Psr\Http\Message\ServerRequestInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('origin')
            ->willReturn(123);
        $result = $method->invokeArgs($sut, [$request]);
        $this->assertSame('', $result);
        // check the logger
        $this->assertTrue($this->testLogger->hasDebugThatContains('Origin is empty or is not a string'));
    }//end testParseOriginInvalidString()

    /**
     * Test the parseMaxAge with values
     * Uses reflection as this is a protected method.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseMaxAge
     */
    public function testParseMaxAgeValues()
    {
        $sut              = new MiddlewareCors();
        $reflection       = new \ReflectionClass(get_class($sut));
        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        $method = $reflection->getMethod('parseMaxAge');
        $method->setAccessible(true);
        $request     = $this->getMockBuilder('Psr\Http\Message\ServerRequestInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $settingName = 'maxAge';

        // good settings
        $settingsProperty->setValue($sut, [$settingName => 234, 'def' => '567', 'ghi' => '911']);
        $result = $method->invokeArgs($sut, [$request]);
        $this->assertInternalType('int', $result);
        $this->assertSame(234, $result);
        $settingsProperty->setValue($sut, [$settingName => 456, 'def' => '567', 'ghi' => '911']);
        $result = $method->invokeArgs($sut, [$request]);
        $this->assertInternalType('int', $result);
        $this->assertSame(456, $result);
        // check failures
        try {
            $settingsProperty->setValue($sut, [$settingName => -1, 'def' => '567', 'ghi' => '911']);
            $result = $method->invokeArgs($sut, [$request]);
            $this->fail('Should have rejected null value');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('maxAge should be 0 or more', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Invalid exception: '.$e->getMessage());
        }

        $values = ['abc', '123', true, false, '-1'];
        foreach ($values as $settingValue) {
            try {
                $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
                $result = $method->invokeArgs($sut, [$request]);
                $this->fail('Should have rejected value:'.$settingValue);
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('maxAge should be an int value', $e->getMessage());
            } catch (\Exception $e) {
                $this->fail('Invalid exception: '.$e->getMessage());
            }
        }
    }//end testParseMaxAgeValues()

    /**
     * Test the parse item section.
     *
     * @param mixed   $settingValue   The callable,int,string,bool or array we are testing against.
     * @param string  $expectedResult The expected result.
     * @param boolean $isSingle       Does this "setting" take a single (true) or multiple parameters (false).
     */
    protected function parseItem($settingValue, string $expectedResult, bool $isSingle = false)
    {
        $sut              = new MiddlewareCors();
        $reflection       = new \ReflectionClass(get_class($sut));
        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        // setup the logger
        $this->logger  = new Logger('test');
        $this->testLogger = new TestHandler();
        $this->logger->pushHandler($this->testLogger);
        $sut->setLogger($this->logger);

        $method = $reflection->getMethod('parseItem');
        $method->setAccessible(true);
        $request     = $this->getMockBuilder('Psr\Http\Message\ServerRequestInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $settingName = 'testItem';
        $settingsProperty->setValue($sut, [$settingName => $settingValue, 'def' => '567', 'ghi' => '911']);
        $result = $method->invokeArgs($sut, [$settingName, $request, $isSingle]);
        $this->assertInternalType('string', $result);
        $this->assertSame($expectedResult, $result);
    }//end parseItem()
}//end class
