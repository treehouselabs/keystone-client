<?php

namespace TreeHouse\Keystone\Tests\Functional;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use TreeHouse\Cache\Cache;
use TreeHouse\Cache\CacheInterface;
use TreeHouse\Cache\Driver\ArrayDriver;
use TreeHouse\Cache\Serializer\JsonSerializer;
use TreeHouse\Keystone\Client\ClientFactory;
use TreeHouse\Keystone\Client\Model\Tenant;
use TreeHouse\Keystone\Test\Server;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Tenant
     */
    private $tenant;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var string
     */
    private $service = 'compute';

    /**
     * @var string
     */
    private $url = 'http://127.0.0.1:8126';

    /**
     * @var string
     */
    private $tokenId = 'abcd1234';

    /**
     * @var ClientFactory
     */
    private $factory;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->cache = new Cache(new ArrayDriver(), new JsonSerializer());
        $this->factory = new ClientFactory($this->cache, Client::class);
        $this->tenant = new Tenant($this->url, 'user', 'p@$$', $this->service);

        Server::start();
        Server::flush();
    }

    /**
     * @test
     */
    public function it_works_with_a_new_valid_token()
    {
        $client = $this->factory->createClient($this->tenant);

        $body = 'Hello, world!';

        Server::enqueue([
            new Response(200, [], $this->getTokenJson()), // token request
            new Response(200, [], $body),                 // original request
        ]);

        $response = $client->request('get', '/foo');

        $this->assertCount(2, Server::received());
        $this->assertEquals($body, $response->getBody()->getContents());
    }

    /**
     * @test
     */
    public function it_works_with_an_existing_valid_token()
    {
        $this->cache->set($this->getTokenKey(), $this->getTokenJson());
        $client = $this->factory->createClient($this->tenant);

        $body = 'Hello, world!';

        Server::enqueue([
            new Response(200, [], $body), // original request
        ]);

        $response = $client->request('get', '/foo');

        $this->assertCount(1, Server::received());
        $this->assertEquals($body, $response->getBody()->getContents());
    }

    /**
     * @test
     */
    public function it_reauthenticates_with_an_existing_invalid_token()
    {
        $this->cache->set($this->getTokenKey(), $this->getTokenJson());
        $client = $this->factory->createClient($this->tenant);

        $body = 'Hello, world!';

        Server::enqueue([
            new Response(401),                            // original request, token is invalid (forged or something)
            new Response(200, [], $this->getTokenJson()), // token request
            new Response(200, [], $body),                 // original request (again)
        ]);

        $response = $client->request('get', '/foo');

        $this->assertCount(3, Server::received());
        $this->assertEquals($body, $response->getBody()->getContents());
    }

    /**
     * @test
     */
    public function it_reauthenticates_with_an_existing_expired_token()
    {
        $this->cache->set($this->getTokenKey(), $this->getTokenJson());
        $client = $this->factory->createClient($this->tenant);

        $body = 'Hello, world!';

        Server::enqueue([
            new Response(403),                            // original request, token has expired
            new Response(200, [], $this->getTokenJson()), // token request
            new Response(200, [], $body),                 // original request (again)
        ]);

        $response = $client->request('get', '/foo');

        $this->assertCount(3, Server::received());
        $this->assertEquals($body, $response->getBody()->getContents());
    }

    /**
     * @test
     * @expectedException \GuzzleHttp\Exception\ClientException
     */
    public function it_fails_with_failed_reathentication()
    {
        $this->cache->set($this->getTokenKey(), $this->getTokenJson());
        $client = $this->factory->createClient($this->tenant);

        Server::enqueue([
            new Response(403), // original request, token has expired
            new Response(401), // token request, invalid credentials
        ]);

        try {
            $client->request('get', '/foo');
        } catch (ClientException $e) {
            // original request should not have repeated
            $this->assertCount(2, Server::received());

            throw $e;
        }
    }

    /**
     * @return string
     */
    private function getTokenKey()
    {
        return sprintf('keystone_token_%s', rawurlencode($this->url));
    }

    /**
     * @param string $ttl
     *
     * @return string
     */
    private function getTokenJson($ttl = '1 hour')
    {
        $expires = date(\DateTime::ISO8601, strtotime($ttl));

        return <<<JSON
{
    "access": {
        "token": {
            "id": "$this->tokenId",
            "expires": "$expires"
        },
        "serviceCatalog": [
            {
                "name": "test",
                "type": "$this->service",
                "endpoints": [
                    {
                        "adminurl": "$this->url",
                        "publicurl": "$this->url"
                    }
                ]
            }
        ]
    }
}
JSON;
    }
}
