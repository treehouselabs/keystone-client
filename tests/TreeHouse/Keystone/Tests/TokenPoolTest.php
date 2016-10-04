<?php

namespace TreeHouse\Keystone\Tests;

use Prophecy\Argument;
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
    private $endpointUrl = 'http://example.org';

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->tenant = new Tenant('http://example.org', 'user', 'p@$$', 'compute');
        $this->token = new Token('abcd1234', new \DateTime('+1 hour'));
        $this->token->addServiceCatalog('compute', 'api', [['publicurl' => $this->endpointUrl]]);

        $this->cache = $this->prophesize(CacheItemPoolInterface::class);

        $this->pool = $this
            ->getMockBuilder(TokenPool::class)
            ->setConstructorArgs([$this->tenant, $this->cache->reveal()])
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

        $cacheItem = $this->prophesize(CacheItemInterface::class);
        $cacheItem->get()->shouldBeCalled();
        $cacheItem->set(json_encode($this->token))->shouldBeCalled();
        $cacheItem->expiresAfter(Argument::that(function($arg) {
            $this->assertLessThanOrEqual(3600, $arg);

            return true;
        }))->shouldBeCalled();

        $this->cache->save($cacheItem)->shouldBeCalled();
        $this->cache->getItem(Argument::type('string'))->willReturn($cacheItem->reveal());

        $this->assertSame($this->token, $this->pool->getToken());
        $this->assertSame($this->token->getId(), $this->pool->getTokenId());
        $this->assertSame($this->endpointUrl, $this->pool->getEndpointUrl());
    }

    /**
     * @test
     */
    public function it_can_return_a_cached_token()
    {
        $this->pool->expects($this->never())->method('requestToken');

        $cacheItem = $this->prophesize(CacheItemInterface::class);
        $cacheItem->get()->willReturn(json_encode($this->token));
        $this->cache->getItem(Argument::type('string'))->willReturn($cacheItem->reveal());

        $this->assertEquals($this->token, $this->pool->getToken());
        $this->assertEquals($this->token->getId(), $this->pool->getTokenId());
        $this->assertEquals($this->endpointUrl, $this->pool->getEndpointUrl());
    }

    /**
     * @test
     * @expectedException \TreeHouse\Keystone\Client\Exception\TokenException
     */
    public function it_does_not_use_an_invalid_cached_token()
    {
        $cacheItem = $this->prophesize(CacheItemInterface::class);
        $cacheItem->get()->willReturn('[whatever]');
        $this->cache->getItem(Argument::type('string'))->willReturn($cacheItem->reveal());

        $this->pool->getToken();
    }

    /**
     * @test
     */
    public function it_does_not_use_an_expired_cached_token()
    {
        $token = new Token('abcd1234', new \DateTime('-1 hour'));
        $this->pool->expects($this->once())->method('requestToken')->willReturn($this->token);

        $cacheItem = $this->prophesize(CacheItemInterface::class);
        $cacheItem->get()->willReturn(json_encode($token));
        $cacheItem->set(json_encode($this->token))->shouldBeCalled();
        $cacheItem->expiresAfter(Argument::that(function($arg) {
            $this->assertLessThanOrEqual(3600, $arg);

            return true;
        }))->shouldBeCalled();

        $this->cache->save($cacheItem)->shouldBeCalled();
        $this->cache->getItem(Argument::type('string'))->willReturn($cacheItem->reveal());

        $this->assertSame($this->token, $this->pool->getToken());
        $this->assertSame($this->token->getId(), $this->pool->getTokenId());
        $this->assertSame($this->endpointUrl, $this->pool->getEndpointUrl());
    }

    /**
     * @test
     */
    public function it_does_not_use_a_cached_token_when_forced()
    {
        $token = new Token('abcd1234', new \DateTime('+1 hour'));

        $this->pool->expects($this->once())->method('requestToken')->willReturn($this->token);

        $cacheItem = $this->prophesize(CacheItemInterface::class);
        $cacheItem->get()->willReturn(json_encode($token));
        $cacheItem->set(json_encode($this->token))->shouldBeCalled();
        $cacheItem->expiresAfter(Argument::that(function($arg) {
            $this->assertLessThanOrEqual(3600, $arg);

            return true;
        }))->shouldBeCalled();

        $this->cache->save($cacheItem)->shouldBeCalled();
        $this->cache->getItem(Argument::type('string'))->willReturn($cacheItem->reveal());

        $this->assertSame($this->token, $this->pool->getToken(true));
        $this->assertSame($this->token->getId(), $this->pool->getTokenId());
        $this->assertSame($this->endpointUrl, $this->pool->getEndpointUrl());
    }
}
