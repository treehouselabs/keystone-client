<?php

namespace TreeHouse\Keystone\Client\Subscriber;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\RequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TreeHouse\Cache\CacheInterface;
use TreeHouse\Keystone\Client\Model\Tenant;
use TreeHouse\Keystone\Client\Model\Token;

class KeystoneTokenSubscriber implements SubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var Tenant
     */
    protected $tenant;

    /**
     * @param CacheInterface $cache
     * @param Tenant         $tenant
     */
    public function __construct(CacheInterface $cache, Tenant $tenant)
    {
        $this->cache  = $cache;
        $this->tenant = $tenant;
    }

    /**
     * @inheritdoc
     */
    public function getEvents()
    {
        return [
            'before' => ['beforeRequest'],
            'error'  => ['onRequestError']
        ];
    }

    /**
     * Returns a token to use for the keystone service. Uses cached instance
     * whenever possible.
     *
     * @param ClientInterface $client
     * @param boolean         $forceNew Whether to force creating a new token
     *
     * @return Token
     */
    public function getToken(ClientInterface $client, $forceNew = false)
    {
        $tokenName = sprintf('keystone_token_%s', rawurlencode($this->tenant->getTokenUrl()));

        // see if token is in cache
        if (!$forceNew && $cachedToken = $this->cache->get($tokenName)) {
            $token = Token::create(json_decode($cachedToken, true));

            if ($this->logger) {
                $this->logger->debug('Obtained token from cache');
            }
        }

        if (!isset($token) || !($token instanceof Token) || $token->isExpired()) {
            // cache the token and set it to expire 5 seconds before the
            // expiration date, to avoid any concurrence errors.
            $token = $this->requestToken($client);
            $this->cache->set($tokenName, json_encode($token), $token->getExpirationDate()->getTimestamp() - time() - 5);
        }

        return $token;
    }

    /**
     * @param BeforeEvent $event
     */
    public function beforeRequest(BeforeEvent $event)
    {
        $client  = $event->getClient();
        $request = $event->getRequest();

        if ($this->isTokenRequest($request)) {
            return;
        }

        // sign the request with our token
        $this->signRequest($request, $this->getToken($client));
    }

    /**
     * Listener for request errors. Handles requests with expired
     * authentication, by reauthenticating and sending the request again.
     *
     * @param ErrorEvent $event
     */
    public function onRequestError(ErrorEvent $event)
    {
        $client  = $event->getClient();
        $request = $event->getRequest();

        // if this is the token-url, stop now because we won't be able to fetch a token
        if ($this->isTokenRequest($request)) {
            return;
        }

        if (!$response = $event->getResponse()) {
            return;
        }

        // if token validity expired, re-request with a new token.
        if (in_array($response->getStatusCode(), [401, 403])) {
            if ($this->logger) {
                $this->logger->debug('Token invalid or expired');
            }

            // retry only once
            if ($event->getRetryCount() >= 1) {
                if ($this->logger) {
                    $this->logger->error('No more retries left');
                }

                return;
            }

            // set new token in client
            $token = $this->getToken($client, true);
            $this->signRequest($request, $token);

            $event->retry();
        }
    }

    /**
     * @param RequestInterface $request
     *
     * @return boolean
     */
    protected function isTokenRequest(RequestInterface $request)
    {
        return $request->getUrl() === $this->tenant->getTokenUrl();
    }

    /**
     * @param ClientInterface $client
     *
     * @return Token
     */
    private function requestToken(ClientInterface $client)
    {
        if ($this->logger) {
            $this->logger->debug('Requesting a new token');
        }

        $data = [
            'auth' => [
                'passwordCredentials' => [
                    'password' => $this->tenant->getPassword(),
                    'username' => $this->tenant->getUsername()
                ]
            ]
        ];

        if ($name = $this->tenant->getTenantName()) {
            $data['auth']['tenantName'] = $name;
        }

        // make sure token isn't sent in request
        $response = $client->post($this->tenant->getTokenUrl(), ['json' => $data]);

        return Token::create($response->json());
    }

    /**
     * @param RequestInterface $request
     * @param Token            $token
     */
    private function signRequest(RequestInterface $request, Token $token)
    {
        $request->setHeader('X-Auth-Token', $token->getId());
    }
}
