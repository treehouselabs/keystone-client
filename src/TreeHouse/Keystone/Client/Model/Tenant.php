<?php

namespace TreeHouse\Keystone\Client\Model;

class Tenant
{
    /**
     * @var string
     */
    protected $tokenUrl;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $serviceType;

    /**
     * @var string
     */
    protected $serviceName;

    /**
     * @var string
     */
    protected $tenantName;

    /**
     * @var string
     */
    protected $serviceEndpoint;

    /**
     * @param string $tokenUrl        The url where to obtain a token
     * @param string $username        Username
     * @param string $password        Password
     * @param string $serviceType     The type of service
     * @param string $serviceName     Service name. If left empty, the first matching service type will be used.
     * @param string $tenantName      Tenant name (optional)
     * @param string $serviceEndpoint Service catalog endpoint (defaults to public)
     */
    public function __construct($tokenUrl, $username, $password, $serviceType, $serviceName = null, $tenantName = null, $serviceEndpoint = 'public')
    {
        $this->tokenUrl = $tokenUrl;
        $this->username = $username;
        $this->password = $password;
        $this->serviceType = $serviceType;
        $this->serviceName = $serviceName;
        $this->tenantName = $tenantName;
        $this->serviceEndpoint = ($serviceEndpoint !== null) ? $serviceEndpoint : 'public';
    }

    /**
     * @return string
     */
    public function getTokenUrl()
    {
        return $this->tokenUrl;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getServiceType()
    {
        return $this->serviceType;
    }

    /**
     * @return string
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * @return string
     */
    public function getTenantName()
    {
        return $this->tenantName;
    }

    /**
     * @return string
     */
    public function getServiceEndpoint()
    {
        return $this->serviceEndpoint;
    }
}
