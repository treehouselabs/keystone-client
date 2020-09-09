<?php

namespace TreeHouse\Keystone\Tests;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use TreeHouse\Keystone\Client\Model\Tenant;
use TreeHouse\Keystone\Client\Model\Token;
use TreeHouse\Keystone\Client\TokenPool;

class TokenPoolTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|CacheItemInterface
     */
    private $cacheItem;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|TokenPool
     */
    private $pool;

    /**
     * @var Tenant
     */
    private $tenant;

    /**
     * @var Token
     */
    private $token;

    /**
     * @var string
     */
    private $publicUrl = 'http://example.org';

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->tenant = new Tenant('http://example.org', 'user', 'p@$$', 'compute');
        $this->token = new Token('abcd1234', new \DateTime('+1 hour'));
        $this->token->addServiceCatalog('compute', 'api', [['publicurl' => $this->publicUrl]]);

        $this->cacheItem = $this
            ->getMockBuilder(CacheItemInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['set', 'get', 'expiresAfter'])
            ->getMockForAbstractClass()
        ;

        $this->cache = $this
            ->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getItem', 'save'])
            ->getMockForAbstractClass()
        ;

        $this->cache->method('getItem')->willReturn($this->cacheItem);

        $this->pool = $this
            ->getMockBuilder(TokenPool::class)
            ->setConstructorArgs([$this->tenant, $this->cache])
            ->setMethods(['requestToken'])
            ->getMock()
        ;
    }

    /**
     * @test
     */
    public function it_can_request_a_new_token()
    {
        $this->pool->expects($this->once())->method('requestToken')->willReturn($this->token);
        $this->cacheItem
            ->expects($this->once())
            ->method('set')
            ->with(json_encode($this->token))
        ;
        $this->cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with($this->lessThanOrEqual(3600))
        ;

        $this->assertSame($this->token, $this->pool->getToken());
        $this->assertSame($this->token->getId(), $this->pool->getTokenId());
        $this->assertSame($this->publicUrl, $this->pool->getPublicUrl());
    }

    /**
     * @test
     */
    public function it_can_return_a_cached_token()
    {
        $this->pool->expects($this->never())->method('requestToken');
        $this->cacheItem->expects($this->atLeastOnce())->method('get')->willReturn(json_encode($this->token));

        $this->assertEquals($this->token->getServiceCatalog('compute'), $this->pool->getToken()->getServiceCatalog('compute'));
        $this->assertEquals($this->token->getExpirationDate()->getTimestamp(), $this->pool->getToken()->getExpirationDate()->getTimestamp());
        $this->assertEquals($this->token->getId(), $this->pool->getTokenId());
        $this->assertEquals($this->publicUrl, $this->pool->getPublicUrl());
    }

    /**
     * @test
     * @expectedException \TreeHouse\Keystone\Client\Exception\TokenException
     */
    public function it_does_not_use_an_invalid_cached_token()
    {
        $this->cacheItem->expects($this->once())->method('get')->willReturn('[whatever]');

        $this->pool->getToken();
    }

    /**
     * @test
     */
    public function it_does_not_use_an_expired_cached_token()
    {
        $token = new Token('abcd1234', new \DateTime('-1 hour'));
        $this->cacheItem->expects($this->once())->method('get')->willReturn(json_encode($token));
        $this->pool->expects($this->once())->method('requestToken')->willReturn($this->token);

        $this->assertSame($this->token, $this->pool->getToken());
        $this->assertSame($this->token->getId(), $this->pool->getTokenId());
        $this->assertSame($this->publicUrl, $this->pool->getPublicUrl());
    }

    /**
     * @test
     */
    public function it_does_not_use_a_cached_token_when_forced()
    {
        $token = new Token('abcd1234', new \DateTime('+1 hour'));

        $this->cacheItem->expects($this->once())->method('get')->willReturn(json_encode($token));
        $this->pool->expects($this->once())->method('requestToken')->willReturn($this->token);

        $this->assertSame($this->token, $this->pool->getToken(true));
        $this->assertSame($this->token->getId(), $this->pool->getTokenId());
        $this->assertSame($this->publicUrl, $this->pool->getPublicUrl());
    }
}
