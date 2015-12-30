<?php

namespace Bairwell\Cors\FunctionalTests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use FastRoute\Dispatcher;
use Bairwell\Cors;
use Interop\Container\ContainerInterface;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\RequestBody;
use Slim\Http\Response;
use Slim\Http\Uri;
use Psr\Log\LoggerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Slim\Http\Body;
class SlimTest extends \PHPUnit_Framework_TestCase
{
    /**
     * List of allowed hosts.
     *
     * @var array
     */
    protected $allowedHosts = ['*.example.com', 'example.com', '*.example.com.test', '192.168.*', '10.*'];

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
     * Get the CORs system integrated with Slim and FastRoute.
     *
     * @param ContainerInterface              $container The Slim Container.
     *
     * @return Cors
     */
    protected function getCors(ContainerInterface $container) : Cors {
        // set our allowed methods callback to integrate with Slim
        $corsAllowedMethods = function (ServerRequestInterface $request) use ($container) : array {
            // if this closure is called, make sure it has the route available in the container.
            /* @var \Slim\Interfaces\RouterInterface $router */
            $router = $container->get('router');

            $routeInfo = $router->dispatch($request);
            $methods   = [];
            // was the method called allowed?
            if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
                $methods = $routeInfo[1];
            } else {
                // if it was, see if we can get the routes and then the methods from it.
                // @var \Slim\Route $route
                $route = $request->getAttribute('route');
                // has the request get a route defined? is so use that
                if (null !== $route) {
                    $methods = $route->getMethods();
                }
            }

            // if we have methods, let's list them removing the OPTIONs one.
            if (false === empty($methods)) {
                // find the OPTIONs method
                $key = array_search('OPTIONS', $methods);
                // and remove it if set.
                if (false !== $key) {
                    unset($methods[$key]);
                    $methods = array_values($methods);
                }
            }

            return $methods;
        };
        // setup CORs
        $cors    = new Cors(
            [
                'origin'           => $this->allowedHosts,
                'exposeHeaders'    => '',
                'maxAge'           => 60,
                'allowCredentials' => true, // we want to allow credentials
                'allowMethods'     => $corsAllowedMethods,
                'allowHeaders'     => ['Accept-Language', 'Authorization', 'Content-type'],
            ]
        );
        // setup the logger
        $this->logger  = new Logger('test');
        $this->testLogger = new TestHandler();
        $this->logger->pushHandler($this->testLogger);
        $cors->setLogger($this->logger);
        return $cors;
    }

    protected function getSlimTest(string $method,
                                   string $url,array $headers=[]) : ResponseInterface {
        $slim=new \Slim\App(['settings'=>['displayErrorDetails' => true]]);

        // add the CORs middleware
        $cors=$this->getCors($slim->getContainer());
        $slim->add($cors);
        // finish adding the CORs middleware

        // add our own error handler.
        $errorHandler=function (ContainerInterface $container) : callable {
            $handler=function (ServerRequestInterface $request,ResponseInterface $response,\Exception $e) : ResponseInterface {
                $body = new Body(fopen('php://temp', 'r+'));
                $body->write('Error Handler caught exception type '.get_class($e).': '.$e->getMessage());
                $return=$response
                    ->withStatus(500)
                    ->withBody($body);
                return $return;
            };
            return $handler;
        };
        // add the error handler.
        $slim->getContainer()['errorHandler']=$errorHandler;

        // add dummy routes
        $slim->get('/foo',function (Request $req,Response $res) { $res->write('getted hi');return $res; });
        $slim->post('/foo',function (Request $req,Response $res) { $res->write('postted hi');return $res; });

        // Prepare request and response objects
        $uri = Uri::createFromString($url);
        $slimHeaders=new Headers($headers);
        $body     = new RequestBody();
        $request  = new Request($method, $uri, $slimHeaders,[], [], $body);
        $response=new Response();
        // override the Slim request and responses with our dummies
        $slim->getContainer()['request']=$request;
        $slim->getContainer()['response']=$response;

        // invoke Slim
        /* @var \Slim\Http\Response $result */
        $result=$slim->run(true);

        // check we got back what we expected
        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $result);
        $this->assertInstanceOf('\Slim\Http\Response',$result);
        return $result;
    }

    /**
     * Test that Slim works as expected.
     *
     * No origin is passed so our middleware should not be invoked.
     *
     * @test
     */
    public function testBasicSlim() {
        $result=$this->getSlimTest('POST','http://localhost/foo');
        $this->assertEquals(200,$result->getStatusCode());
        $this->assertEquals('OK',$result->getReasonPhrase());
        $headers=$result->getHeaders();
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin',$headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials',$headers);
        $this->assertArrayNotHasKey('Access-Control-Expose-Headers',$headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Headers',$headers);
        $this->assertArrayNotHasKey('Access-Control-Max-Age',$headers);
        $this->assertEquals('',$result->getHeaderLine('content-type'));
        $this->assertEquals(10,$result->getBody()->getSize());
        $body=$result->getBody();
        $body->rewind();
        $contents=$body->getContents();
        $this->assertEquals('postted hi',$contents);
        // check a 404 request
        $result=$this->getSlimTest('GET','http://localhost/XYZ');
        $this->assertEquals(404,$result->getStatusCode());
        $this->assertEquals('Not Found',$result->getReasonPhrase());
        $headers=$result->getHeaders();
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin',$headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials',$headers);
        $this->assertArrayNotHasKey('Access-Control-Expose-Headers',$headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Headers',$headers);
        $this->assertArrayNotHasKey('Access-Control-Max-Age',$headers);
        $body=$result->getBody();
        $body->rewind();
        $contents=$body->getContents();
        $this->assertRegExp('/Page Not Found/',$contents);
        // check a 405 request
        $result=$this->getSlimTest('DELETE','http://localhost/foo');
        $this->assertEquals(405,$result->getStatusCode());
        $this->assertEquals('Method Not Allowed',$result->getReasonPhrase());
        $headers=$result->getHeaders();
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin',$headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials',$headers);
        $this->assertArrayNotHasKey('Access-Control-Expose-Headers',$headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Headers',$headers);
        $this->assertArrayNotHasKey('Access-Control-Max-Age',$headers);
        $body=$result->getBody();
        $body->rewind();
        $contents=$body->getContents();
        $this->assertRegExp('/Method not allowed/',$contents);
    }

    /**
     * Test the middleware works with a basic get request.
     *
     * We pass in an Origin header. We should get back the CORs headers and the body content.
     *
     * @test
     */
    public function testGetWithOrigin() {
        $result=$this->getSlimTest('GET','http://localhost/foo',['Origin'=>'test.example.com']);
        $this->assertEquals(200,$result->getStatusCode());
        $this->assertEquals('OK',$result->getReasonPhrase());
        $headers=$result->getHeaders();
        $this->assertArrayHasKey('Access-Control-Allow-Origin',$headers);
        $this->assertEquals('test.example.com',$result->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertArrayHasKey('Access-Control-Allow-Credentials',$headers);
        $this->assertEquals('true',$result->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertArrayNotHasKey('Access-Control-Expose-Headers',$headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Headers',$headers);
        $this->assertArrayNotHasKey('Access-Control-Max-Age',$headers);
        $this->assertEquals('',$result->getHeaderLine('content-type'));
        $this->assertEquals(9,$result->getBody()->getSize());
        $body=$result->getBody();
        $body->rewind();
        $contents=$body->getContents();
        $this->assertEquals('getted hi',$contents);
    }
    /**
     * Test the middleware works with a basic OPTIONS request.
     *
     * We pass in an Origin header - but we do not pass in a Access-Control-Request-Method.
     * This should raise a "NoMethod" exception which should be caught by the error handler.
     *
     * @test
     */
    public function testOptionsWithOrigin() {
        $result=$this->getSlimTest('OPTIONS','http://localhost/foo',['Origin'=>'test.example.com']);
        $body=$result->getBody();
        $body->rewind();
        $contents=$body->getContents();
        $this->assertEquals('Error Handler caught exception type Bairwell\Cors\Exceptions\NoMethod: No method provided',$contents);
        $this->assertEquals(500,$result->getStatusCode());
        $this->assertEquals('Internal Server Error',$result->getReasonPhrase());
        $headers=$result->getHeaders();
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin',$headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials',$headers);
        $this->assertArrayNotHasKey('Access-Control-Expose-Headers',$headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Headers',$headers);
        $this->assertArrayNotHasKey('Access-Control-Max-Age',$headers);

    }
    /**
     * Test the middleware works with a basic OPTIONS request.
     *
     * We pass in an Origin and Access-Control-Request. We should get back the CORs headers only.
     *
     * @test
     */
    public function testOptionsWithOriginAndMethod() {
        $result=$this->getSlimTest('OPTIONS','http://localhost/foo',['Origin'=>'test.example.com','Access-Control-Request-Method'=>'POST']);
        $body=$result->getBody();
        $body->rewind();
        $contents=$body->getContents();
        $this->assertEquals(0,$result->getBody()->getSize());
        $this->assertEquals('',$contents);
        $this->assertEquals(204,$result->getStatusCode());
        $this->assertEquals('No Content',$result->getReasonPhrase());
        $headers=$result->getHeaders();
        $this->assertArrayHasKey('Access-Control-Allow-Origin',$headers);
        $this->assertEquals('test.example.com',$result->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertArrayHasKey('Access-Control-Allow-Credentials',$headers);
        $this->assertEquals('true',$result->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertArrayNotHasKey('Access-Control-Expose-Headers',$headers);
        $this->assertArrayHasKey('Access-Control-Allow-Headers',$headers);
        $this->assertEquals('Accept-Language, Authorization, Content-type',$result->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertArrayHasKey('Access-Control-Max-Age',$headers);
        $this->assertEquals(60,$result->getHeaderLine('Access-Control-Max-Age'));
    }

}
