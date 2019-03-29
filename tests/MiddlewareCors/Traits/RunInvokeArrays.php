<?php
/**
 * Class MiddlewareCorsTest validate.
 *
 * Tests the MiddlewareCors middleware layer.
 */

namespace Bairwell\MiddlewareCors\Traits;

use Bairwell\MiddlewareCors;
use Psr\Log\LoggerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

trait RunInvokeArrays
{
    /**
     * Expected default configuration array.
     *
     * @var array
     */
    protected $defaults;

    /**
     * The allowed settings.
     *
     * @var array
     */
    protected $allowedSettings;

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
     * Get all the logger entries.
     *
     * @return array
     */
    public function getLoggerStrings() {
        $records=$this->testLogger->getRecords();
        $return=[];
        foreach ($records as $record) {
            $return[]=$record['message'];
        }
        return $return;
    }
    /**
     * Setup for PHPUnit.
     *
     * @throws \Exception In callback if there is a problem.
     */
    public function setUp()
    {
        $this->defaults = [
            'origin' => '*',
            'exposeHeaders' => '',
            'maxAge' => 0,
            'allowCredentials' => false,
            'allowMethods' => 'GET,HEAD,PUT,POST,DELETE',
            'allowHeaders' => ''
        ];
        $this->allowedSettings = [
            'exposeHeaders' => ['string', 'array', 'callable'],
            'allowMethods' => ['string', 'array', 'callable'],
            'allowHeaders' => ['string', 'array', 'callable'],
            'origin' => ['string', 'array', 'callable'],
            'maxAge' => ['int', 'callable'],
            'allowCredentials' => ['bool', 'callable']
        ];
    }//end setUp()

    /**
     * Run the invoke system for testing.
     *
     * @param array $settings Include:
     *                        method (GET/POST/PUT/OPTIONS)
     *                        setHeaders (array): additional headers sending in (such as origin)
     *                        configuration (array) Configuration data to pass in.
     *
     * @throws \Exception If configuration settings are missing.
     *
     * @return array
     */
    private function runInvoke(array $settings)
    {
        if (false === isset($settings['method']) || false === isset($settings['setHeaders'])
            || false === isset($settings['configuration'])
        ) {
            throw new \Exception('Missing settings');
        }

        $sut = new MiddlewareCors($settings['configuration']);
        // sanity check
        $this->assertInstanceOf('Bairwell\MiddlewareCors', $sut);
        $sutSettings = array_merge($this->defaults, $settings['configuration']);
        $this->arraysAreSimilar($sutSettings, $sut->getSettings(), 'Matching internal settings');
        // setup the logger
        $this->logger = new Logger('test');
        $this->testLogger = new TestHandler();
        $this->logger->pushHandler($this->testLogger);
        $sut->setLogger($this->logger);
        // set up the request
        $request = $this->getMockForAbstractClass('\Psr\Http\Message\ServerRequestInterface');
        $request->expects($this->any())
            ->method('getMethod')
            ->willReturn($settings['method']);
        $request->expects($this->any())
            ->method('getHeaderLine')
            ->willReturnCallback(
                function ($headerName) use ($settings) {
                    if (true === isset($settings['setHeaders'][$headerName])) {
                        return $settings['setHeaders'][$headerName];
                    } else {
                        return '';
                    }
                }
            );
        // now setup the response stack.
        $responseCalls = [];
        $response = $this->getMockForAbstractClass('\Psr\Http\Message\ResponseInterface');
        $response->expects($this->any())
            ->method('withAddedHeader')
            ->will(
                $this->returnCallback(
                    function ($k, $v) use (&$responseCalls, $response) {
                                $responseCalls['withAddedHeader:'.$k] = $v;

                                return $response;
                    }
                )
            );
        $response->expects($this->any())
            ->method('withHeader')
            ->will(
                $this->returnCallback(
                    function ($k, $v) use (&$responseCalls, $response) {
                                $responseCalls['withHeader:'.$k] = $v;

                                return $response;
                    }
                )
            );
        $response->expects($this->any())
            ->method('withoutHeader')
            ->will(
                $this->returnCallback(
                    function ($k) use (&$responseCalls, $response) {
                                $responseCalls['withoutHeader:'.$k] = true;

                                return $response;
                    }
                )
            );
        $response->expects($this->any())
            ->method('withStatus')
            ->will(
                $this->returnCallback(
                    function ($k, $v) use (&$responseCalls, $response) {
                                $responseCalls['withStatus'] = $k.':'.$v;

                                return $response;
                    }
                )
            );
        $next = function ($req, $res) use (&$responseCalls, $response) {
            $responseCalls['calledNext'] = 'called';

            return $response;
        };
        $returnedResponse = $sut->__invoke($request, $response, $next);

        return $responseCalls;
    }//end runInvoke()

    /**
     * Determine if two associative arrays are similar.
     *
     * Both arrays must have the same indexes with identical values
     * without respect to key ordering.
     *
     * @param \array $a       First array to compare against.
     * @param \array $b       Second array to compare against.
     * @param string $message Optional diagnostic message.
     */
    private function arraysAreSimilar(array $a, array $b, string $message = '')
    {
        if (count($a) !== count($b)) {
            $this->fail($message.': First array has '.count($a).' variables, second has '.count($b));

            return;
        }

        $aKeys = array_keys($a);
        $bKeys = array_keys($b);
        $differenceInKeys = array_diff($aKeys, $bKeys);
        if (0 !== count($differenceInKeys)) {
            $this->fail(
                $message.': Difference in keys: first array has keys: ['.
                implode(', ', $aKeys).
                '] second array has: ['.
                implode(', ', $bKeys).
                ']'
            );

            return;
        }

        // we know that the indexes, but maybe not values, match.
        // compare the values between the two arrays
        foreach ($a as $k => $v) {
            if (($v instanceof \Closure) || ($b[$k] instanceof \Closure)) {
                if (false === (($v instanceof \Closure) && ($b[$k] instanceof \Closure))) {
                    $this->fail($message.': Expected key '.$k.' to be a closure in both instances');
                }
            } else {
                $msg = $message.': Expected '.gettype($v);
                if (true === is_string($v)) {
                    $msg .= ' "'.$v.'"';
                }

                $msg .= ' got '.gettype($b[$k]);
                if (true === is_string($b[$k])) {
                    $msg .= ' "'.$b[$k].'"';
                }

                $this->assertSame($v, $b[$k], 'Comparing arrays:'.$msg);
            }
        }//end foreach
    }//end arraysAreSimilar()
}
