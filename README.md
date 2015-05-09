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
