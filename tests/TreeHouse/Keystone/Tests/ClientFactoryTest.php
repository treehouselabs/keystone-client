<?php

namespace TreeHouse\Keystone\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use TreeHouse\Cache\CacheInterface;
use TreeHouse\Keystone\Client\ClientFactory;
use TreeHouse\Keystone\Client\Model\Tenant;
use TreeHouse\Keystone\Client\Model\Token;
use TreeHouse\Keystone\Client\Subscriber\KeystoneTokenSubscriber;

class ClientFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Tenant
     */
    protected $tenant;

    /**
     * @var Token
     */
    protected $token;

    protected function setUp()
    {
        $url         = 'http://example.org';
        $user        = 'username';
        $pass        = 'password';
        $serviceType = 'object-store';
        $serviceName = 'cdn';

        $this->tenant = new Tenant($url, $user, $pass, $serviceType, $serviceName);

        $this->token = new Token(uniqid(), new \DateTime('+1 hour'));
        $this->token->addServiceCatalog($serviceType, $serviceName, [['publicUrl' => 'http://example.org/v1']]);
    }

    public function testConstructor()
    {
        $factory = new ClientFactory($this->getCacheMock(), 'foo', $this->getLoggerMock());

        $this->assertInstanceOf(ClientFactory::class, $factory);
    }

    public function testCreateClient()
    {
        $cache = $this->getCacheMock();
        $cache->expects($this->once())->method('get')->will($this->returnValue(json_encode($this->token)));

        $factory = new ClientFactory($cache);

        $client = $factory->createClient($this->tenant);
        $this->assertInstanceOf(ClientInterface::class, $client);

        /** @var KeystoneTokenSubscriber $subscriber */
        $subscriber = $this
            ->getMockBuilder(KeystoneTokenSubscriber::class)
            ->disableOriginalConstructor()
            ->setMethods(['getToken'])
            ->getMock()
        ;

        foreach ($subscriber->getEvents() as $name => $events) {
            $this->assertTrue($client->getEmitter()->hasListeners($name));

            $listeners = $client->getEmitter()->listeners($name);

            foreach ($events as $method) {
                $found = false;
                foreach ($listeners as $listener) {
                    if (!is_array($listener)) {
                        continue;
                    }

                    list ($subscriber, $name) = $listener;
                    if ($subscriber instanceof KeystoneTokenSubscriber && $name === $method) {
                        $found = true;
                    }
                }

                $this->assertTrue($found, sprintf('KeystoneTokenSubscriber::%s not attached to emitter', $method));
            }
        }
    }

    public function testClientWithToken()
    {
        $subscriber = $this->getSubscriberMock();

        $factory = $this->getFactoryMock($this->getCacheMock());
        $factory->expects($this->once())->method('createSubscriber')->will($this->returnValue($subscriber));

        $client = $factory->createClient($this->tenant);
        $this->assertEquals('http://example.org/v1', $client->getBaseUrl());
    }

    public function testCreateClientWithLogger()
    {
        $logger     = $this->getLoggerMock();
        $subscriber = $this->getSubscriberMock();
        $subscriber->expects($this->once())->method('setLogger')->with($logger);

        $factory = $this->getFactoryMock($this->getCacheMock(), null, $logger);
        $factory->expects($this->once())->method('createSubscriber')->will($this->returnValue($subscriber));

        $factory->createClient($this->tenant);
    }

    public function testCreateClientWithClass()
    {
        $cache = $this->getCacheMock();
        $cache->expects($this->once())->method('get')->will($this->returnValue(json_encode($this->token)));

        $factory = new ClientFactory($cache, TestClient::class);

        $client = $factory->createClient($this->tenant);
        $this->assertInstanceOf(TestClient::class, $client);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCreateClientWithoutPublicEndpoint()
    {
        $token = new Token(uniqid(), new \DateTime('+1 hour'));
        $token->addServiceCatalog('object-store', 'cdn', [['adminUrl' => 'http://example.org/v1']]);

        $cache = $this->getCacheMock();
        $cache->expects($this->once())->method('get')->will($this->returnValue(json_encode($token)));

        $factory = new ClientFactory($cache, TestClient::class);
        $factory->createClient($this->tenant);
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

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|LoggerInterface
     */
    private function getLoggerMock()
    {
        return $this
            ->getMockBuilder(LoggerInterface::class)
            ->getMockForAbstractClass()
        ;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|KeystoneTokenSubscriber
     */
    protected function getSubscriberMock()
    {
        $mock = $this
            ->getMockBuilder(KeystoneTokenSubscriber::class)
            ->setConstructorArgs([$this->getCacheMock(), $this->tenant])
            ->setMethods(['getToken', 'setLogger'])
            ->getMock()
        ;

        $mock->expects($this->any())->method('getToken')->will($this->returnValue($this->token));

        return $mock;
    }

    /**
     * @param CacheInterface  $cache
     * @param string          $class
     * @param LoggerInterface $logger
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|ClientFactory
     */
    protected function getFactoryMock(CacheInterface $cache, $class = null, LoggerInterface $logger = null)
    {
        return $this
            ->getMockBuilder(ClientFactory::class)
            ->setConstructorArgs([$cache, $class, $logger])
            ->setMethods(['createSubscriber'])
            ->getMock()
        ;
    }
}

class TestClient extends Client
{
}
