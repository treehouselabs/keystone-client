<?php

namespace TreeHouse\Keystone\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $url = 'http://example.org';
        $user = 'username';
        $pass = 'password';
        $serviceType = 'object-store';
        $serviceName = 'cdn';

        $this->tenant = new Tenant($url, $user, $pass, $serviceType, $serviceName);

        $this->client = $this->getMockForAbstractClass(ClientInterface::class);
    }

    /**
     * @test
     */
    public function it_can_be_constructed()
    {
        $config = ['foo' => 'bar'];
        $class = ClientInterface::class;

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
        $client->request('GET', '/foo');

        // two calls should only create 1 actual client
        $client->request('GET', '/bar');
    }

    /**
     * @test
     * @dataProvider sendMethodDataProvider
     *
     * @param string $method
     * @param string $httpMethod
     * @param string $uri
     * @param array  $headers
     */
    public function it_can_relay_send_methods($method, $httpMethod, $uri, $headers)
    {
        $options = ['foo' => 'bar'];
        $request = new Request($httpMethod, $uri, $headers);
        $keystoneClient = new KeystoneClient($this->getFactoryMock($this->client), $this->tenant);

        $response = new Response(200);
        $this->client
            ->expects($this->once())
            ->method($method)
            ->with($request, $options)
            ->willReturn($response)
        ;

        $this->assertSame($response, $keystoneClient->$method($request, $options));
    }

    /**
     * @return array
     */
    public function sendMethodDataProvider()
    {
        return [
            ['send',      'GET', '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
            ['sendAsync', 'GET', '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
        ];
    }

    /**
     * @test
     * @dataProvider requestMethodDataProvider
     *
     * @param string $method
     * @param string $httpMethod
     * @param string $uri
     * @param array  $options
     */
    public function it_can_relay_request_methods($method, $httpMethod, $uri, $options)
    {
        $keystoneClient = new KeystoneClient($this->getFactoryMock($this->client), $this->tenant);

        $response = new Response(200);
        $this->client
            ->expects($this->once())
            ->method($method)
            ->with($httpMethod, $uri, $options)
            ->willReturn($response)
        ;

        $this->assertSame($response, $keystoneClient->$method($httpMethod, $uri, $options));
    }

    public function requestMethodDataProvider()
    {
        return [
            ['request',      'GET', '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
            ['requestAsync', 'GET', '/foo', ['headers' => ['Content-Type' => 'text/plain']]],
        ];
    }

    /**
     * @test
     */
    public function it_can_relay_config_method()
    {
        $keystoneClient = new KeystoneClient($this->getFactoryMock($this->client), $this->tenant);

        $name = 'foo';
        $value = 'bar';

        $this->client
            ->expects($this->once())
            ->method('getConfig')
            ->with($name)
            ->willReturn($value)
        ;

        $this->assertSame($value, $keystoneClient->getConfig($name));
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
