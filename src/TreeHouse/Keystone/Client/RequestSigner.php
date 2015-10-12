<?php

namespace TreeHouse\Keystone\Client;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TreeHouse\Keystone\Client\Exception\TokenException;

class RequestSigner implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var TokenPool
     */
    protected $pool;

    /**
     * @param TokenPool $pool
     */
    public function __construct(TokenPool $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @param RequestInterface $request
     * @param bool             $forceNew
     *
     * @return RequestInterface
     */
    public function signRequest(RequestInterface $request, $forceNew = false)
    {
        try {
            // fetch token, optionally force a new one
            $this->pool->getToken($forceNew);

            // create a new request with the new uri and the token added to the headers
            $uri = Uri::resolve(
                new Uri($this->pool->getPublicUrl()),
                $request->getUri()
            );

            return $request
                ->withUri($uri)
                ->withHeader('X-Auth-Token', $this->pool->getTokenId())
            ;
        } catch (TokenException $e) {
            throw new ClientException('Could not obtain token', $request, null, $e);
        }
    }
}
