<?php
/**
 * Tests the main CORs system.
 * 
 * Part of the Bairwell\MiddlewareCors package.
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types = 1);

namespace Bairwell;

use Bairwell\MiddlewareCors\Exceptions\BadOrigin;

/**
 * Class CorsTest.
 * Tests the CORs middleware layer.
 *
 * @uses \Bairwell\MiddlewareCors
 * @uses \Bairwell\MiddlewareCors\ValidateSettings
 * @uses \Bairwell\MiddlewareCors\Traits\Parse
 * @uses \Bairwell\MiddlewareCors\Preflight
 * @uses \Bairwell\MiddlewareCors\Exceptions\ExceptionAbstract
 */
class MiddlewareCorsTest extends \PHPUnit_Framework_TestCase
{
    use \Bairwell\MiddlewareCors\Traits\RunInvokeArrays;

    /**
     * Checks the default settings.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::getDefaults
     * @covers \Bairwell\MiddlewareCors::getSettings
     * @covers \Bairwell\MiddlewareCors::getAllowedSettings
     */
    public function testCheckDefaultSettings()
    {
        $sut      = new MiddlewareCors();
        $defaults = $sut->getDefaults();
        $this->arraysAreSimilar($this->defaults, $defaults);
        $settings = $sut->getSettings();
        $this->arraysAreSimilar($this->defaults, $settings);
        $allowed = $sut->getAllowedSettings();
        $this->arraysAreSimilar($this->allowedSettings, $allowed);
    }//end testCheckDefaultSettings()

    /**
     * Test the logger can be configured.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::addLog
     * @covers \Bairwell\MiddlewareCors::setLogger
     */
    public function testLogger() {
        $sut      = new MiddlewareCors();
        $addLog=new \ReflectionMethod($sut,'addLog');
        $addLog->setAccessible(true);
        $this->assertFalse($addLog->invoke($sut,'Log entry'));

        $logger=$this->getMockForAbstractClass('\Psr\Log\LoggerInterface');
        $logger->expects($this->once())
            ->method('debug')
            ->with('Log entry');
        $sut->setLogger($logger);
        $this->assertTrue($addLog->invoke($sut,'Log entry'));
    }
    /**
     * Checks the settings can be changed via the constructor.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::getSettings
     */
    public function testCheckChangedSettingsViaConstructor()
    {
        $sut                = new MiddlewareCors(['origin' => 'test']);
        $expected           = $this->defaults;
        $expected['origin'] = 'test';
        $settings           = $sut->getSettings();
        $this->arraysAreSimilar($expected, $settings);
    }//end testCheckChangedSettingsViaConstructor()

    /**
     * Checks the settings can be changed via the setter.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::getSettings
     * @covers \Bairwell\MiddlewareCors::setSettings
     */
    public function testCheckChangedSettingsViaSetter()
    {
        $sut = new MiddlewareCors();
        $sut->setSettings(['maxAge' => 123, 'allowCredentials' => true]);
        $expected           = $this->defaults;
        $expected['maxAge'] = 123;
        $expected['allowCredentials'] = true;
        $settings = $sut->getSettings();
        $this->arraysAreSimilar($expected, $settings);
    }//end testCheckChangedSettingsViaSetter()

