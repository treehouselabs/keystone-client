CHANGELOG
=========

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
