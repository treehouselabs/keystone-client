<?php

namespace TreeHouse\Keystone\Client;

use Guzzle\Http\Client as GuzzleClient;

/**
 * Extended Guzzle HTTP client for transparent communication with a keystone
 * service.
 *
 * Basic usage:
 * <code>
 *     $token = obtainToken();
 *
 *     $client = new Client();
 *     $client->setTokenUrl('http://keystone-service.com/tokens');
 *     $client->setKeystoneCredentials('username', 'password');
 *     $client->setToken($token);
 * </code>
 *
 * Now the client automatically uses the keystone public-url, and adds the
 * appropriate token header.
 *
 * Note that you still have to deal with expired tokens, by obtaining a new
 * token and giving it to the client.
 */
class Client extends GuzzleClient
{
    /**
     * @var Token
     */
    protected $token;

    /**
     * @var string
     */
    protected $tokenUrl;

    /**
     * @var string
     */
    protected $publicUrl;

    /**
     * @var string
     */
    protected $tenantName;

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
    protected $keystoneUsername;

    /**
     * @var string
     */
    protected $keystonePassword;

    /**
     * @param string $username
     * @param string $password
     */
    public function setKeystoneCredentials($username, $password)
    {
        $this->keystoneUsername = $username;
        $this->keystonePassword = $password;
    }

    /**
     * @return string
     */
    public function getKeystoneUsername()
    {
        return $this->keystoneUsername;
    }

    /**
     * @return string
     */
    public function getKeystonePassword()
    {
        return $this->keystonePassword;
    }

    /**
     * @param string $name
     */
    public function setTenantName($name)
    {
        $this->tenantName = $name;
    }

    /**
     * @return string
     */
    public function getTenantName()
    {
        return $this->tenantName;
    }

    /**
     * @param string $type
     */
    public function setServiceType($type)
    {
        $this->serviceType = $type;
    }

    /**
     * @return string
     */
    public function getServiceType()
    {
        return $this->serviceType;
    }

    /**
     * @param string $name
     */
    public function setServiceName($name)
    {
        $this->serviceName = $name;
    }

    /**
     * @return string
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * @param string $tokenUrl
     */
    public function setTokenUrl($tokenUrl)
    {
        $this->tokenUrl = $tokenUrl;
    }

    /**
     * @return string
     */
    public function getTokenUrl()
    {
        return $this->tokenUrl;
    }

    /**
     * @param Token $token
     */
    public function setToken(Token $token)
    {
        $this->token = $token;

        // set new default header
        $this->setDefaultOption('headers/X-Auth-Token', $token->getId());

        // set public url
        $catalog = array_change_key_case($token->getServiceCatalog($this->serviceType, $this->serviceName), CASE_LOWER);
        $this->setPublicUrl(rtrim($catalog['publicurl'], '/'));
    }

    /**
     * @return Token
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return string
     */
    public function getTokenId()
    {
        return $this->token->getId();
    }

    /**
     * @param string $url
     */
    public function setPublicUrl($url)
    {
        $this->publicUrl = $url;
        $this->setBaseUrl($url);
    }

    /**
     * @return string
     */
    public function getPublicUrl()
    {
        if (!$this->publicUrl) {
            $this->dispatch('client.initialize', array('client' => $this));
        }

        return $this->publicUrl;
    }

    /**
     * @param bool $expand
     *
     * @return \Guzzle\Http\Url|string
     */
    public function getBaseUrl($expand = true)
    {
        // make sure we have a public url (which is the same as the base url),
        // this triggers the token to be fetched
        $this->getPublicUrl();

        return parent::getBaseUrl($expand);
    }
}
