<?php

namespace TreeHouse\Keystone\Client;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\RequestInterface;
use TreeHouse\Keystone\Client\Model\Tenant;

/**
 * Decorator to implement a lazy loading Keystone client.
 *
 * This is useful when you want to inject a client without directly requesting
 * a new token.
 */
class KeystoneClient implements ClientInterface
{
    /**
     * @var ClientFactory
     */
    protected $factory;

    /**
     * @var Tenant
     */
    protected $tenant;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $class;

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @param ClientFactory $factory
     * @param Tenant        $tenant
     * @param array         $config
     * @param null          $class
     */
    public function __construct(ClientFactory $factory, Tenant $tenant, array $config = [], $class = null)
    {
        $this->factory = $factory;
        $this->tenant = $tenant;
        $this->config = $config;
        $this->class = $class;
    }

    /**
     * {@inheritdoc}
     */
    public function send(RequestInterface $request, array $options = [])
    {
        return $this->getActualClient()->send($request, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsync(RequestInterface $request, array $options = [])
    {
        return $this->getActualClient()->sendAsync($request, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function request($method, $uri, array $options = [])
    {
        return $this->getActualClient()->request($method, $uri, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function requestAsync($method, $uri, array $options = [])
    {
        return $this->getActualClient()->requestAsync($method, $uri, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig($option = null)
    {
        return $this->getActualClient()->getConfig($option);
    }

    /**
     * @return ClientInterface
     */
    protected function getActualClient()
    {
        if (null === $this->client) {
            $this->client = $this->factory->createClient($this->tenant, $this->config, $this->class);
        }

        return $this->client;
    }
}
