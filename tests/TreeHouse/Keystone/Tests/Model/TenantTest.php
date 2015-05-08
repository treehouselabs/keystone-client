<?php

namespace TreeHouse\Keystone\Tests\Model;

use TreeHouse\Keystone\Client\Model\Tenant;

class TenantTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $tokenUrl = 'http://example.org/tokens';

    /**
     * @var string
     */
    protected $username = 'admin';

    /**
     * @var string
     */
    protected $password = '1234';

    /**
     * @var string
     */
    protected $serviceType = 'object-store';

    /**
     * @var string
     */
    protected $serviceName = 'cdn';

    /**
     * @var string
     */
    protected $tenantName = 'treehouse';

    public function testConstruction()
    {
        $tenant = new Tenant($this->tokenUrl, $this->username, $this->password, $this->serviceType, $this->serviceName, $this->tenantName);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals($this->tokenUrl, $tenant->getTokenUrl());
        $this->assertEquals($this->username, $tenant->getUsername());
        $this->assertEquals($this->password, $tenant->getPassword());
        $this->assertEquals($this->serviceType, $tenant->getServiceType());
        $this->assertEquals($this->serviceName, $tenant->getServiceName());
        $this->assertEquals($this->tenantName, $tenant->getTenantName());
    }
}
