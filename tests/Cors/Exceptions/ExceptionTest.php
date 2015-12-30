<?php
/**
 * Tests the exceptions.
 *
 * Part of the Bairwell\Cors package.
 *
 * (c) Richard Bairwell <richard@bairwell.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types = 1);

namespace Bairwell\Cors\Exceptions;

/**
 * Class ExceptionsTest.
 */
class ExceptionsTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Bad Origin.
     *
     * @test
     * @covers \Bairwell\Cors\Exceptions\BadOrigin
     * @covers \Bairwell\Cors\Exceptions\ExceptionAbstract
     */
    public function testBadOrigin() {
        $sut=new BadOrigin();
        $this->assertInstanceOf('\Bairwell\Cors\Exceptions\BadOrigin',$sut);
        $this->assertInstanceOf('\Bairwell\Cors\Exceptions\ExceptionAbstract',$sut);
        $this->basicChecks($sut);
    }

    /**
     * Header not allowed.
     *
     * @test
     * @covers \Bairwell\Cors\Exceptions\HeaderNotAllowed
     * @covers \Bairwell\Cors\Exceptions\ExceptionAbstract
     */
    public function testHeaderNotAllowed() {
        $sut=new HeaderNotAllowed();
        $this->assertInstanceOf('\Bairwell\Cors\Exceptions\HeaderNotAllowed',$sut);
        $this->assertInstanceOf('\Bairwell\Cors\Exceptions\ExceptionAbstract',$sut);
        $this->basicChecks($sut);
    }

    /**
     * Method not allowed.
     *
     * @test
     * @covers \Bairwell\Cors\Exceptions\MethodNotAllowed
     * @covers \Bairwell\Cors\Exceptions\ExceptionAbstract
     */
    public function testMethodNotAllowed() {
        $sut=new MethodNotAllowed();
        $this->assertInstanceOf('\Bairwell\Cors\Exceptions\MethodNotAllowed',$sut);
        $this->assertInstanceOf('\Bairwell\Cors\Exceptions\ExceptionAbstract',$sut);
        $this->basicChecks($sut);
    }
    /**
     * No Headers Allowed.
     *
     * @test
     * @covers \Bairwell\Cors\Exceptions\MethodNotAllowed
     * @covers \Bairwell\Cors\Exceptions\ExceptionAbstract
     */
    public function testNoHeadersAllowed() {
        $sut=new NoHeadersAllowed();
        $this->assertInstanceOf('\Bairwell\Cors\Exceptions\NoHeadersAllowed',$sut);
        $this->assertInstanceOf('\Bairwell\Cors\Exceptions\ExceptionAbstract',$sut);
        $this->basicChecks($sut);
    }
    /**
     * No Method.
     *
     * @test
     * @covers \Bairwell\Cors\Exceptions\MethodNotAllowed
     * @covers \Bairwell\Cors\Exceptions\ExceptionAbstract
     */
    public function testNoMethod() {
        $sut=new NoMethod();
        $this->assertInstanceOf('\Bairwell\Cors\Exceptions\NoMethod',$sut);
        $this->assertInstanceOf('\Bairwell\Cors\Exceptions\ExceptionAbstract',$sut);
        $this->basicChecks($sut);
    }

    /**
     * Basic checks of methods provided by the ExceptionAbstract.
     *
     * @param ExceptionAbstract $sut The exception we are testing.
     */
    protected function basicChecks(ExceptionAbstract $sut) {
        $this->assertInternalType('string',$sut->getSent());
        $this->assertInternalType('array',$sut->getAllowed());
        $this->assertEmpty($sut->getSent());
        $this->assertEmpty($sut->getAllowed());
        // now try setting the sent
        $this->assertSame($sut,$sut->setSent('test 123'));
        $this->assertInternalType('string',$sut->getSent());
        $this->assertEquals('test 123',$sut->getSent());
        // not try setting the allowed
        $this->assertSame($sut,$sut->setAllowed(['thing','goes','boom',123]));
        $this->assertInternalType('array',$sut->getAllowed());
        $this->assertEquals(['thing','goes','boom',123],$sut->getAllowed());
        // ensure sent was not changed
        $this->assertInternalType('string',$sut->getSent());
        $this->assertEquals('test 123',$sut->getSent());
        // ensure allowed is not changed
        $this->assertSame($sut,$sut->setSent('jeff'));
        $this->assertInternalType('array',$sut->getAllowed());
        $this->assertEquals(['thing','goes','boom',123],$sut->getAllowed());
    }
}
