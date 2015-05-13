<?php

namespace TreeHouse\Keystone\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Event\Emitter;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use TreeHouse\Keystone\Client\ClientFactory;
use TreeHouse\Keystone\Client\KeystoneClient;
use TreeHouse\Keystone\Client\Model\Tenant;
use TreeHouse\Keystone\Client\Model\Token;

class KeystoneClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Tenant
     */
    protected $tenant;

    /**
     * @var Token
     */
    protected $token;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ClientInterface
     */
    protected $client;

    protected function setUp()
    {
        $url         = 'http://example.org';
        $user        = 'username';
        $pass        = 'password';
        $serviceType = 'object-store';
        $serviceName = 'cdn';

        $this->tenant = new Tenant($url, $user, $pass, $serviceType, $serviceName);

        $this->client  = $this->getMockForAbstractClass(ClientInterface::class);
    }

    public function testConstructor()
    {
        $config = ['foo' => 'bar'];
        $class  = ClientInterface::class;

        $factory = $this->getFactoryMock();
        $factory
            ->expects($this->once())
            ->method('createClient')
            ->with($this->tenant, $config, $class)
            ->willReturn($this->client)
        ;

        $client = new KeystoneClient($factory, $this->tenant, $config, $class);

        $this->assertInstanceOf(KeystoneClient::class, $client);

        // now call something that gets the actual client
        $client->createRequest('/foo', []);

        // two calls should only create 1 actual client
        $client->createRequest('/bar', []);
    }

    public function testCreateRequest()
    {
        $keystoneClient = new KeystoneClient($this->getFactoryMock($this->client), $this->tenant);

        $response = new Response(200);
        $this->client
            ->expects($this->once())
            ->method('createRequest')
            ->with('get', '/foo', [])
            ->willReturn($response)
        ;

        $this->assertSame($response, $keystoneClient->createRequest('get', '/foo', []));
    }

    public function testSendRequest()
    {
        $keystoneClient = new KeystoneClient($this->getFactoryMock($this->client), $this->tenant);

        $request  = new Request('get', 'foo');
        $response = new Response(200);
        $this->client
            ->expects($this->once())
            ->method('send')
            ->with($request)
            ->willReturn($response)
        ;

        $this->assertSame($response, $keystoneClient->send($request));
    }

    /**
     * @dataProvider httpMethodDataProvider
     *
     * @param       $method
     * @param       $url
     * @param array $options
     */
    public function testHttpMethods($method, $url, array $options = [])
    {
        $keystoneClient = new KeystoneClient($this->getFactoryMock($this->client), $this->tenant);

        $response = new Response(200);
        $this->client
            ->expects($this->once())
            ->method($method)
            ->with($url, $options)
            ->willReturn($response)
        ;

        $this->assertSame($response, $keystoneClient->$method($url, $options));
    }

    public function httpMethodDataProvider()
    {
        return [
            ['head',    '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
            ['get',     '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
            ['put',     '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
            ['post',    '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
            ['patch',   '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
            ['delete',  '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
            ['options', '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
        ];
    }

    public function testDefaultOptions()
    {
        $keystoneClient = new KeystoneClient($this->getFactoryMock($this->client), $this->tenant);

        $this->client
            ->expects($this->once())
            ->method('setDefaultOption')
            ->with('foo', 'bar')
        ;

        $this->client
            ->expects($this->once())
            ->method('getDefaultOption')
            ->with('foo')
            ->willReturn('bar')
        ;

        $keystoneClient->setDefaultOption('foo', 'bar');
        $this->assertSame('bar', $keystoneClient->getDefaultOption('foo'));
    }

    public function testGetEmitter()
    {
        $keystoneClient = new KeystoneClient($this->getFactoryMock($this->client), $this->tenant);
        $emitter = new Emitter();

        $this->client
            ->expects($this->once())
            ->method('getEmitter')
            ->willReturn($emitter)
        ;

        $this->assertSame($emitter, $keystoneClient->getEmitter());
    }

    public function testGetBaseUrl()
    {
        $keystoneClient = new KeystoneClient($this->getFactoryMock($this->client), $this->tenant);
        $url = 'http://example.org';

        $this->client
            ->expects($this->once())
            ->method('getBaseUrl')
            ->willReturn($url)
        ;

        $this->assertSame($url, $keystoneClient->getBaseUrl());
    }

    /**
     * @param ClientInterface $client
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|ClientFactory
     */
    protected function getFactoryMock(ClientInterface $client = null)
    {
        $factory = $this
            ->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['createClient'])
            ->getMock()
        ;

        if ($client) {
            $factory->expects($this->once())->method('createClient')->willReturn($client);
        }

        return $factory;
    }
}
