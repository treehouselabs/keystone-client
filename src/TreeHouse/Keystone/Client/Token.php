<?php

namespace TreeHouse\Keystone\Client;

class Token implements \Serializable
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
        $this->id = $id;
        $this->expires = $expires;
        $this->catalogs = array();
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $type
     * @param string $name
     * @param array  $endpoints
     */
    public function addServiceCatalog($type, $name, array $endpoints)
    {
        if (!array_key_exists($type, $this->catalogs)) {
            $this->catalogs[$type] = array();
        }

        $this->catalogs[$type][$name] = $endpoints;
    }

    /**
     * @param string $type
     * @param string $name
     *
     * @return array
     */
    public function getServiceCatalog($type, $name = null)
    {
        return is_null($name) ? current($this->catalogs[$type]) : $this->catalogs[$type][$name];
    }

    /**
     * @return \DateTime
     */
    public function getExpirationDate()
    {
        return $this->expires;
    }

    /**
     * @return string
     */
    public function serialize()
    {
        return serialize(array(
            'id' => $this->id,
            'expires' => $this->expires,
            'catalogs' => $this->catalogs
        ));
    }

    /**
     * @param string $data
     */
    public function unserialize($data)
    {
        $data = unserialize($data);

        $this->id = $data['id'];
        $this->expires = $data['expires'];
        $this->catalogs = $data['catalogs'];
    }
}
