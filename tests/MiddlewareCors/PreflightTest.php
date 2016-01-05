<?php
/**
 * MiddlewareCors Test preflight.
 */
declare (strict_types = 1);

namespace Bairwell\MiddlewareCors;

use Bairwell\MiddlewareCors;
use Bairwell\MiddlewareCors\Exceptions\NoMethod;
use Bairwell\MiddlewareCors\Exceptions\MethodNotAllowed;
use Bairwell\MiddlewareCors\Exceptions\NoHeadersAllowed;
use Bairwell\MiddlewareCors\Exceptions\HeaderNotAllowed;
/**
 * Class MiddlewareCorsTest.
 * Tests the MiddlewareCors middleware layer.
 *
 * @uses \Bairwell\MiddlewareCors
 * @uses \Bairwell\MiddlewareCors\Traits\Parse
 * @uses \Bairwell\MiddlewareCors\Preflight
 * @uses \Bairwell\MiddlewareCors\ValidateSettings
 * @uses \Bairwell\MiddlewareCors\Exceptions\ExceptionAbstract
 */
class PreflightTest extends \PHPUnit_Framework_TestCase
{
    use \Bairwell\MiddlewareCors\Traits\RunInvokeArrays;

    /**
     * Test the add log
     * @test
     * @covers \Bairwell\MiddlewareCors\Preflight::__construct
     * @covers \Bairwell\MiddlewareCors\Preflight::addLog
     */
    public function testAddLog() {
        $logger=function($logEntry) {
            switch ($logEntry) {
                case 'first':
                    return true;
                case 'second':
                    return false;
                default:
                    throw \Exception('Unrecognised call');
            }
        };
        $sut=new Preflight($logger);
        $reflected=new \ReflectionClass($sut);
        $method=$reflected->getMethod('addLog');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($sut,'first'));
        $this->assertFalse($method->invoke($sut,'second'));
    }
    /**
     * Runs a test based on this having:
     * - Method: OPTIONS (preflight)
     * - * allowed origin (default)
     * - * No methods allowed
     * - Origin set to example.com (matching wildcard)
     * should get exception (no ACRM).
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::__construct
     * @covers \Bairwell\MiddlewareCors\Preflight::addLog
     * @covers \Bairwell\MiddlewareCors\Preflight::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlRequestHeaders
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlAllowMethods
     */
    public function testInvokerPreflightNoMethods()
    {
        try {
            $this->runInvoke(
                [
                    'method'        => 'OPTIONS',
                    'setHeaders'    => ['origin' => 'example.com'],
                    'configuration' => ['allowMethods' => '']
                ]
            );
            $this->fail('Should not have got here');
        } catch (\DomainException $e) {
            $this->assertSame('No methods configured to be allowed for request', $e->getMessage());
        }
    }//end testInvokerPreflightNoMethods()

    /**
     * Runs a test based on this having:
     * - Method: OPTIONS (preflight)
     * - * allowed origin (default)
     * - Origin set to example.com (matching wildcard)
     * should get exception (no ACRM).
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::__construct
     * @covers \Bairwell\MiddlewareCors\Preflight::addLog
     * @covers \Bairwell\MiddlewareCors\Preflight::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlRequestHeaders
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlAllowMethods
     * @uses \Bairwell\MiddlewareCors\Exceptions\NoMethod
     */
    public function testInvokerPreflightNoAcrm()
    {
        try {
            $this->runInvoke(
                [
                    'method'        => 'OPTIONS',
                    'setHeaders'    => ['origin' => 'example.com'],
                    'configuration' => []
                ]
            );
            $this->fail('Should not have got here');
            $this->fail('Expected exception to be raised');
        } catch (NoMethod $e) {
            $this->assertSame('No method provided', $e->getMessage());
            $this->assertEmpty($e->getAllowed());
            $this->assertSame('', $e->getSent());
        }
    }//end testInvokerPreflightNoAcrm()

    /**
     * Runs a test based on this having:
     * - Method: OPTIONS (preflight)
     * - * allowed origin (default)
     * - * allowed methods set to PUT,POST
     * - Origin set to example.com (matching wildcard)
     * - Access-Control-Request-Method set to "DELETE"
     * should get exception.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::__construct
     * @covers \Bairwell\MiddlewareCors\Preflight::addLog
     * @covers \Bairwell\MiddlewareCors\Preflight::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlRequestHeaders
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlAllowMethods
     * @uses \Bairwell\MiddlewareCors\Exceptions\MethodNotAllowed
     */
    public function testInvokerPreflightInvalidAcrm()
    {
        try {
            $this->runInvoke(
                [
                    'method'        => 'OPTIONS',
                    'setHeaders'    => ['origin' => 'example.com', 'access-control-request-method' => 'delete'],
                    'configuration' => ['allowMethods' => ['PUT', 'POST']]
                ]
            );
            $this->fail('Should have failed');
        } catch (MethodNotAllowed $e) {
            $this->assertSame('Method not allowed', $e->getMessage());
            $this->assertSame(['PUT', 'POST'], $e->getAllowed());
            $this->assertSame('DELETE', $e->getSent());
        }
    }//end testInvokerPreflightInvalidAcrm()

    /**
     * Runs a test based on this having:
     * - Method: OPTIONS (preflight)
     * - * allowed origin (default)
     * - * allowed methods set to PUT,POST
     * - Origin set to example.com (matching wildcard)
     * - Access-Control-Request-Method set to "PUT"
     * should get:
     * Access-Control-Allow-Origin
     * Access-Control-Allow-Methods
     * Access-Control-Allow-Headers.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::__construct
     * @covers \Bairwell\MiddlewareCors\Preflight::addLog
     * @covers \Bairwell\MiddlewareCors\Preflight::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlRequestHeaders
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlAllowMethods
     */
    public function testInvokerPreflightValidAcrm()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'OPTIONS',
                'setHeaders'    => ['origin' => 'example.com', 'access-control-request-method' => 'pUt'],
                'configuration' => ['allowMethods' => ['PUT', 'POST']]
            ]
        );
        $expected = [
            'withHeader:Access-Control-Allow-Origin'  => '*',
            'withHeader:Access-Control-Allow-Methods' => 'PUT, POST',
            'withHeader:Access-Control-Allow-Headers' => '',
            'withStatus'                              => '204:No Content',
            'withoutHeader:Content-Type'              => true,
            'withoutHeader:Content-Length'            => true
        ];
        $this->arraysAreSimilar($expected, $results);
    }//end testInvokerPreflightValidAcrm()

    /**
     * Runs a test based on this having:
     * - Method: OPTIONS (preflight)
     * - * allowed origin (default)
     * - * allowed methods set to PUT,POST
     * - Origin set to example.com (matching wildcard)
     * - Access-Control-Request-Method set to "PUT"
     * - Access-Control-Request-Headers set to "X-Jeff"
     * should get exception.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::__construct
     * @covers \Bairwell\MiddlewareCors\Preflight::addLog
     * @covers \Bairwell\MiddlewareCors\Preflight::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlRequestHeaders
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlAllowMethods
     * @uses \Bairwell\MiddlewareCors\Exceptions\NoHeadersAllowed
     */
    public function testInvokerPreflightValidAcrmInvalidAcrh()
    {
        try {
            $this->runInvoke(
                [
                    'method'        => 'OPTIONS',
                    'setHeaders'    => [
                        'origin'                         => 'example.com',
                        'access-control-request-method'  => 'put',
                        'access-control-request-headers' => 'x-jeff'
                    ],
                    'configuration' => ['allowMethods' => ['PUT', 'POST']]
                ]
            );
            $this->fail('Should not have got here');
        } catch (NoHeadersAllowed $e) {
            $this->assertSame('No headers are allowed', $e->getMessage());
            $this->assertEmpty($e->getAllowed());
            $this->assertSame('x-jeff', $e->getSent());
        }
    }//end testInvokerPreflightValidAcrmInvalidAcrh()

    /**
     * Runs a test based on this having:
     * - Method: OPTIONS (preflight)
     * - * allowed origin (default)
     * - * allowed methods set to PUT,POST
     * - * allowed headers to to x-jeff, x-smith
     * - Origin set to example.com (matching wildcard)
     * - Access-Control-Request-Method set to "PUT"
     * - Access-Control-Request-Headers set to "X-Jeff"
     * should get exception.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::__construct
     * @covers \Bairwell\MiddlewareCors\Preflight::addLog
     * @covers \Bairwell\MiddlewareCors\Preflight::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlRequestHeaders
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlAllowMethods
     * @uses Bairwell\MiddlewareCors\Exceptions\HeaderNotAllowed
     */
    public function testInvokerPreflightValidAcrmDisallowedAcrh()
    {
        try {
            $this->runInvoke(
                [
                    'method'        => 'OPTIONS',
                    'setHeaders'    => [
                        'origin'                         => 'example.com',
                        'access-control-request-method'  => 'put',
                        'access-control-request-headers' => 'x-jeff, x-smith, x-jones'
                    ],
                    'configuration' => ['allowMethods' => ['PUT', 'POST'], 'allowHeaders' => 'x-jeff,x-smith']
                ]
            );
            $this->fail('Should not have got here');
        } catch (HeaderNotAllowed $e) {
            $this->assertSame('Header not allowed', $e->getMessage());
            $this->assertSame(['x-jeff', 'x-smith'], $e->getAllowed());
            $this->assertSame('x-jeff, x-smith, x-jones', $e->getSent());
        }
    }//end testInvokerPreflightValidAcrmDisallowedAcrh()

    /**
     * Runs a test based on this having:
     * - Method: OPTIONS (preflight)
     * - * allowed origin (default)
     * - * allowed methods set to PUT,POST
     * - * allowed headers to to x-jeff, x-smith
     * - Origin set to example.com (matching wildcard)
     * - Access-Control-Request-Method set to "PUT"
     * - Access-Control-Request-Headers set to "X-Jeff"
     * should get:
     * Access-Control-Allow-Origin
     * Access-Control-Allow-Methods
     * Access-Control-Allow-Headers.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::__construct
     * @covers \Bairwell\MiddlewareCors\Preflight::addLog
     * @covers \Bairwell\MiddlewareCors\Preflight::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlRequestHeaders
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlAllowMethods
     */
    public function testInvokerPreflightValidAcrmValidAcrh()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'OPTIONS',
                'setHeaders'    => [
                    'origin'                         => 'example.com',
                    'access-control-request-method'  => 'put',
                    'access-control-request-headers' => 'x-jeff, x-smith, x-jones'
                ],
                'configuration' => ['allowMethods' => ['PUT', 'POST'], 'allowHeaders' => 'x-jeff,x-smith,x-jones']
            ]
        );
        $expected = [
            'withHeader:Access-Control-Allow-Origin'  => '*',
            'withHeader:Access-Control-Allow-Methods' => 'PUT, POST',
            'withHeader:Access-Control-Allow-Headers' => 'x-jeff, x-smith, x-jones',
            'withStatus'                              => '204:No Content',
            'withoutHeader:Content-Type'              => true,
            'withoutHeader:Content-Length'            => true
        ];
        $this->arraysAreSimilar($expected, $results);
    }//end testInvokerPreflightValidAcrmValidAcrh()

    /**
     * Runs a test based on this having:
     * - Method: OPTIONS (preflight)
     * - * allowed origin (default)
     * - * allowed methods set to PUT,POST
     * - * allowed headers to to x-jeff, x-smith
     * - * allow credentials
     * - * maxAge 300
     * - * origin example.com
     * - Origin set to example.com
     * - Access-Control-Request-Method set to "PUT"
     * - Access-Control-Request-Headers set to "X-Jeff"
     * should get:
     * Access-Control-Allow-Origin
     * Access-Control-Allow-Methods
     * Access-Control-Allow-Headers
     * Access-Control-Max-Age
     * Access-Control-Allow-Credentials
     * Vary: Origin.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors::__construct
     * @covers \Bairwell\MiddlewareCors::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::__construct
     * @covers \Bairwell\MiddlewareCors\Preflight::addLog
     * @covers \Bairwell\MiddlewareCors\Preflight::__invoke
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlRequestHeaders
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlAllowMethods
     */
    public function testInvokerPreflightAllTheThings()
    {
        $results  = $this->runInvoke(
            [
                'method'        => 'OPTIONS',
                'setHeaders'    => [
                    'origin'                         => 'example.com',
                    'access-control-request-method'  => 'put',
                    'access-control-request-headers' => 'x-jeff, x-smith, x-jones'
                ],
                'configuration' => [
                    'allowMethods'     => ['PUT', 'POST'],
                    'allowHeaders'     => 'x-jeff,x-smith,x-jones',
                    'maxAge'           => 300,
                    'allowCredentials' => true,
                    'origin'           => 'example.com'
                ]
            ]
        );
        $expected = [
            'withHeader:Access-Control-Allow-Origin'      => 'example.com',
            'withHeader:Access-Control-Allow-Credentials' => 'true',
            'withHeader:Access-Control-Allow-Methods'     => 'PUT, POST',
            'withHeader:Access-Control-Allow-Headers'     => 'x-jeff, x-smith, x-jones',
            'withHeader:Access-Control-Max-Age'           => 300,
            'withAddedHeader:Vary'                        => 'Origin',
            'withStatus'                                  => '204:No Content',
            'withoutHeader:Content-Type'                  => true,
            'withoutHeader:Content-Length'                => true
        ];
        $this->arraysAreSimilar($expected, $results);
    }//end testInvokerPreflightAllTheThings()

    /**
     * Specific test to ensure PreflightAccessControlRequestHeaders returns empty arrays.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlRequestHeaders
     * @uses Bairwell\MiddlewareCors\Exceptions\NoHeadersAllowed
     */
    public function testPreflightAccessControlRequestHeadersNoHeaders()
    {
        $log=function (string $string) { };
        $sut                    = new Preflight($log);
        $reflection             = new \ReflectionClass(get_class($sut));
        $settings=$reflection->getProperty('settings');
        $settings->setAccessible(true);
        $settings->setValue($sut,['allowHeaders'=>[]]);
        $method                 = $reflection->getMethod('accessControlRequestHeaders');
        $method->setAccessible(true);
        $request = $this->getMockForAbstractClass('\Psr\Http\Message\ServerRequestInterface');
        $request->expects($this->any())
            ->method('getHeaderLine')
            ->with('access-control-request-headers')
            ->willReturn('xyz');
        $response = $this->getMockForAbstractClass('\Psr\Http\Message\ResponseInterface');

        $headers = [];
        try {
            // @codingStandardsIgnoreStart
            $response = $method->invokeArgs($sut, [$request, $response, &$headers]);
            // @codingStandardsIgnoreEnd
            $this->fail('Should have thrown exception');
        } catch (NoHeadersAllowed $e) {
            $this->assertSame('No headers are allowed', $e->getMessage());
            $this->assertEmpty($e->getAllowed());
            $this->assertSame('xyz', $e->getSent());
        }
    }//end testPreflightAccessControlRequestHeadersNoHeaders()

    /**
     * Specific test to ensure PreflightAccessControlRequestHeaders returns empty arrays.
     *
     * @test
     * @covers \Bairwell\MiddlewareCors\Preflight::accessControlRequestHeaders
     * @uses Bairwell\MiddlewareCors\Exceptions\HeaderNotAllowed
     */
    public function testPreflightAccessControlRequestHeadersInvalidHeaders()
    {
        $log=function (string $string) { };
        $sut                    = new Preflight($log);
        $reflection             = new \ReflectionClass(get_class($sut));

        $settings=$reflection->getProperty('settings');
        $settings->setAccessible(true);
        $settings->setValue($sut,['allowHeaders'=>['x-smith']]);
        $method                 = $reflection->getMethod('accessControlRequestHeaders');
        $method->setAccessible(true);
        $request = $this->getMockForAbstractClass('\Psr\Http\Message\ServerRequestInterface');
        $request->expects($this->any())
            ->method('getHeaderLine')
            ->with('access-control-request-headers')
            ->willReturn('x-jones');
        $response = $this->getMockForAbstractClass('\Psr\Http\Message\ResponseInterface');

        $headers = [];
        try {
            // @codingStandardsIgnoreStart
            $response = $method->invokeArgs($sut, [$request, $response, &$headers]);
            // @codingStandardsIgnoreEnd
            $this->fail('Exception expected');
        } catch (HeaderNotAllowed $e) {
            $this->assertSame('Header not allowed', $e->getMessage());
            $this->assertSame(['x-smith'], $e->getAllowed());
            $this->assertSame('x-jones', $e->getSent());
        }
    }//end testPreflightAccessControlRequestHeadersInvalidHeaders()
}//end class
