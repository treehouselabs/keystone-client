<?php

namespace TreeHouse\Keystone\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Cache\CacheItemPoolInterface;
use TreeHouse\Keystone\Client\Exception\TokenException;
use TreeHouse\Keystone\Client\Model\Tenant;
use TreeHouse\Keystone\Client\Model\Token;

class TokenPool
{
    const TOKEN_KEY_FORMAT = 'keystone_token_3_%s';

    /**
     * @var Tenant
     */
    protected $tenant;

    /**
     * The cache where tokens are stored.
     *
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * Optional logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Internal client to request a new token with.
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * The public url that was obtained via the token.
     *
     * @var string
     */
    protected $publicUrl;

    /**
     * Cached copy of the token id.
     *
     * @var string
     */
    protected $tokenId;

    /**
     * @param Tenant          $tenant
     * @param CacheItemPoolInterface  $cache
     * @param LoggerInterface $logger
     */
    public function __construct(Tenant $tenant, CacheItemPoolInterface $cache, LoggerInterface $logger = null)
    {
        $this->tenant = $tenant;
        $this->cache = $cache;
        $this->logger = $logger ?: new NullLogger();
        $this->client = new Client();
    }

    /**
     * @return string
     */
    public function getPublicUrl()
    {
        if (null === $this->publicUrl) {
            $this->getToken();
        }

        return $this->publicUrl;
    }

    /**
     * @return string
     */
    public function getTokenId()
    {
        if (null === $this->tokenId) {
            $this->getToken();
        }

        return $this->tokenId;
    }

    /**
     * Returns a token to use for the keystone service. Uses cached instance
     * whenever possible.
     *
     * @param bool $forceNew Whether to force creating a new token
     *
     * @return Token
     */
    public function getToken($forceNew = false)
    {
        $tokenName = $this->getCacheKey();
        $cacheItem = $this->cache->getItem($tokenName);
        $cachedToken = $cacheItem->get();

        // see if token is in cache
        if (!$forceNew && $cachedToken !== null) {
            $this->logger->debug('Obtained token from cache');
            $token = $this->createToken($cachedToken);
        }

        if (!isset($token) || !($token instanceof Token) || $token->isExpired()) {
            // cache the token and set it to expire 5 seconds before the
            // expiration date, to avoid any concurrence errors.
            $token = $this->requestToken();
            $cacheItem->set(json_encode($token));
            $cacheItem->expiresAfter($token->getExpirationDate()->getTimestamp() - time() - 5);

            $this->cache->save($cacheItem);
        }

        // cache token properties
        $this->publicUrl = $this->getPublicUrlFromToken($token);
        $this->tokenId = $token->getId();
dump($token);
        return $token;
    }

    /**
     * @throws TokenException
     *
     * @return Token
     */
    protected function requestToken()
    {
        $this->logger->debug('Requesting a new token');

        $data = [
            'auth' => [
                'passwordCredentials' => [
                    'password' => $this->tenant->getPassword(),
                    'username' => $this->tenant->getUsername(),
                ],
            ],
        ];

        if ($name = $this->tenant->getTenantName()) {
            $data['auth']['tenantName'] = $name;
        }

        try {
            $response = $this->client->request('POST', $this->tenant->getTokenUrl(), [
                RequestOptions::JSON => $data,
            ]);

            return $this->createToken($response->getBody()->getContents());
        } catch (RequestException $e) {
            $this->logger->error(sprintf('Error requesting token: %s', $e->getMessage()));

            throw new TokenException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $jsonData
     *
     * @throws TokenException
     *
     * @return Token
     */
    private function createToken($jsonData)
    {
        $content = json_decode($jsonData, true);

        if (!is_array($content)) {
            throw new TokenException(sprintf('Could not decode JSON string: "%s"', $jsonData));
        }

        return Token::create($content);
    }

    /**
     * @param Token $token
     *
     * @throws TokenException
     *
     * @return string
     */
    private function getPublicUrlFromToken(Token $token)
    {
        $catalog = $token->getServiceCatalog($this->tenant->getServiceType(), $this->tenant->getServiceName());

        // use the first endpoint that has a public url
        foreach ($catalog as $endpoint) {
            $endpoint = array_change_key_case($endpoint, CASE_LOWER);
            if (array_key_exists('publicurl', $endpoint)) {
                return $endpoint['publicurl'];
            }
        }

        throw new TokenException('No endpoint with a public url found');
    }

    /**
     * @return string
     */
    private function getCacheKey()
    {
        return sprintf(self::TOKEN_KEY_FORMAT, rawurlencode($this->tenant->getTokenUrl()));
    }
}
