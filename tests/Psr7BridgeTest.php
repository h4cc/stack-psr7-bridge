<?php

/*
 * This file is part of the h4cc/stack-psr7-bridge package.
 *
 * (c) Julius Beckmann <github@h4cc.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace h4cc\StackPsr7Bridge\Tests;

use h4cc\StackPsr7Bridge\Psr7Bridge;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Stack\CallableHttpKernel;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zend\Diactoros\Uri;

/**
 * @covers \h4cc\StackPsr7Bridge\Psr7Bridge
 */
class Psr7BridgeTest extends \PHPUnit_Framework_TestCase
{
    /** @var  Psr7Bridge */
    private $bridge;

    /** @var \PHPUnit_Framework_MockObject_MockObject | HttpFoundationFactoryInterface */
    private $mockHttpFoundationFactory;

    /** @var \PHPUnit_Framework_MockObject_MockObject | HttpMessageFactoryInterface */
    private $mockPsr7Factory;

    /** @var  RequestInterface */
    private $defaultPsr7Request;

    /** @var  ResponseInterface */
    private $defaultPsr7Response;

    /** @var  Request */
    private $defaultSymfonyRequest;

    /** @var  Response */
    private $defaultSymfonyResponse;

    public function setUp()
    {
        $this->mockHttpFoundationFactory = $this->getMockBuilder('\Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface')
            ->setMethods(array('createRequest', 'createResponse'))
            ->getMockForAbstractClass();

        $this->mockPsr7Factory = $this->getMockBuilder('\Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface')
            ->setMethods(array('createRequest', 'createResponse'))
            ->getMockForAbstractClass();

        $request = new \Zend\Diactoros\ServerRequest();
        $request = $request->withUri(new Uri('/hello-world'));
        $request = $request->withMethod('GET');
        $this->defaultPsr7Request = $request;
        $response = new \Zend\Diactoros\Response();
        $response = $response->withStatus(200);
        $response->getBody()->write('hello world');
        $this->defaultPsr7Response = $response;

        $this->defaultSymfonyRequest = Request::create('/hello-world', 'GET');
        $this->defaultSymfonyResponse = new Response('hello world', 200);
    }

    /**
     * Using the bridge like a proxy. No mapping should be done.
     */
    public function testHandleWithoutMapping()
    {
        $this->mockHttpFoundationFactory->expects($this->never())->method('createRequest');
        $this->mockHttpFoundationFactory->expects($this->never())->method('createResponse');

        $this->mockPsr7Factory->expects($this->never())->method('createRequest');
        $this->mockPsr7Factory->expects($this->never())->method('createResponse');

        $this->createBridge($this->getKernelHelloWorld());

        $response = $this->bridge->handle($this->defaultSymfonyRequest);

        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\Response', $response);
        $this->assertEquals('hello world', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Using the bridge like a http kernel, to use a psr-7 application inside.
     */
    public function testHandleMappingToPsr7()
    {
        $this->mockHttpFoundationFactory->expects($this->never())
            ->method('createRequest');
        $this->mockHttpFoundationFactory->expects($this->once())
            ->method('createResponse')
            ->with($this->defaultPsr7Response)
            ->willReturn($this->defaultSymfonyResponse);

        $this->mockPsr7Factory->expects($this->once())
            ->method('createRequest')
            ->with($this->defaultSymfonyRequest)
            ->willReturn($this->defaultPsr7Request);
        $this->mockPsr7Factory->expects($this->never())
            ->method('createResponse');

        $this->createBridge($this->getPsr7HelloWorld());

        $response = $this->bridge->handle($this->defaultSymfonyRequest);

        $this->assertInstanceOf('\Symfony\Component\HttpFoundation\Response', $response);
        $this->assertEquals('hello world', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Using the bridge like a proxy. No mapping should be done.
     */
    public function testInvokeWithoutMapping()
    {
        $this->mockHttpFoundationFactory->expects($this->never())->method('createRequest');
        $this->mockHttpFoundationFactory->expects($this->never())->method('createResponse');

        $this->mockPsr7Factory->expects($this->never())->method('createRequest');
        $this->mockPsr7Factory->expects($this->never())->method('createResponse');

        $this->createBridge($this->getPsr7HelloWorld());

        $response = $this->bridge->__invoke($this->defaultPsr7Request, $this->defaultPsr7Response);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Using the bridge like a psr-7 application, to use a http kernel inside.
     */
    public function testInvokeMappingToKernel()
    {
        $this->mockHttpFoundationFactory->expects($this->once())
            ->method('createRequest')
            ->with($this->defaultPsr7Request)
            ->willReturn($this->defaultSymfonyRequest);
        $this->mockHttpFoundationFactory->expects($this->never())
            ->method('createResponse');

        $this->mockPsr7Factory->expects($this->never())
            ->method('createRequest');
        $this->mockPsr7Factory->expects($this->once())
            ->method('createResponse')
            ->with($this->defaultSymfonyResponse)
            ->willReturn($this->defaultPsr7Response);

        $this->createBridge($this->getKernelHelloWorld());

        $response = $this->bridge->__invoke($this->defaultPsr7Request, $this->defaultPsr7Response);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Using invoke including the $next middleware.
     */
    public function testInvokeUsingNext()
    {
        $this->createBridge($this->getPsr7HelloWorld());

        $psr7Middleware = function (RequestInterface $request, ResponseInterface $response, $next = null) {
            $this->assertEquals($this->defaultPsr7Request, $request);

            return $this->defaultPsr7Response;
        };

        $response = $this->bridge->__invoke($this->defaultPsr7Request, $this->defaultPsr7Response, $psr7Middleware);

        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionInvalidKernel()
    {
        $this->createBridge(null);

        $this->bridge->handle($this->defaultSymfonyRequest);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionInvalidPsr7()
    {
        $this->createBridge(null);

        $this->bridge->__invoke($this->defaultPsr7Request, $this->defaultPsr7Response);
    }

    private function createBridge($kernelOrNext)
    {
        $this->bridge = new Psr7Bridge($kernelOrNext);
        $this->bridge->setHttpFoundationFactory($this->mockHttpFoundationFactory);
        $this->bridge->setPsr7Factory($this->mockPsr7Factory);
    }

    private function getKernelHelloWorld()
    {
        return new CallableHttpKernel(function (Request $request) {
            return $this->defaultSymfonyResponse;
        });
    }

    private function getPsr7HelloWorld()
    {
        return function (RequestInterface $request, ResponseInterface $response, $next = null) {
            return $this->defaultPsr7Response;
        };
    }
}
