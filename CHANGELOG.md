CHANGELOG
=========

## 3.0.0

### Changes

* Upgraded Guzzle to v6 (now uses PSR7)
  * Since Guzzle replaced its events mechanism in favour of middleware, the
    keystone client factory has been refactored to accomodate this.
  * The client no longer has to pre-fetch the token on construction due to the
    changes in Guzzle. Which means the lazy [`KeystoneClient`](/src/TreeHouse/Keystone/Client/KeystoneClient.php)
    class has been removed. A regular Guzzle client is now used.

### BC breaks

* PHP version requirement bumped to 5.6
* Guzzle version requirement bumped to 6.0
* The JIT request signing implementation has changed from a event subscriber to
  middleware. If you extended the `KeystoneTokenSubscriber` you will need to
  refactor your code.
* The Token class now only throws `TokenException` instances, instead of
  `\InvalidArgumentException` and `\OutOfBoundsException`.

## 2.1.0

* Added lazy loading client

## 2.0.0

* Upgraded Guzzle to v5
* Added support to pass Guzzle config
* Library is now fully tested & documented

### BC breaks

* Renamed `TreeHouse\Keystone\Client\Factory => TreeHouse\Keystone\Client\ClientFactory`
* Added `$class` as second constructor argument in constructor

Before, the Keystone Client extended the Guzzle Client class and provided the
token injection. This has been refactored to better use Guzzle's features. The
Client class has been replaced with an event subscriber.

If you're just using the factory method, you need to update the referencing
class, and pass a Tenant object instead of separate `url`/`user`/`pass`
parameters:

Before:

```php
$factory = new Factory($cache, $logger);
$client = $factory->createClient($tokenUrl, $username, $password, $serviceType, $serviceName);
```

After:

```php
// use null for default Guzzle client class
$tenant  = new Tenant($tokenUrl, $username, $password, $serviceType, $serviceName);
$factory = new ClientFactory($cache, null, $logger);
$client  = $factory->createClient($tenant);
```
