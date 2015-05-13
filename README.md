Keystone Client
===============

A client to use when communicating with Keystone services. Uses Guzzle as the
actual HTTP client library.

[![Build Status](https://travis-ci.org/treehouselabs/keystone-client.svg)](https://travis-ci.org/treehouselabs/keystone-client)
[![Code Coverage](https://scrutinizer-ci.com/g/treehouselabs/keystone-client/badges/coverage.png)](https://scrutinizer-ci.com/g/treehouselabs/keystone-client/)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/treehouselabs/keystone-client/badges/quality-score.png)](https://scrutinizer-ci.com/g/treehouselabs/keystone-client/)

## Installation

```sh
composer require treehouselabs/keystone-client:~2.0
```

## Usage

Use the `ClientFactory` to create a Guzzle Client with. The factory attaches an
event subscriber that requests a Keystone token an adds it to the request
headers.

In the case of an expired token, and the request fails, a new token is fetched
automatically and the request is retried with the new token.


```php
use TreeHouse\Cache\CacheInterface;
use TreeHouse\Keystone\Client\ClientFactory;
use TreeHouse\Keystone\Client\Model\Tenant;

$tokenUrl     = 'http://example.org/tokens';
$username     = 'acme';
$password     = 's3cr4t';
$serviceType  = 'compute';
$serviceName  = 'api';

// $cache is a TreeHouse\Cache\CacheInterface instance
$tenant  = new Tenant($tokenUrl, $username, $password, $serviceType, $serviceName);
$client  = (new ClientFactory($cache))->createClient($tenant);

// now just use $client as you would a regular Guzzle client
$response = $client->get('/posts/');
```

## Lazy client

If you're using a dependency injection container, you'll probably want to use
the lazy [`KeystoneClient`][KeystoneClient]. The reason for this is because the
factory method requests a token when constructing a new client. See the
following example, from a compiled Symfony DIC:

```php
protected function getKeystoneClientService()
{
    $factory = $this->getKeystoneClientFactory();

    return $this->services['keystone_client'] = $factory->createClient($tenant);
}
```

Now in itself this is not bad, but when you're requesting the client from the
container, a token is always requested, before you've even made a request,
which is inefficient. Even worse: if the container references a Keystone
service within the same project, you'll end up in an infinite recursion loop:

```php
protected function getKeystoneClientService()
{
    $factory = $this->getKeystoneClientFactory();

    return $this->services['keystone_client'] = $factory->createClient($tenant);
}

protected function getApiCallCommandService()
{
    return $this->services['command.api_call'] = new ApiCallCommand($this->get('keystone_client');
}
```

When warming up the cache for this application, commands are registered for it
and the `keystone_client` service is requested. That service immediately
requests a token, which hits the same application. But because the cache is not
yet warmed up, it will try to do so, causing an infinite loop.

To prevent this, you can use the [`KeystoneClient`][KeystoneClient], which
wraps the public API of Guzzle's [`ClientInterface`][ClientInterface] and only
creates a client (and thereby requesting a token) when a call is actually made.

[KeystoneClient]:  /src/TreeHouse/Client/KeystoneClient.php
[ClientInterface]: https://github.com/guzzle/guzzle/blob/master/src/ClientInterface.php
