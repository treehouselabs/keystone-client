<?php

namespace TreeHouse\Keystone\Tests;

use TreeHouse\Cache\CacheInterface;
use TreeHouse\Keystone\Client\Model\Tenant;
use TreeHouse\Keystone\Client\Model\Token;
use TreeHouse\Keystone\Client\TokenPool;

class TokenPoolTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|CacheInterface
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

        $this->cache = $this
            ->getMockBuilder(CacheInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['get', 'set'])
            ->getMockForAbstractClass()
        ;

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
        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->isType('string'),
                json_encode($this->token),
                $this->lessThanOrEqual(3600)
            )
        ;

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
        $this->cache->expects($this->once())->method('get')->willReturn(json_encode($this->token));

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
        $this->cache->expects($this->once())->method('get')->willReturn('[whatever]');

        $this->pool->getToken();
    }

    /**
     * @test
     */
    public function it_does_not_use_an_expired_cached_token()
    {
        $token = new Token('abcd1234', new \DateTime('-1 hour'));
        $this->cache->expects($this->once())->method('get')->willReturn(json_encode($token));
        $this->pool->expects($this->once())->method('requestToken')->willReturn($this->token);

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

        $this->cache->expects($this->any())->method('get')->willReturn(json_encode($token));
        $this->pool->expects($this->once())->method('requestToken')->willReturn($this->token);

        $this->assertSame($this->token, $this->pool->getToken(true));
        $this->assertSame($this->token->getId(), $this->pool->getTokenId());
        $this->assertSame($this->endpointUrl, $this->pool->getEndpointUrl());
    }
}
