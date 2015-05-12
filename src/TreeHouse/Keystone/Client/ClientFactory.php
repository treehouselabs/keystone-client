<?php

namespace TreeHouse\Keystone\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use TreeHouse\Cache\CacheInterface;
use TreeHouse\Keystone\Client\Model\Tenant;
use TreeHouse\Keystone\Client\Model\Token;
use TreeHouse\Keystone\Client\Subscriber\KeystoneTokenSubscriber;

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
     * @var CacheInterface
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
     * @param CacheInterface  $cache
     * @param string          $class
     * @param LoggerInterface $logger
     */
    public function __construct(CacheInterface $cache, $class = null, LoggerInterface $logger = null)
    {
        $this->cache       = $cache;
        $this->clientClass = $class ?: GuzzleClient::class;
        $this->logger      = $logger;
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

        // create a new subscriber
        $subscriber = $this->getSubscriber($tenant);

        // we need a temporary client to fetch the token, since we need the
        // public url from it, and we cannot change the base url later on.
        $token = $subscriber->getToken(new $class($config));

        /** @var ClientInterface $client */
        $client = new $class(array_merge($config, ['base_url' => $this->getPublicUrl($tenant, $token)]));
        $client->getEmitter()->attach($subscriber);

        return $client;
    }

    /**
     * @param Tenant $tenant
     * @param Token  $token
     *
     * @return string
     */
    protected function getPublicUrl(Tenant $tenant, Token $token)
    {
        $catalog = $token->getServiceCatalog($tenant->getServiceType(), $tenant->getServiceName());

        // use the first endpoint that has a public url
        foreach ($catalog as $endpoint) {
            $endpoint = array_change_key_case($endpoint, CASE_LOWER);
            if (array_key_exists('publicurl', $endpoint)) {
                return $endpoint['publicurl'];
            }
        }

        throw new \RuntimeException('No endpoint with a public url found');
    }

    /**
     * @param Tenant $tenant
     *
     * @return KeystoneTokenSubscriber
     */
    protected function getSubscriber(Tenant $tenant)
    {
        $subscriber = $this->createSubscriber($tenant);

        if ($this->logger) {
            $subscriber->setLogger($this->logger);
        }

        return $subscriber;
    }

    /**
     * @param Tenant $tenant
     *
     * @return KeystoneTokenSubscriber
     */
    protected function createSubscriber(Tenant $tenant)
    {
        return new KeystoneTokenSubscriber($this->cache, $tenant);
    }
}