    /**
     * Checks the settings will allow random stuff to be set.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::getSettings
     */
    public function testCheckChangedSettingsViaSetterRandomSettings()
    {
        $sut = new MiddlewareCors();
        $sut->setSettings(['maxAge' => 123, 'allowCredentials' => true, 'random' => '123']);
        $expected           = $this->defaults;
        $expected['maxAge'] = 123;
        $expected['allowCredentials'] = true;
        $expected['random']           = '123';
        $settings = $sut->getSettings();
        $this->arraysAreSimilar($expected, $settings);
    }//end testCheckChangedSettingsViaSetterRandomSettings()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - Default configuration.
     * Should have no CORS headers back.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     */
    public function testInvokerGetDefaults()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => [],
                'configuration' => []
            ]
        );
        $expected = ['calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'Request does not have an origin setting'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerGetDefaults()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - * allowed origin (default)
     * - Origin set to example.com (matching wildcard)
     * should get
     * Access-Control-Allow-Origin
     * and next called.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOriginMatch
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     */
    public function testInvokerWithOriginHeader()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'example.com'],
                'configuration' => []
            ]
        );
        $expected = ['withHeader:Access-Control-Allow-Origin' => '*', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "example.com"',
            'Attempting to match origin as string',
            'Checking configuration origin of "*" against user "example.com"',
            'Origin is either an empty string or wildcarded star. Returning *',
            'Processing with origin of "*"',
            'Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);

    }//end testInvokerWithOriginHeader()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - example.com allowed origin (default)
     * - Origin set to example.com
     * should get
     * Access-Control-Allow-Origin
     * and next called.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOriginMatch
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     */
    public function testInvokerWithCustomOriginHeader()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'example.com'],
                'configuration' => ['origin' => 'example.com']
            ]
        );
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'example.com', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "example.com"',
            'Attempting to match origin as string',
            'Checking configuration origin of "example.com" against user "example.com"',
            'Origin is an exact case insensitive match',
            'Processing with origin of "example.com"',
            'Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithCustomOriginHeader()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - example.com allowed origin (default)
     * - Origin set to dummy.com
     * should get access denied.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOriginMatch
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     * @uses \Bairwell\MiddlewareCors\Exceptions\BadOrigin
     */
    public function testInvokerWithCustomOriginHeaderInvalid()
    {
        try {
            $results = $this->runInvoke(
                [
                    'method'        => 'GET',
                    'setHeaders'    => ['origin' => 'dummy.com'],
                    'configuration' => ['origin' => 'example.com']
                ]
            );
            $this->fail('An exception should have been raised due to the mismatches');
        } catch (BadOrigin $e) {
            $this->assertSame('Bad Origin', $e->getMessage());
            $this->assertEmpty($e->getAllowed());
            $this->assertSame('dummy.com', $e->getsent());
        }

        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "dummy.com"',
            'Attempting to match origin as string',
            'Checking configuration origin of "example.com" against user "dummy.com"',
            'Unable to match "example.com" against user "dummy.com"'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithCustomOriginHeaderInvalid()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - example.com allowed origin (default)
     * - Origin set to ''
     * should just get "Next" as (with a blank/unset origin), this is not a cors call.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOriginMatch
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     */
    public function testInvokerWithCustomOriginHeaderEmpty()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => ''],
                'configuration' => ['origin' => 'example.com']
            ]
        );
        $expected = ['calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'Request does not have an origin setting'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithCustomOriginHeaderEmpty()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - example.com allowed origin (default)
     * - Origin set to dummy.com
     * should get access denied.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOriginMatch
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     * @uses \Bairwell\MiddlewareCors\Exceptions\BadOrigin
     */
    public function testInvokerWithCustomOriginHeaderDummyCallback()
    {
        try {
            $results = $this->runInvoke(
                [
                    'method'        => 'GET',
                    'setHeaders'    => ['origin' => 'dummy.com'],
                    'configuration' => ['origin' => 'example.com']
                ]
            );
            $this->fail('Expected exception to be raised');
        } catch (BadOrigin $e) {
            $this->assertSame('Bad Origin', $e->getMessage());
            $this->assertEmpty($e->getAllowed());
            $this->assertSame('dummy.com', $e->getSent());
        }
        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "dummy.com"',
            'Attempting to match origin as string',
            'Checking configuration origin of "example.com" against user "dummy.com"',
            'Unable to match "example.com" against user "dummy.com"'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithCustomOriginHeaderDummyCallback()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - origin set via callback
     * - Origin set to dummy.com
     * should get access denied.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOriginMatch
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     * @uses \Bairwell\MiddlewareCors\Exceptions\BadOrigin
     */
    public function testInvokerWithCustomOriginHeaderCustomCallbacks()
    {
        $originCallback = function ($request) {
            return 'hello';
        };
        try {
            $results = $this->runInvoke(
                [
                    'method'        => 'GET',
                    'setHeaders'    => ['origin' => 'dummy.com'],
                    'configuration' => ['origin' => $originCallback]
                ]
            );
            $this->fail('Expected exception to be raised');
        } catch (BadOrigin $e) {
            $this->assertSame('Bad Origin', $e->getMessage());
            $this->assertEmpty($e->getAllowed());
            $this->assertSame('dummy.com', $e->getSent());
        }
        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "dummy.com"',
            'Origin server request is being passed to callback',
            'Attempting to match origin as string',
            'Checking configuration origin of "hello" against user "dummy.com"',
            'Unable to match "hello" against user "dummy.com"'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithCustomOriginHeaderCustomCallbacks()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - origin set via callback
     * - Origin set to dummy.com
     * should get
     * Access-Control-Allow-Origin
     * and next called.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOriginMatch
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     */
    public function testInvokerWithCustomOriginHeaderCustomAllowedCallbacks()
    {
        $originCallback = function ($request) {
            return 'dummy.com';
        };
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'dummy.com'],
                'configuration' => ['origin' => $originCallback]
            ]
        );
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'dummy.com', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "dummy.com"',
            'Origin server request is being passed to callback',
            'Attempting to match origin as string',
            'Checking configuration origin of "dummy.com" against user "dummy.com"',
            'Origin is an exact case insensitive match',
            'Processing with origin of "dummy.com"',
            'Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithCustomOriginHeaderCustomAllowedCallbacks()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - origin set to array
     * - Origin set to dummy.com
     * should get
     * Access-Control-Allow-Origin
     * and next called.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOriginMatch
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     */
    public function testInvokerWithOriginArray()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'dummy.com'],
                'configuration' => ['origin' => ['example.com', 'dummy.com']]
            ]
        );
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'dummy.com', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "dummy.com"',
            'Iterating through Origin array',
            'Checking configuration origin of "example.com" against user "dummy.com"',
            'Unable to match "example.com" against user "dummy.com"',
            'Checking configuration origin of "dummy.com" against user "dummy.com"',
            'Origin is an exact case insensitive match',
            'Iterator found a matched origin of dummy.com',
            'Processing with origin of "dummy.com"',
            'Calling next bit of middleware'
            ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithOriginArray()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - origin set to array
     * - Origin set to dummy.com
     * should get
     * Access-Control-Allow-Origin
     * and next called.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOriginMatch
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     */
    public function testInvokerWithOriginArrayWildcard()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'www.dummy.com'],
                'configuration' => ['origin' => ['example.com', '*.dummy.com']]
            ]
        );
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'www.dummy.com', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "www.dummy.com"',
            'Iterating through Origin array',
            'Checking configuration origin of "example.com" against user "www.dummy.com"',
            'Unable to match "example.com" against user "www.dummy.com"',
            'Checking configuration origin of "*.dummy.com" against user "www.dummy.com"',
            'Wildcarded origin match with www.dummy.com',
            'Iterator found a matched origin of www.dummy.com',
            'Processing with origin of "www.dummy.com"',
            'Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithOriginArrayWildcard()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - origin set to array
     * - Origin set to dummy.com
     * should get
     * access denied.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOriginMatch
     * @covers \Bairwell\MiddlewareCors\Traits\Parse::parseOrigin
     * @uses \Bairwell\MiddlewareCors\Exceptions\BadOrigin
     */
    public function testInvokerWithOriginArrayInvalid()
    {
        try {
            $results = $this->runInvoke(
                [
                    'method'        => 'GET',
                    'setHeaders'    => ['origin' => 'bbc.co.uk'],
                    'configuration' => ['origin' => ['example.com', 'dummy.com']]
                ]
            );
            $this->fail('Expected exception to be raised');
        } catch (BadOrigin $e) {
            $this->assertSame('Bad Origin', $e->getMessage());
            $this->assertEmpty($e->getAllowed());
            $this->assertSame('bbc.co.uk', $e->getSent());
        }
        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "bbc.co.uk"',
            'Iterating through Origin array',
            'Checking configuration origin of "example.com" against user "bbc.co.uk"',
            'Unable to match "example.com" against user "bbc.co.uk"',
            'Checking configuration origin of "dummy.com" against user "bbc.co.uk"',
            'Unable to match "dummy.com" against user "bbc.co.uk"'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithOriginArrayInvalid()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - * allowed origin (default)
     * - allowCredentials true
     * - Origin set to example.com (matching wildcard)
     * should get
     * Access-Control-Allow-Origin
     * Access-Control-Allow-Credentials
     * and next called.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     */
    public function testInvokerWithOriginHeaderAndCredentials()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'example.com'],
                'configuration' => ['allowCredentials' => true]
            ]
        );
        $expected = [
            'withHeader:Access-Control-Allow-Origin'      => '*',
            'withHeader:Access-Control-Allow-Credentials' => 'true',
            'calledNext'                                  => 'called'
        ];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "example.com"',
            'Attempting to match origin as string',
            'Checking configuration origin of "*" against user "example.com"',
            'Origin is either an empty string or wildcarded star. Returning *',
            'Processing with origin of "*"',
            'Adding Access-Control-Allow-Credentials header',
            'Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithOriginHeaderAndCredentials()

    /**
     * Runs a test based on this having:
     * - Method: GET
     * - * allowed origin (default)
     * - allowCredentials true
     * - Origin set to example.com (matching wildcard)
     * should get
     * Access-Control-Allow-Origin
     * Access-Control-Allow-Credentials
     * Access-Control-Expose-Headers
     * and next called.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     */
    public function testInvokerWithOriginHeaderAndCredentialsWithHeaders()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'example.com'],
                'configuration' => ['allowCredentials' => true, 'exposeHeaders' => 'XY,ZX']
            ]
        );
        $expected = [
            'withHeader:Access-Control-Allow-Origin'      => '*',
            'withHeader:Access-Control-Allow-Credentials' => 'true',
            'withHeader:Access-Control-Expose-Headers'    => 'XY, ZX',
            'calledNext'                                  => 'called'
        ];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'Request has an origin setting and is being treated like a CORs request',
            'Processing origin of "example.com"',
            'Attempting to match origin as string',
            'Checking configuration origin of "*" against user "example.com"',
            'Origin is either an empty string or wildcarded star. Returning *',
            'Processing with origin of "*"',
            'Adding Access-Control-Allow-Credentials header',
            'Adding Access-Control-Expose-Header header',
            'Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithOriginHeaderAndCredentialsWithHeaders()
}//end class
