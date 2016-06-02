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
        $this->assertFalse($addLog->invoke($sut,'CORs: Log entry'));

        $logger=$this->getMockForAbstractClass('\Psr\Log\LoggerInterface');
        $logger->expects($this->once())
            ->method('debug')
            ->with('CORs: Log entry');
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
            'CORs: Request does not have an origin setting'
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
                'setHeaders'    => ['origin' => 'http://example.com'],
                'configuration' => []
            ]
        );
        // check logs

        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "http://example.com"',
            'CORs: Parsed a hostname from origin: example.com',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "*" against user "example.com"',
            'CORs: Origin is either an empty string or wildcarded star. Returning *',
            'CORs: Processing with origin of "*"',
            'CORs: Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
        // check output
        $expected = ['withHeader:Access-Control-Allow-Origin' => '*', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
    }//end testInvokerWithOriginHeader()

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
    public function testInvokerWithFullyQualifiedOriginHeader()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'http://example.com:83/text.html'],
                'configuration' => []
            ]
        );
        $expected = ['withHeader:Access-Control-Allow-Origin' => '*', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "http://example.com:83/text.html"',
            'CORs: Parsed a hostname from origin: example.com',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "*" against user "example.com"',
            'CORs: Origin is either an empty string or wildcarded star. Returning *',
            'CORs: Processing with origin of "*"',
            'CORs: Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);

    }//end testInvokerWithOriginHeader()
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
    public function testInvokerWithFullyQualifiedOriginHeaderAndProtocol()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'https://example.com:83/text.html'],
                'configuration' => ['origin' => 'example.com']
            ]
        );
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'https://example.com:83', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "https://example.com:83/text.html"',
            'CORs: Parsed a hostname from origin: example.com',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "example.com" against user "example.com"',
            'CORs: Origin is an exact case insensitive match',
            'CORs: Parsed a protocol from origin: https',
            'CORs: Parsed a port from origin: 83',
            'CORs: Processing with origin of "https://example.com:83"',
            'CORs: Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);

    }//end testInvokerWithOriginHeaderAndProtocol()
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
    public function testInvokerWithFullyQualifiedOriginHeaderAndProtocolStandardPort()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'https://example.com:443/text.html'],
                'configuration' => ['origin' => 'example.com']
            ]
        );
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'https://example.com', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "https://example.com:443/text.html"',
            'CORs: Parsed a hostname from origin: example.com',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "example.com" against user "example.com"',
            'CORs: Origin is an exact case insensitive match',
            'CORs: Parsed a protocol from origin: https',
            'CORs: Parsed a port from origin: 443',
            'CORs: Processing with origin of "https://example.com"',
            'CORs: Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);

    }//end testInvokerWithOriginHeaderAndProtocolStandardPort()
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
    public function testInvokerWithFullyQualifiedOriginHeaderAndProtocolNoPort()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'GET',
                'setHeaders'    => ['origin' => 'https://testing.com/text.html'],
                'configuration' => ['origin' => 'testing.com']
            ]
        );
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'https://testing.com', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "https://testing.com/text.html"',
            'CORs: Parsed a hostname from origin: testing.com',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "testing.com" against user "testing.com"',
            'CORs: Origin is an exact case insensitive match',
            'CORs: Parsed a protocol from origin: https',
            'CORs: Unable to parse port from origin',
            'CORs: Processing with origin of "https://testing.com"',
            'CORs: Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);

    }//end testInvokerWithOriginHeaderAndProtocolNoPort()
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
                'setHeaders'    => ['origin' => 'http://example.com'],
                'configuration' => ['origin' => 'example.com']
            ]
        );

        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "http://example.com"',
            'CORs: Parsed a hostname from origin: example.com',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "example.com" against user "example.com"',
            'CORs: Origin is an exact case insensitive match',
            'CORs: Parsed a protocol from origin: http',
            'CORs: Unable to parse port from origin',
            'CORs: Processing with origin of "http://example.com"',
            'CORs: Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
        //
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'http://example.com', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
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
            $this->assertSame(['example.com'],$e->getAllowed());
            $this->assertSame('dummy.com', $e->getSent());
        }

        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "dummy.com"',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "example.com" against user "dummy.com"',
            'CORs: Unable to match "example.com" against user "dummy.com"'
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
            'CORs: Request does not have an origin setting'
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
            $this->assertSame(['example.com'],$e->getAllowed());
            $this->assertSame('dummy.com', $e->getSent());
        }
        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "dummy.com"',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "example.com" against user "dummy.com"',
            'CORs: Unable to match "example.com" against user "dummy.com"'
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
            $this->assertSame(['hello'],$e->getAllowed());
            $this->assertSame('dummy.com', $e->getSent());
        }
        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "dummy.com"',
            'CORs: Origin server request is being passed to callback',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "hello" against user "dummy.com"',
            'CORs: Unable to match "hello" against user "dummy.com"'
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
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'https://dummy.com', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "dummy.com"',
            'CORs: Origin server request is being passed to callback',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "dummy.com" against user "dummy.com"',
            'CORs: Origin is an exact case insensitive match',
            'CORs: Unable to parse protocol/scheme from origin',
            'CORs: Unable to parse port from origin',
            'CORs: Processing with origin of "https://dummy.com"',
            'CORs: Calling next bit of middleware'
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
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'https://dummy.com', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "dummy.com"',
            'CORs: Iterating through Origin array',
            'CORs: Checking configuration origin of "example.com" against user "dummy.com"',
            'CORs: Unable to match "example.com" against user "dummy.com"',
            'CORs: Checking configuration origin of "dummy.com" against user "dummy.com"',
            'CORs: Origin is an exact case insensitive match',
            'CORs: Iterator found a matched origin of dummy.com',
            'CORs: Unable to parse protocol/scheme from origin',
            'CORs: Unable to parse port from origin',
            'CORs: Processing with origin of "https://dummy.com"',
            'CORs: Calling next bit of middleware'
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
        $expected = ['withHeader:Access-Control-Allow-Origin' => 'https://www.dummy.com', 'calledNext' => 'called'];
        $this->arraysAreSimilar($results, $expected);
        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "www.dummy.com"',
            'CORs: Iterating through Origin array',
            'CORs: Checking configuration origin of "example.com" against user "www.dummy.com"',
            'CORs: Unable to match "example.com" against user "www.dummy.com"',
            'CORs: Checking configuration origin of "*.dummy.com" against user "www.dummy.com"',
            'CORs: Wildcarded origin match with www.dummy.com',
            'CORs: Iterator found a matched origin of www.dummy.com',
            'CORs: Unable to parse protocol/scheme from origin',
            'CORs: Unable to parse port from origin',
            'CORs: Processing with origin of "https://www.dummy.com"',
            'CORs: Calling next bit of middleware'
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
            $this->assertSame(['example.com','dummy.com'],$e->getAllowed());
            $this->assertSame('bbc.co.uk', $e->getSent());
        }
        // check logs
        $expectedLogs=[
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "bbc.co.uk"',
            'CORs: Iterating through Origin array',
            'CORs: Checking configuration origin of "example.com" against user "bbc.co.uk"',
            'CORs: Unable to match "example.com" against user "bbc.co.uk"',
            'CORs: Checking configuration origin of "dummy.com" against user "bbc.co.uk"',
            'CORs: Unable to match "dummy.com" against user "bbc.co.uk"'
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
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "example.com"',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "*" against user "example.com"',
            'CORs: Origin is either an empty string or wildcarded star. Returning *',
            'CORs: Processing with origin of "*"',
            'CORs: Adding Access-Control-Allow-Credentials header',
            'CORs: Calling next bit of middleware'
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
            'CORs: Request has an origin setting and is being treated like a CORs request',
            'CORs: Processing origin of "example.com"',
            'CORs: Attempting to match origin as string',
            'CORs: Checking configuration origin of "*" against user "example.com"',
            'CORs: Origin is either an empty string or wildcarded star. Returning *',
            'CORs: Processing with origin of "*"',
            'CORs: Adding Access-Control-Allow-Credentials header',
            'CORs: Adding Access-Control-Expose-Header header',
            'CORs: Calling next bit of middleware'
        ];
        $logEntries=$this->getLoggerStrings();
        $this->assertEquals($expectedLogs,$logEntries);
    }//end testInvokerWithOriginHeaderAndCredentialsWithHeaders()
}//end class
