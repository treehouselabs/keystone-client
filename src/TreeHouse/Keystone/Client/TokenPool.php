<?php

namespace TreeHouse\Keystone\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TreeHouse\Cache\CacheInterface;
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
     * @var CacheInterface
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
     * The endpoint url that was obtained via the token.
     *
     * @var string
     */
    protected $endpointUrl;

    /**
     * Cached copy of the token id.
     *
     * @var string
     */
    protected $tokenId;

    /**
     * @param Tenant          $tenant
     * @param CacheInterface  $cache
     * @param LoggerInterface $logger
     */
    public function __construct(Tenant $tenant, CacheInterface $cache, LoggerInterface $logger = null)
    {
        $this->tenant = $tenant;
        $this->cache = $cache;
        $this->logger = $logger ?: new NullLogger();
        $this->client = new Client();
    }

    /**
     * @deprecated Please use getEndpointUrl() in favor of this
     * @return string
     */
    public function getPublicUrl() {
        return $this->getEndpointUrl();
    }

    /**
     * @return string
     */
    public function getEndpointUrl()
    {
        if (null === $this->endpointUrl) {
            $this->getToken();
        }

        return $this->endpointUrl;
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

        // see if token is in cache
        if (!$forceNew && $cachedToken = $this->cache->get($tokenName)) {
            $this->logger->debug('Obtained token from cache');
            $token = $this->createToken($cachedToken);
        }

        if (!isset($token) || !($token instanceof Token) || $token->isExpired()) {
            // cache the token and set it to expire 5 seconds before the
            // expiration date, to avoid any concurrence errors.
            $token = $this->requestToken();
            $this->cache->set($tokenName, json_encode($token), $token->getExpirationDate()->getTimestamp() - time() - 5);
        }

        // cache token properties
        $this->endpointUrl = $this->getEndpointUrlFromToken($token);
        $this->tokenId = $token->getId();

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
    private function getEndpointUrlFromToken(Token $token)
    {
        $catalog = $token->getServiceCatalog($this->tenant->getServiceType(), $this->tenant->getServiceName());

        // use the first endpoint that has was requested
        $endpointType = $this->tenant->getServiceEndpoint() . 'url';
        foreach ($catalog as $endpoint) {
            $endpoint = array_change_key_case($endpoint, CASE_LOWER);
            if (array_key_exists($endpointType, $endpoint)) {
                return $endpoint[$endpointType];
            }
        }

        throw new TokenException('No endpoint with a ' . $this->tenant->getServiceEndpoint() . ' url found');
    }

    /**
     * @return string
     */
    private function getCacheKey()
    {
        return sprintf(self::TOKEN_KEY_FORMAT, rawurlencode($this->tenant->getTokenUrl()));
    }
}
