<?php

namespace TreeHouse\Keystone\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\RequestInterface;
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
        $this->tenant  = $tenant;
        $this->config  = $config;
        $this->class   = $class;
    }

    /**
     * @inheritdoc
     */
    public function createRequest($method, $url = null, array $options = [])
    {
        return $this->getActualClient()->createRequest($method, $url, $options);
    }

    /**
     * @inheritdoc
     */
    public function get($url = null, $options = [])
    {
        return $this->getActualClient()->get($url, $options);
    }

    /**
     * @inheritdoc
     */
    public function head($url = null, array $options = [])
    {
        return $this->getActualClient()->head($url, $options);
    }

    /**
     * @inheritdoc
     */
    public function delete($url = null, array $options = [])
    {
        return $this->getActualClient()->delete($url, $options);
    }

    /**
     * @inheritdoc
     */
    public function put($url = null, array $options = [])
    {
        return $this->getActualClient()->put($url, $options);
    }

    /**
     * @inheritdoc
     */
    public function patch($url = null, array $options = [])
    {
        return $this->getActualClient()->patch($url, $options);
    }

    /**
     * @inheritdoc
     */
    public function post($url = null, array $options = [])
    {
        return $this->getActualClient()->post($url, $options);
    }

    /**
     * @inheritdoc
     */
    public function options($url = null, array $options = [])
    {
        return $this->getActualClient()->options($url, $options);
    }

    /**
     * @inheritdoc
     */
    public function send(RequestInterface $request)
    {
        return $this->getActualClient()->send($request);
    }

    /**
     * @inheritdoc
     */
    public function getDefaultOption($keyOrPath = null)
    {
        return $this->getActualClient()->getDefaultOption($keyOrPath);
    }

    /**
     * @inheritdoc
     */
    public function setDefaultOption($keyOrPath, $value)
    {
        $this->getActualClient()->setDefaultOption($keyOrPath, $value);
    }

    /**
     * @inheritdoc
     */
    public function getBaseUrl()
    {
        return $this->getActualClient()->getBaseUrl();
    }

    /**
     * @inheritdoc
     */
    public function getEmitter()
    {
        return $this->getActualClient()->getEmitter();
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
