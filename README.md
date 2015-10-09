Keystone Client
===============

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]

A client to use when communicating with Keystone services. Uses Guzzle as the
actual HTTP client library.

## Installation

```sh
composer require treehouselabs/keystone-client:~3.0
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


## Testing

``` bash
composer test
```


## Security

If you discover any security related issues, please email peter@treehouse.nl instead of using the issue tracker.


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


## Credits

- [Peter Kruithof][link-author]
- [All Contributors][link-contributors]


[ico-version]: https://img.shields.io/packagist/v/treehouselabs/keystone-client.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/treehouselabs/keystone-client/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/treehouselabs/keystone-client.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/treehouselabs/keystone-client.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/treehouselabs/keystone-client.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/treehouselabs/keystone-client
[link-travis]: https://travis-ci.org/treehouselabs/keystone-client
[link-scrutinizer]: https://scrutinizer-ci.com/g/treehouselabs/keystone-client/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/treehouselabs/keystone-client
[link-downloads]: https://packagist.org/packages/treehouselabs/keystone-client
[link-author]: https://github.com/treehouselabs
[link-contributors]: ../../contributors
