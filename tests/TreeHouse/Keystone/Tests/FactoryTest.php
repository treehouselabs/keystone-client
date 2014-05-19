<?php

namespace TreeHouse\Keystone\Tests;

use Psr\Log\LoggerInterface;
use TreeHouse\Cache\CacheInterface;
use TreeHouse\Keystone\Client\Client;
use TreeHouse\Keystone\Client\Factory;
use TreeHouse\Keystone\Client\Token;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $factory = new Factory($this->getCacheMock(), $this->getLoggerMock());

        $this->assertInstanceOf(Factory::class, $factory);
    }

    public function testCreateClient()
    {
        $factory = new Factory($this->getCacheMock(), $this->getLoggerMock());

        $url         = 'http://example.org';
        $user        = 'username';
        $pass        = 'password';
        $serviceType = 'service_type';
        $serviceName = 'service_name';

        $client = $factory->createClient($url, $user, $pass, $serviceType, $serviceName);
        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals($url, $client->getTokenUrl());
        $this->assertEquals($user, $client->getKeystoneUsername());
        $this->assertEquals($pass, $client->getKeystonePassword());
        $this->assertEquals($serviceType, $client->getServiceType());
        $this->assertEquals($serviceName, $client->getServiceName());
    }

    public function testGetClientToken()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Factory $factory */
        $factory = $this
            ->getMockBuilder(Factory::class)
            ->setConstructorArgs([$this->getCacheMock(), $this->getLoggerMock()])
            ->setMethods(['getToken'])
            ->getMock()
        ;

        $token = new Token(uniqid(), new \DateTime('+1 hour'));
        $token->addServiceCatalog('type', 'test', ['publicUrl' => 'http://example.org/v1']);

        $factory
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token))
        ;

        $client = $factory->createClient('http://example.org', 'user', 'pass', 'type');
        $this->assertEquals('http://example.org/v1', $client->getBaseUrl());
        $this->assertEquals('http://example.org/v1', $client->getPublicUrl());
    }

    public function testSetClientToken()
    {
        $url     = 'http://example.org';
        $type    = 'type';
        $id      = uniqid();
        $expires = new \DateTime('+1 hour');
        $token   = new Token($id, $expires);

        $factory = new Factory($this->getCacheMock(), $this->getLoggerMock());
        $client  = $factory->createClient($url, 'user', 'pass', $type);

        $otherUrl = 'http://example.org/other';
        $token->addServiceCatalog($type, 'test', ['publicUrl' => $otherUrl]);
        $client->setToken($token);

        $this->assertEquals($otherUrl, $client->getBaseUrl(), 'Token url should be updated when setting a new token');
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
}
