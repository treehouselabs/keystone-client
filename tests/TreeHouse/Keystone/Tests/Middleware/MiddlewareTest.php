<?php

namespace TreeHouse\Keystone\Tests\Middleware;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TreeHouse\Keystone\Client\Middleware\Middleware;
use TreeHouse\Keystone\Client\Middleware\ReauthenticateMiddleware;
use TreeHouse\Keystone\Client\RequestSigner;

class MiddlewareTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_signs_requests()
    {
        $signer = $this
            ->getMockBuilder(RequestSigner::class)
            ->disableOriginalConstructor()
            ->setMethods(['signRequest'])
            ->getMock()
        ;

        $tokenId = 'abcd1234';
        $request = new Request('GET', 'http://foo.com', ['X-Auth-Token' => $tokenId]);
        $signer->expects($this->once())->method('signRequest')->willReturn($request);

        $middleware = Middleware::signRequest($signer);
        $handler = new MockHandler([
            function (RequestInterface $request) use ($tokenId) {
                $this->assertTrue($request->hasHeader('X-Auth-Token'));
                $this->assertEquals($tokenId, $request->getHeaderLine('X-Auth-Token'));

                return new Response(200);
            },

        ]);

        $callback = $middleware($handler);

        /** @var ResponseInterface $response */
        $response = $callback(new Request('GET', 'http://foo.com'), [])->wait();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_reauthenticates_requests()
    {
        $signer = $this
            ->getMockBuilder(RequestSigner::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $middleware = Middleware::reauthenticate($signer);
        $handler = $middleware(new MockHandler([]));

        $this->assertInstanceOf(ReauthenticateMiddleware::class, $handler);
    }
}
