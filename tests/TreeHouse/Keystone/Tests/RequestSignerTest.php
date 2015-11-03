<?php

namespace TreeHouse\Keystone\Tests;

use GuzzleHttp\Psr7\Request;
use TreeHouse\Keystone\Client\Exception\TokenException;
use TreeHouse\Keystone\Client\RequestSigner;
use TreeHouse\Keystone\Client\TokenPool;

class RequestSignerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_can_sign_requests()
    {
        $pool = $this
            ->getMockBuilder(TokenPool::class)
            ->disableOriginalConstructor()
            ->setMethods(['getToken', 'getPublicUrl', 'getTokenId'])
            ->getMock()
        ;

        $force = true;
        $publicUrl = 'http://example.org/v1/';
        $tokenId = 'abcd1234';

        $pool->expects($this->once())->method('getToken')->with($force);
        $pool->expects($this->once())->method('getPublicUrl')->willReturn($publicUrl);
        $pool->expects($this->once())->method('getTokenId')->willReturn($tokenId);

        $signer = new RequestSigner($pool);
        $request = $signer->signRequest(new Request('GET', 'foo'), $force);

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://example.org/v1/foo', (string) $request->getUri());
        $this->assertTrue($request->hasHeader('X-Auth-Token'));
        $this->assertEquals($tokenId, $request->getHeaderLine('X-Auth-Token'));
    }

    /**
     * @test
     * @expectedException \GuzzleHttp\Exception\ClientException
     */
    public function it_throws_exception_on_invalid_token()
    {
        $pool = $this
            ->getMockBuilder(TokenPool::class)
            ->disableOriginalConstructor()
            ->setMethods(['getToken', 'getPublicUrl', 'getTokenId'])
            ->getMock()
        ;

        $pool->expects($this->once())->method('getToken')->willThrowException(new TokenException());

        $signer = new RequestSigner($pool);
        $signer->signRequest(new Request('GET', 'foo'), true);
    }
}
