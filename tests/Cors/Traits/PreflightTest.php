<?php
/**
 * Cors Test preflight.
 */
declare (strict_types = 1);

namespace Bairwell\Cors\Traits;

use Bairwell\Cors;
use Bairwell\Cors\Exceptions\NoMethod;
use Bairwell\Cors\Exceptions\MethodNotAllowed;
use Bairwell\Cors\Exceptions\NoHeadersAllowed;
use Bairwell\Cors\Exceptions\HeaderNotAllowed;
/**
 * Class CorsTest.
 * Tests the CORs middleware layer.
 *
 * @uses \Bairwell\Cors
 * @uses \Bairwell\Cors\Traits\Parse
 * @uses \Bairwell\Cors\Traits\Validate
 * @uses \Bairwell\Cors\Traits\Preflight
 * @uses \Bairwell\Cors\Exceptions\ExceptionAbstract
 */
class PreflightTest extends \PHPUnit_Framework_TestCase
{
    use \Bairwell\Cors\Traits\RunInvokeArrays;

    /**
     * Runs a test based on this having:
     * - Method: OPTIONS (preflight)
     * - * allowed origin (default)
     * - * No methods allowed
     * - Origin set to example.com (matching wildcard)
     * should get exception (no ACRM).
     *
     * @test
     * @covers \Bairwell\Cors::__construct
     * @covers \Bairwell\Cors::__invoke
     * @covers \Bairwell\Cors::preflight
     * @covers \Bairwell\Cors::preflightAccessControlRequestHeaders
     * @covers \Bairwell\Cors::preflightAccessControlAllowMethods
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
        } catch (\Exception $e) {
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
     * @covers \Bairwell\Cors::__construct
     * @covers \Bairwell\Cors::__invoke
     * @covers \Bairwell\Cors::preflight
     * @covers \Bairwell\Cors::preflightAccessControlRequestHeaders
     * @covers \Bairwell\Cors::preflightAccessControlAllowMethods
     * @uses \Bairwell\Cors\Exceptions\NoMethod
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
     * @covers \Bairwell\Cors::__construct
     * @covers \Bairwell\Cors::__invoke
     * @covers \Bairwell\Cors::preflight
     * @covers \Bairwell\Cors::preflightAccessControlRequestHeaders
     * @covers \Bairwell\Cors::preflightAccessControlAllowMethods
     * @uses \Bairwell\Cors\Exceptions\MethodNotAllowed
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
     * @covers \Bairwell\Cors::__construct
     * @covers \Bairwell\Cors::__invoke
     * @covers \Bairwell\Cors::preflight
     * @covers \Bairwell\Cors::preflightAccessControlRequestHeaders
     * @covers \Bairwell\Cors::preflightAccessControlAllowMethods
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
     * @covers \Bairwell\Cors::__construct
     * @covers \Bairwell\Cors::__invoke
     * @covers \Bairwell\Cors::preflight
     * @covers \Bairwell\Cors::preflightAccessControlRequestHeaders
     * @covers \Bairwell\Cors::preflightAccessControlAllowMethods
     * @uses \Bairwell\Cors\Exceptions\NoHeadersAllowed
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
     * @covers \Bairwell\Cors::__construct
     * @covers \Bairwell\Cors::__invoke
     * @covers \Bairwell\Cors::preflight
     * @covers \Bairwell\Cors::preflightAccessControlRequestHeaders
     * @covers \Bairwell\Cors::preflightAccessControlAllowMethods
     * @uses Bairwell\Cors\Exceptions\HeaderNotAllowed
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
     * @covers \Bairwell\Cors::__construct
     * @covers \Bairwell\Cors::__invoke
     * @covers \Bairwell\Cors::preflight
     * @covers \Bairwell\Cors::preflightAccessControlRequestHeaders
     * @covers \Bairwell\Cors::preflightAccessControlAllowMethods
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
     * @covers \Bairwell\Cors::__construct
     * @covers \Bairwell\Cors::__invoke
     * @covers \Bairwell\Cors::preflight
     * @covers \Bairwell\Cors::preflightAccessControlRequestHeaders
     * @covers \Bairwell\Cors::preflightAccessControlAllowMethods
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
     * @covers \Bairwell\Cors::preflightAccessControlRequestHeaders
     * @uses Bairwell\Cors\Exceptions\NoHeadersAllowed
     */
    public function testPreflightAccessControlRequestHeadersNoHeaders()
    {
        // first with no headers
        $config['allowHeaders'] = [''];
        $sut                    = new Cors($config);
        $reflection             = new \ReflectionClass(get_class($sut));
        $method                 = $reflection->getMethod('preflightAccessControlRequestHeaders');
        $method->setAccessible(true);
        $request = $this->getMockForAbstractClass('\Psr\Http\Message\ServerRequestInterface');
        $request->expects($this->any())
            ->method('getHeaderLine')
            ->with('access-control-request-headers')
            ->willReturn('xyz');
        $response = $this->getMockForAbstractClass('\Psr\Http\Message\ResponseInterface');

        $headers = ['abc', 'def', 'ghi'];
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
     * @covers \Bairwell\Cors::preflightAccessControlRequestHeaders
     * @uses Bairwell\Cors\Exceptions\HeaderNotAllowed
     */
    public function testPreflightAccessControlRequestHeadersInvalidHeaders()
    {
        // first with no headers
        $config['allowHeaders'] = ['x-smith'];
        $sut                    = new Cors($config);
        $reflection             = new \ReflectionClass(get_class($sut));
        $method                 = $reflection->getMethod('preflightAccessControlRequestHeaders');
        $method->setAccessible(true);
        $request = $this->getMockForAbstractClass('\Psr\Http\Message\ServerRequestInterface');
        $request->expects($this->any())
            ->method('getHeaderLine')
            ->with('access-control-request-headers')
            ->willReturn('x-jones');
        $response = $this->getMockForAbstractClass('\Psr\Http\Message\ResponseInterface');

        $headers = ['abc', 'def', 'ghi'];
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
