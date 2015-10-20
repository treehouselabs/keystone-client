<?php

namespace TreeHouse\Keystone\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use TreeHouse\Cache\CacheInterface;
use TreeHouse\Keystone\Client\ClientFactory;
use TreeHouse\Keystone\Client\Model\Tenant;
use TreeHouse\Keystone\Client\Model\Token;
use TreeHouse\Keystone\Client\TokenPool;

class ClientFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Tenant
     */
    private $tenant;

    /**
     * @var Token
     */
    private $token;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $url = 'http://example.org';
        $user = 'username';
        $pass = 'password';
        $serviceType = 'object-store';
        $serviceName = 'cdn';

        $this->tenant = new Tenant($url, $user, $pass, $serviceType, $serviceName);

        $this->token = new Token(uniqid(), new \DateTime('+1 hour'));
        $this->token->addServiceCatalog($serviceType, $serviceName, [['publicUrl' => 'http://example.org/v1']]);
    }

    /**
     * @test
     */
    public function it_can_be_constructed()
    {
        $factory = new ClientFactory($this->getCacheMock());

        $this->assertInstanceOf(ClientFactory::class, $factory);
    }

    /**
     * @test
     */
    public function it_can_create_a_client()
    {
        $factory = new ClientFactory($this->getCacheMock());

        $client = $factory->createClient($this->tenant);
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    /**
     * @test
     */
    public function it_can_create_a_client_with_a_specific_class()
    {
        $factory = new ClientFactory($this->getCacheMock(), TestClient::class);

        $client1 = $factory->createClient($this->tenant);
        $this->assertInstanceOf(TestClient::class, $client1);

        $client2 = $factory->createClient($this->tenant, [], Client::class);
        $this->assertInstanceOf(Client::class, $client2);
    }

    /**
     * @test
     */
    public function it_can_create_a_client_with_config()
    {
        $factory = new ClientFactory($this->getCacheMock());

        $client = $factory->createClient($this->tenant, ['foo' => 'bar']);
        $this->assertEquals('bar', $client->getConfig('foo'));
    }

    /**
     * @test
     */
    public function it_stores_the_token_pool_in_the_config()
    {
        $factory = new ClientFactory($this->getCacheMock());

        $client = $factory->createClient($this->tenant);
        $pool = $client->getConfig('token_pool');

        $this->assertInstanceOf(TokenPool::class, $pool);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|CacheInterface
     */
    private function getCacheMock()
    {
        return $this
            ->getMockBuilder(CacheInterface::class)
            ->getMockForAbstractClass()
        ;
    }
}

class TestClient extends Client
{
}
