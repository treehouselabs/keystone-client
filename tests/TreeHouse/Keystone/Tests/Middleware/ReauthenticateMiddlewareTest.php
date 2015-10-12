<?php

namespace TreeHouse\Keystone\Tests\Middleware;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use TreeHouse\Keystone\Client\Middleware\ReauthenticateMiddleware;
use TreeHouse\Keystone\Client\RequestSigner;

class ReauthenticateMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_can_be_invoked()
    {
        $options = ['foo' => 'bar'];
        $request = new Request('GET', 'http://example.org');

        $handler = function (RequestInterface $requestIn, $optionsIn) use ($request, $options) {
            $this->assertSame($request, $requestIn);
            $this->assertSame($options, $optionsIn);

            return new Promise();
        };

        $signer = $this
            ->getMockBuilder(RequestSigner::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $middleware = new ReauthenticateMiddleware($handler, $signer);
        $promise = $middleware($request, $options);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }
}
