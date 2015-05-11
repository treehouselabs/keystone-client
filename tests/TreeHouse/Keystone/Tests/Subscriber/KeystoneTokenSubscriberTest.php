<?php

namespace TreeHouse\Keystone\Tests\Subscriber;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Transaction;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;
use TreeHouse\Cache\CacheInterface;
use TreeHouse\Keystone\Client\Model\Tenant;
use TreeHouse\Keystone\Client\Model\Token;
use TreeHouse\Keystone\Client\Subscriber\KeystoneTokenSubscriber;

class KeystoneTokenSubscriberTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Tenant
     */
    protected $tenant;

    /**
     * @var Token
     */
    protected $token;

    protected function setUp()
    {
        $url         = 'http://example.org';
        $user        = 'username';
        $pass        = 'password';
        $serviceType = 'object-store';
        $serviceName = 'cdn';
        $tenantName  = 'TreeHouse';

        $this->tenant = new Tenant($url, $user, $pass, $serviceType, $serviceName, $tenantName);

        $this->token = new Token(uniqid(), new \DateTime('+1 hour'));
        $this->token->addServiceCatalog($serviceType, $serviceName, [['publicUrl' => 'http://example.org/v1']]);
    }

    /**
     * Tests construction.
     */
    public function testConstructor()
    {
        $subscriber = new KeystoneTokenSubscriber($this->getCacheMock(), $this->tenant);

        $this->assertInstanceOf(KeystoneTokenSubscriber::class, $subscriber);
        $this->assertInstanceOf(SubscriberInterface::class, $subscriber);
    }

    /**
     * Tests that the subscriber has the right events.
     */
    public function testEvents()
    {
        $subscriber = new KeystoneTokenSubscriber($this->getCacheMock(), $this->tenant);

        $this->assertArrayHasKey('before', $subscriber->getEvents());
        $this->assertArrayHasKey('error', $subscriber->getEvents());
    }

    /**
     * Tests that the subscriber is logger aware.
     */
    public function testLogger()
    {
        $subscriber = new KeystoneTokenSubscriber($this->getCacheMock(), $this->tenant);
        $subscriber->setLogger(new NullLogger());

        $this->assertInstanceOf(LoggerAwareInterface::class, $subscriber);
    }

    /**
     * Tests that a token can be fetched from cache.
     */
    public function testGetTokenFromCache()
    {
        $cache = $this->getCacheMock();
        $cache
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue(json_encode($this->token)))
        ;

        $subscriber = new KeystoneTokenSubscriber($cache, $this->tenant);
        $subscriber->setLogger(new NullLogger());
        $this->assertEquals($this->token, $subscriber->getToken(new Client()));
    }

    /**
     * Tests that a new token can be requested.
     */
    public function testRequestNewToken()
    {
        $ttl = $this->token->getExpirationDate()->getTimestamp() - time() - 5;

        $cache = $this->getCacheMock();
        $cache
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue(null))
        ;
        $cache
            ->expects($this->once())
            ->method('set')
            ->with($this->stringStartsWith('keystone_token_'), json_encode($this->token), $this->lessThanOrEqual($ttl))
        ;

        $client = $this->createClientMock();
        $client
            ->expects($this->once())
            ->method('post')
            ->will($this->returnValue(
                new Response(200, [], Stream::factory(json_encode($this->token)))
            ))
        ;

        $subscriber = new KeystoneTokenSubscriber($cache, $this->tenant);
        $subscriber->setLogger(new NullLogger());

        $token = $subscriber->getToken($client);
        $this->assertInstanceOf(Token::class, $token);
        $this->assertEquals($this->token->getId(), $token->getId());
    }

    /**
     * Tests that a new token can be requested, regardless of a cached one.
     */
    public function testForceRequestToken()
    {
        $ttl = $this->token->getExpirationDate()->getTimestamp() - time() - 5;

        $cache = $this->getCacheMock();
        $cache
            ->expects($this->never())
            ->method('get')
        ;
        $cache
            ->expects($this->once())
            ->method('set')
            ->with($this->stringStartsWith('keystone_token_'), json_encode($this->token), $this->lessThanOrEqual($ttl))
        ;

        $client = $this->createClientMock();
        $client
            ->expects($this->once())
            ->method('post')
            ->will($this->returnValue(
                new Response(200, [], Stream::factory(json_encode($this->token)))
            ))
        ;

        $subscriber = new KeystoneTokenSubscriber($cache, $this->tenant);
        $subscriber->setLogger(new NullLogger());

        $token = $subscriber->getToken($client, true);
        $this->assertInstanceOf(Token::class, $token);
        $this->assertEquals($this->token->getId(), $token->getId());
    }

    /**
     * Tests that a new token is requested when a cached token has expired.
     */
    public function testRequestTokenWhenExpired()
    {
        $ttl = $this->token->getExpirationDate()->getTimestamp() - time() - 5;

        $token = new Token(uniqid(), new \DateTime('-1 minute'));
        $cache = $this->getCacheMock();
        $cache
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue(json_encode($token)))
        ;
        $cache
            ->expects($this->once())
            ->method('set')
            ->with($this->stringStartsWith('keystone_token_'), json_encode($this->token), $this->lessThanOrEqual($ttl))
        ;

        $client = $this->createClientMock();
        $client
            ->expects($this->once())
            ->method('post')
            ->will($this->returnValue(
                new Response(200, [], Stream::factory(json_encode($this->token)))
            ))
        ;

        $subscriber = new KeystoneTokenSubscriber($cache, $this->tenant);
        $subscriber->setLogger(new NullLogger());

        $token = $subscriber->getToken($client);
        $this->assertInstanceOf(Token::class, $token);
        $this->assertEquals($this->token->getId(), $token->getId());
    }

    /**
     * Tests that the 'before' event adds the token in the request headers.
     */
    public function testBeforeRequestEvent()
    {
        $cache = $this->getCacheMock();
        $cache
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue(json_encode($this->token)))
        ;

        $request = new Request('GET', 'http://api.example.org');
        $this->assertFalse($request->hasHeader('X-Auth-Token'));

        $subscriber = new KeystoneTokenSubscriber($cache, $this->tenant);
        $subscriber->setLogger(new NullLogger());
        $subscriber->beforeRequest(new BeforeEvent(new Transaction(new Client(), $request)));

        $this->assertTrue($request->hasHeader('X-Auth-Token'));
        $this->assertSame($this->token->getId(), (string) $request->getHeader('X-Auth-Token'));
    }

    /**
     * Tests that the 'before' event does not add the token in the request headers when the token itself is requested.
     */
    public function testBeforeTokenRequestEvent()
    {
        $request = new Request('POST', $this->tenant->getTokenUrl());
        $this->assertFalse($request->hasHeader('X-Auth-Token'));

        $subscriber = new KeystoneTokenSubscriber($this->getCacheMock(), $this->tenant);
        $subscriber->setLogger(new NullLogger());
        $subscriber->beforeRequest(new BeforeEvent(new Transaction(new Client(), $request)));

        $this->assertFalse($request->hasHeader('X-Auth-Token'));
    }

    /**
     * Tests that the 'error' event does not do anything when no response was given.
     */
    public function testErrorRequestNoResponseEvent()
    {
        $request = new Request('GET', 'http://api.example.org');
        $this->assertFalse($request->hasHeader('X-Auth-Token'));

        $subscriber = new KeystoneTokenSubscriber($this->getCacheMock(), $this->tenant);
        $subscriber->setLogger(new NullLogger());
        $subscriber->onRequestError(new ErrorEvent(new Transaction(new Client(), $request)));

        $this->assertFalse($request->hasHeader('X-Auth-Token'));
    }

    /**
     * Tests that the 'error' event does not do anything when the token was requested.
     */
    public function testErrorTokenRequestEvent()
    {
        $request = new Request('POST', $this->tenant->getTokenUrl());
        $this->assertFalse($request->hasHeader('X-Auth-Token'));

        $subscriber = new KeystoneTokenSubscriber($this->getCacheMock(), $this->tenant);
        $subscriber->setLogger(new NullLogger());
        $subscriber->onRequestError(new ErrorEvent(new Transaction(new Client(), $request)));

        $this->assertFalse($request->hasHeader('X-Auth-Token'));
    }

    /**
     * Tests that a failed request is retried with a new token.
     *
     * @dataProvider getRetryCodes
     */
    public function testErrorRequestRetryResponseEvent($code)
    {
        $request = new Request('GET', 'http://api.example.org');
        $this->assertFalse($request->hasHeader('X-Auth-Token'));

        /** @var \PHPUnit_Framework_MockObject_MockObject|KeystoneTokenSubscriber $subscriber */
        $subscriber = $this
            ->getMockBuilder(KeystoneTokenSubscriber::class)
            ->setConstructorArgs([$this->getCacheMock(), $this->tenant])
            ->setMethods(['getToken'])
            ->getMock()
        ;
        $subscriber
            ->expects($this->once())
            ->method('getToken')
            ->with($this->isInstanceOf(ClientInterface::class), true)
            ->will($this->returnValue($this->token))
        ;
        $subscriber->setLogger(new NullLogger());

        $transaction = new Transaction(new Client(), $request);
        $transaction->response = new Response($code);

        /** @var \PHPUnit_Framework_MockObject_MockObject|ErrorEvent $event */
        $event = $this
            ->getMockBuilder(ErrorEvent::class)
            ->setConstructorArgs([$transaction])
            ->setMethods(['retry'])
            ->getMock()
        ;
        $event
            ->expects($this->once())
            ->method('retry')
        ;
        $subscriber->onRequestError($event);

        $this->assertTrue($request->hasHeader('X-Auth-Token'));
    }

    public function getRetryCodes()
    {
        return [
            [401],
            [403],
        ];
    }

    /**
     * Tests that a failed request is not retried for certain response codes.
     *
     * @dataProvider getNoRetryCodes
     */
    public function testErrorRequestNoRetryResponseEvent($code)
    {
        $request = new Request('GET', 'http://api.example.org');
        $this->assertFalse($request->hasHeader('X-Auth-Token'));

        /** @var \PHPUnit_Framework_MockObject_MockObject|KeystoneTokenSubscriber $subscriber */
        $subscriber = $this
            ->getMockBuilder(KeystoneTokenSubscriber::class)
            ->setConstructorArgs([$this->getCacheMock(), $this->tenant])
            ->setMethods(['getToken'])
            ->getMock()
        ;
        $subscriber
            ->expects($this->never())
            ->method('getToken')
            ->will($this->returnValue($this->token))
        ;

        $transaction = new Transaction(new Client(), $request);
        $transaction->response = new Response($code);

        /** @var \PHPUnit_Framework_MockObject_MockObject|ErrorEvent $event */
        $event = $this
            ->getMockBuilder(ErrorEvent::class)
            ->setConstructorArgs([$transaction])
            ->setMethods(['retry'])
            ->getMock()
        ;
        $event
            ->expects($this->never())
            ->method('retry')
        ;
        $subscriber->onRequestError($event);

        $this->assertFalse($request->hasHeader('X-Auth-Token'));
    }

    public function getNoRetryCodes()
    {
        return [
            [200],
            [301],
            [402],
            [404],
            [500],
            [501],
            [503],
        ];
    }

    /**
     * Tests that no new token is requested when no more retries are left.
     */
    public function testErrorRequestNoMoreRetriesResponseEvent()
    {
        $request = new Request('GET', 'http://api.example.org');
        $this->assertFalse($request->hasHeader('X-Auth-Token'));

        /** @var \PHPUnit_Framework_MockObject_MockObject|KeystoneTokenSubscriber $subscriber */
        $subscriber = $this
            ->getMockBuilder(KeystoneTokenSubscriber::class)
            ->setConstructorArgs([$this->getCacheMock(), $this->tenant])
            ->setMethods(['getToken'])
            ->getMock()
        ;
        $subscriber
            ->expects($this->never())
            ->method('getToken')
            ->will($this->returnValue($this->token))
        ;
        $subscriber->setLogger(new NullLogger());

        $transaction = new Transaction(new Client(), $request);
        $transaction->response = new Response(401);

        /** @var \PHPUnit_Framework_MockObject_MockObject|ErrorEvent $event */
        $event = $this
            ->getMockBuilder(ErrorEvent::class)
            ->setConstructorArgs([$transaction])
            ->setMethods(['retry', 'getRetryCount'])
            ->getMock()
        ;
        $event
            ->expects($this->once())
            ->method('getRetryCount')
            ->will($this->returnValue(1))
        ;
        $event
            ->expects($this->never())
            ->method('retry')
        ;
        $subscriber->onRequestError($event);

        $this->assertFalse($request->hasHeader('X-Auth-Token'));
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|CacheInterface
     */
    private function getCacheMock()
    {
        return $this
            ->getMockBuilder(CacheInterface::class)
            ->getMockForAbstractClass()
            ;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function createClientMock()
    {
        $client   = $this->getMockBuilder(ClientInterface::class)->setMethods(['post'])->getMockForAbstractClass();

        return $client;
    }
}
