<?php

namespace TreeHouse\Keystone\Client\Middleware;

use Psr\Http\Message\RequestInterface;
use TreeHouse\Keystone\Client\RequestSigner;

class Middleware
{
    /**
     * @param RequestSigner $signer
     *
     * @return \Closure
     */
    public static function signRequest(RequestSigner $signer)
    {
        return function (callable $handler) use ($signer) {
            return function (RequestInterface $request, array $options) use ($handler, $signer) {
                return $handler($signer->signRequest($request), $options);
            };
        };
    }

    /**
     * @param RequestSigner $signer
     *
     * @return \Closure
     */
    public static function reauthenticate(RequestSigner $signer)
    {
        return function (callable $handler) use ($signer) {
            return new ReauthenticateMiddleware($handler, $signer);
        };
    }
}
