<?php

namespace TreeHouse\Keystone\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use TreeHouse\Keystone\Client\Middleware\Middleware;
use TreeHouse\Keystone\Client\Model\Tenant;

/**
 * Factory service to create a Guzzle client with Keystone authentication
 * support. This factory also deals with expiring tokens, by automatically
 * re-authenticating.
 *
 * Usage:
 *
 * ```
 * $tenant = new Tenant($tokenUrl, $username, $password, $serviceType);
 * $client = $factory->createClient($tenant);
 * ```
 */
class ClientFactory
{
    /**
     * The cache where tokens are stored.
     *
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * The class to construct a Guzzle client with.
     *
     * @var string
     */
    protected $clientClass;

    /**
     * Optional logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param CacheItemPoolInterface  $cache
     * @param string          $class
     * @param LoggerInterface $logger
     */
    public function __construct(CacheItemPoolInterface $cache, $class = null, LoggerInterface $logger = null)
    {
        $this->cache = $cache;
        $this->clientClass = $class ?: GuzzleClient::class;
        $this->logger = $logger;
    }

    /**
     * Creates a Guzzle client for communicating with a Keystone service.
     *
     * @param Tenant $tenant The keystone tenant to authenticate with
     * @param array  $config The client configuration
     * @param string $class  Optionally override the Guzzle client class
     *
     * @throws RequestException When a new token could not be requested.
     *
     * @return ClientInterface
     */
    public function createClient(Tenant $tenant, array $config = [], $class = null)
    {
        if (null === $class) {
            $class = $this->clientClass;
        }

        $pool = new TokenPool($tenant, $this->cache, $this->logger);
        $signer = new RequestSigner($pool);

        $stack = HandlerStack::create();
        $stack->before('http_errors', Middleware::signRequest($signer), 'signer');
        $stack->before('http_errors', Middleware::reauthenticate($signer), 'reauth');

        return new $class(array_merge($config, [
            'handler' => $stack,
            'token_pool' => $pool,
        ]));
    }
}
