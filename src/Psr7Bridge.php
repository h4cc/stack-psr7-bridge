<?php

/*
 * This file is part of the h4cc/stack-psr7-bridge package.
 *
 * (c) Julius Beckmann <github@h4cc.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace h4cc\StackPsr7Bridge;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Stack\CallableHttpKernel;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Zend\Diactoros\Response;

/**
 * This middleware is able to convert in the following ways:
 *
 * > Psr7 Request -> Symfony HttpKernel -> Psr7 Response
 * > Symfony Request -> (Expected) PSR7 KernelInterface -> Symfony Response
 *
 *
 * But it can still be used like a standard HttpKernel or a PSR-7 kernel:
 *
 * > Symfony Request -> Symfony HttpKernel -> Symfony Response
 * > Psr7 Request -> (Expected) PSR7 KernelInterface -> Psr7 Response
 *
 * Main use can be to run a Symfony based application in a context already using PSR-7,
 * or needing to use a Symfony/PSR-7 application via HttpKernelInterface and as callable.
 *
 * @author Julius Beckmann <github@h4cc.de>
 */
class Psr7Bridge implements HttpKernelInterface
{
    /** @var  HttpKernelInterface|callable */
    private $kernelOrNext;

    /**
     * Default implementation for $psr7Factory is DiactorosFactory.
     *
     * @param $kernelOrNext HttpKernelInterface|callable
     */
    public function __construct($kernelOrNext)
    {
        $this->kernelOrNext = $kernelOrNext;

        $this->httpFoundationFactory = new HttpFoundationFactory();
        $this->psr7Factory = new DiactorosFactory();
    }

    /**
     * @param HttpFoundationFactoryInterface $httpFoundationFactory
     */
    public function setHttpFoundationFactory(HttpFoundationFactoryInterface $httpFoundationFactory)
    {
        $this->httpFoundationFactory = $httpFoundationFactory;
    }

    /**
     * @param HttpMessageFactoryInterface $psr7Factory
     */
    public function setPsr7Factory(HttpMessageFactoryInterface $psr7Factory)
    {
        $this->psr7Factory = $psr7Factory;
    }

    /**
     * This middleware can be called like any other Symfony application too.
     * If the wrapped application is already PSR7, the request/response will be converted.
     *
     * @param Request $request
     * @param int $type
     * @param bool $catch
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        return $this->getHttpKernel()->handle($request, $type, $catch);
    }

    /**
     * This is the expected PSR7 Interface. Might be subject to change.
     * If the wrapped application is a Symfony application, request/response will be converted,
     * else it will be simply given to PSR7-Kernel.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next =null
     * @return ResponseInterface|\Zend\Diactoros\MessageTrait|\Zend\Diactoros\Response
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        $psr7Callable = $this->getPsr7Callable();

        if ($next) {
            $response = call_user_func($next, $request, $response, $psr7Callable);
        } else {
            $response = call_user_func($psr7Callable, $request, $response);
        }

        return $response;
    }

    /**
     * Helper to ensure a HttpKernelInterface for the wrapped application.
     *
     * @return HttpKernelInterface
     */
    private function getHttpKernel()
    {
        if ($this->kernelOrNext instanceof HttpKernelInterface) {
            return $this->kernelOrNext;
        }

        if (is_callable($this->kernelOrNext)) {
            return new CallableHttpKernel(function (Request $request) {
                $psr7Request = $this->psr7Factory->createRequest($request);

                $psr7Response = call_user_func($this->kernelOrNext, $psr7Request, new Response(), null);

                return $this->httpFoundationFactory->createResponse($psr7Response);
            });
        }

        throw new \InvalidArgumentException('Need either a HttpKernel or a Closure.');
    }

    /**
     * Helper to ensure a PSR-7 Callable for the wrapped implementation.
     *
     * @return callable
     */
    private function getPsr7Callable()
    {
        if (is_callable($this->kernelOrNext)) {
            return $this->kernelOrNext;
        }

        if ($this->kernelOrNext instanceof HttpKernelInterface) {
            return function (ServerRequestInterface $request, ResponseInterface $response, callable $next = null) {
                $symfonyRequest = $this->httpFoundationFactory->createRequest($request);

                $symfonyResponse = $this->getHttpKernel()->handle($symfonyRequest);

                return $this->psr7Factory->createResponse($symfonyResponse);
            };
        }

        throw new \InvalidArgumentException('Need either a HttpKernel or a Closure.');
    }
}
