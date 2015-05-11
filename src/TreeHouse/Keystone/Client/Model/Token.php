<?php

namespace TreeHouse\Keystone\Client\Model;

class Token implements \JsonSerializable
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var \DateTime
     */
    protected $expires;

    /**
     * @var array
     */
    protected $catalogs;

    /**
     * @param string    $id
     * @param \DateTime $expires
     */
    public function __construct($id, \DateTime $expires)
    {
        $this->id       = $id;
        $this->expires  = $expires;
        $this->catalogs = [];
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Adds a service catalog, using the array output of a token-call.
     *
     * @param string $type      The service type
     * @param string $name      The service name
     * @param array  $endpoints Array of endpoints
     */
    public function addServiceCatalog($type, $name, array $endpoints)
    {
        if (!array_key_exists($type, $this->catalogs)) {
            $this->catalogs[$type] = [];
        }

        // check if endpoints config is correct
        foreach ($endpoints as $index => $endpoint) {
            if (!is_array($endpoint)) {
                throw new \InvalidArgumentException('Expecting an array for an endpoint');
            }

            $endpoints[$index] = array_change_key_case($endpoint, CASE_LOWER);

            if (!isset($endpoints[$index]['publicurl']) && !isset($endpoints[$index]['adminurl'])) {
                throw new \InvalidArgumentException(
                    sprintf('An endpoint must have either a "publicurl" or "adminurl" key, got %s', json_encode($endpoint))
                );
            }
        }

        $this->catalogs[$type][$name] = $endpoints;
    }

    /**
     * @param string $type
     * @param string $name
     *
     * @throws \OutOfBoundsException
     *
     * @return array
     */
    public function getServiceCatalog($type, $name = null)
    {
        if (!array_key_exists($type, $this->catalogs) || empty($this->catalogs[$type])) {
            throw new \OutOfBoundsException(sprintf('There is no catalog for "%s"', $type));
        }

        $catalogs = $this->catalogs[$type];

        if (is_null($name)) {
            return reset($catalogs);
        }

        if (!array_key_exists($name, $catalogs)) {
            throw new \OutOfBoundsException(sprintf('There is no service named "%s" for catalog "%s"', $name, $type));
        }

        return $catalogs[$name];
    }

    /**
     * @return \DateTime
     */
    public function getExpirationDate()
    {
        return $this->expires;
    }

    /**
     * @return bool
     */
    public function isExpired()
    {
        return new \DateTime() >= $this->getExpirationDate();
    }

    /**
     * Factory method to use with Keystone token responses, or a json_encoded Token instance.
     *
     * @param array $content
     *
     * @throws \InvalidArgumentException
     *
     * @return static
     */
    public static function create(array $content)
    {
        $access  = static::arrayGet($content, 'access');
        $token   = static::arrayGet($access, 'token');
        $tokenid = static::arrayGet($token, 'id');
        $expires = static::arrayGet($token, 'expires');

        try {
            $expireDate = new \DateTime($expires);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Invalid expiration date: %s', $e->getMessage()), null, $e);
        }

        $token = new static($tokenid, $expireDate);

        $catalogs = static::arrayGet($access, 'serviceCatalog');
        if (is_array($catalogs)) {
            foreach ($catalogs as $catalog) {
                $type      = static::arrayGet($catalog, 'type');
                $name      = static::arrayGet($catalog, 'name');
                $endpoints = static::arrayGet($catalog, 'endpoints');

                if (!is_array($endpoints)) {
                    throw new \InvalidArgumentException(sprintf('Invalid endpoints: %s', json_encode($endpoints)));
                }

                $token->addServiceCatalog($type, $name, $endpoints);
            }
        }

        return $token;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        $response = [
            'access' => [
                'token' => [
                    'id' => $this->id,
                    'expires' => $this->expires->format(DATE_ISO8601)
                ],
                'serviceCatalog' => [],
            ],
        ];

        foreach ($this->catalogs as $type => $catalogs) {
            foreach ($catalogs as $name => $endpoints) {
                $response['access']['serviceCatalog'][] = [
                    'name'      => $name,
                    'type'      => $type,
                    'endpoints' => $endpoints,
                ];
            }
        }

        return $response;
    }

    /**
     * @param array  $array
     * @param string $key
     *
     * @return mixed
     */
    protected static function arrayGet(array $array, $key)
    {
        $key   = strtolower($key);
        $array = array_change_key_case($array, CASE_LOWER);

        if (!array_key_exists($key, $array)) {
            throw new \InvalidArgumentException(
                sprintf('Did not find key %s in array: %s', json_encode($key), json_encode($array))
            );
        }

        return $array[$key];
    }
}
