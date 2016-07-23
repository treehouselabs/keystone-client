<?php

namespace TreeHouse\Keystone\Tests\Model;

use TreeHouse\Keystone\Client\Model\Tenant;

class TenantTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $tokenUrl = 'http://example.org/tokens';

    /**
     * @var string
     */
    private $username = 'admin';

    /**
     * @var string
     */
    private $password = '1234';

    /**
     * @var string
     */
    private $serviceType = 'object-store';

    /**
     * @var string
     */
    private $serviceName = 'cdn';

    /**
     * @var string
     */
    private $tenantName = 'treehouse';

    /**
     * @var string
     */
    private $serviceEndpoint = 'admin';

    /**
     * @test
     */
    public function it_can_be_constructed()
    {
        $tenant = new Tenant($this->tokenUrl, $this->username, $this->password, $this->serviceType, $this->serviceName, $this->tenantName,
                             $this->serviceEndpoint);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals($this->tokenUrl, $tenant->getTokenUrl());
        $this->assertEquals($this->username, $tenant->getUsername());
        $this->assertEquals($this->password, $tenant->getPassword());
        $this->assertEquals($this->serviceType, $tenant->getServiceType());
        $this->assertEquals($this->serviceName, $tenant->getServiceName());
        $this->assertEquals($this->tenantName, $tenant->getTenantName());
        $this->assertEquals($this->serviceEndpoint, $tenant->getServiceEndpoint());
    }
}
