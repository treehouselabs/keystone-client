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

Use the `ClientFactory` to create a Guzzle Client with. The factory attaches
middleware that automatically requests a Keystone token and signs outgoing
requests with it.

In the case of an expired token, and the request fails, a new token is fetched
automatically and the request is retried with the new token.


```php
use Psr\Cache\CacheItemPoolInterface;
use TreeHouse\Keystone\Client\ClientFactory;
use TreeHouse\Keystone\Client\Model\Tenant;

$tokenUrl     = 'http://example.org/tokens';
$username     = 'acme';
$password     = 's3cr4t';
$serviceType  = 'compute';
$serviceName  = 'api';

// $cache is a Psr\Cache\CacheItemPoolInterface instance
$tenant  = new Tenant($tokenUrl, $username, $password, $serviceType, $serviceName);
$client  = (new ClientFactory($cache))->createClient($tenant);

// now just use $client as you would a regular Guzzle client
$response = $client->get('posts/');
```


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
