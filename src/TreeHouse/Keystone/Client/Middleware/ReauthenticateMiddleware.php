<?php

namespace TreeHouse\Keystone\Client\Middleware;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TreeHouse\Keystone\Client\RequestSigner;

class ReauthenticateMiddleware
{
    /**
     * @var callable
     */
    protected $handler;

    /**
     * @var RequestSigner
     */
    protected $signer;

    /**
     * @param callable      $handler
     * @param RequestSigner $signer
     */
    public function __construct(callable $handler, RequestSigner $signer)
    {
        $this->handler = $handler;
        $this->signer = $signer;
    }

    /**
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->handler;

        return $fn($request, $options)
            ->then(
                $this->onFulfilled($request, $options),
                $this->onRejected($request, $options)
            );
    }

    /**
     * @param ResponseInterface $response
     *
     * @return bool
     */
    protected function isUnauthenticatedResponse(ResponseInterface $response)
    {
        return in_array($response->getStatusCode(), [401, 403]);
    }

    /**
     * @param RequestInterface $request
     *
     * @return bool
     */
    protected function reauthenticate(RequestInterface $request)
    {
        return $this->signer->signRequest($request, true);
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array             $options
     *
     * @return mixed
     */
    private function handleUnauthenticatedResponse(RequestInterface $request, ResponseInterface $response, array $options)
    {
        if (!$this->isUnauthenticatedResponse($response)) {
            return null;
        }

        $handler = $this->handler;
        $newRequest = $this->reauthenticate($request);

        return $handler($newRequest, $options);
    }

    /**
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return \Closure
     */
    private function onFulfilled(RequestInterface $request, array $options)
    {
        return function (ResponseInterface $response) use ($request, $options) {
            if ($next = $this->handleUnauthenticatedResponse($request, $response, $options)) {
                return $next;
            }

            return $response;
        };
    }

    /**
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return \Closure
     */
    private function onRejected(RequestInterface $request, array $options)
    {
        return function ($reason) use ($request, $options) {
            if (!$reason instanceof ClientException) {
                return new RejectedPromise($reason);
            }

            $response = $reason->getResponse();

            if ($next = $this->handleUnauthenticatedResponse($request, $response, $options)) {
                return $next;
            }

            return new RejectedPromise($reason);
        };
    }
}
