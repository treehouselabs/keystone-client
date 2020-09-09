<?php

namespace TreeHouse\Keystone\Tests\Model;

use TreeHouse\Keystone\Client\Model\Token;

class TokenTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_can_be_constructed()
    {
        $id = uniqid();
        $expires = new \DateTimeImmutable('+ 1 minute');
        $token = new Token($id, $expires);

        $this->assertInstanceOf(Token::class, $token);
        $this->assertEquals($id, $token->getId());
        $this->assertEquals($expires->getTimestamp(), $token->getExpirationDate()->getTimestamp());
    }

    /**
     * @test
     */
    public function it_has_a_service_catalog()
    {
        $type = 'compute';
        $name = 'api';
        $endpoint1 = [
            'adminurl' => 'https://admin.example.org',
            'publicurl' => 'https://example.org',
        ];
        $endpoint2 = [
            'adminurl' => 'https://admin.example.org',
            'publicurl' => 'https://example.org',
        ];

        $token = new Token(uniqid(), new \DateTime('+ 1 minute'));

        $token->addServiceCatalog($type, $name, [$endpoint1]);
        $token->addServiceCatalog($type, 'test', [$endpoint2]);

        $this->assertEquals([$endpoint1], $token->getServiceCatalog($type));
        $this->assertEquals([$endpoint2], $token->getServiceCatalog($type, 'test'));
    }

    /**
     * @test
     */
    public function it_can_expire()
    {
        $expires = new \DateTime('- 1 minute');
        $token = new Token(uniqid(), $expires);

        $this->assertTrue($token->isExpired());
    }

    /**
     * @test
     */
    public function it_can_be_serialized()
    {
        $token = new Token(uniqid(), new \DateTime('+ 1 minute'));
        $token->addServiceCatalog('compute', 'test', [['adminurl' => 'https://admin.example.org', 'publicurl' => 'https://example.org']]);

        $serialized = json_encode($token);
        $this->assertInternalType('string', $serialized);

        $unserialized = Token::create(json_decode($serialized, true));
        $this->assertInstanceOf(Token::class, $unserialized);
        $this->assertEquals($token->getId(), $unserialized->getId());
        $this->assertEquals($token->getExpirationDate()->getTimestamp(), $unserialized->getExpirationDate()->getTimestamp());
        $this->assertEquals($token->getServiceCatalog('compute'), $unserialized->getServiceCatalog('compute'));
    }

    /**
     * @test
     */
    public function it_can_be_constructed_using_a_factory()
    {
        $id = uniqid();
        $expires = new \DateTime('+ 1 minute');
        $type = 'compute';
        $name = 'api';
        $endpoint = [
            'adminurl' => 'https://admin.example.org',
            'publicurl' => 'https://example.org',
        ];

        $content = [
            'access' => [
                'token' => [
                    'id' => $id,
                    'expires' => $expires->format(DATE_ISO8601),
                ],
                'servicecatalog' => [
                    [
                        'name' => $name,
                        'type' => $type,
                        'endpoints' => [$endpoint],
                    ],
                ],
            ],
        ];

        $token = Token::create($content);

        $this->assertInstanceOf(Token::class, $token);
        $this->assertEquals($id, $token->getId());
        $this->assertEquals($expires->getTimestamp(), $token->getExpirationDate()->getTimestamp());
        $this->assertEquals([$endpoint], $token->getServiceCatalog($type, $name));
    }

    /**
     * @test
     * @@dataProvider     invalidFactoryMethodDataProvider
     * @expectedException \TreeHouse\Keystone\Client\Exception\TokenException
     *
     * @param array $content
     */
    public function it_cannot_be_constructed_with_invalid_arguments(array $content)
    {
        Token::create($content);
    }

    /**
     * @return array
     */
    public function invalidFactoryMethodDataProvider()
    {
        return [
            [
                // no 'access' component
                [],
            ],
            [
                ['access' => []],
            ],
            [
                // no 'expires' key
                [
                    'access' => [
                        'token' => [
                            'id' => 1234,
                        ],
                    ],
                ],
            ],
            [
                // no service catalog
                [
                    'access' => [
                        'token' => [
                            'id' => 1234,
                            'expires' => '2014-07-25T10:32:05+0000',
                        ],
                    ],
                ],
            ],
            [
                // empty service catalog
                [
                    'access' => [
                        'token' => [
                            'id' => 1234,
                            'expires' => '2014-07-25T10:32:05+0000',
                        ],
                        'servicecatalog' => [
                            [],
                        ],
                    ],
                ],
            ],
            [
                // invalid 'expires' value
                [
                    'access' => [
                        'token' => [
                            'id' => 1234,
                            'expires' => 'asdfasdf',
                        ],
                    ],
                ],
            ],
            [
                // no endpoints in catalog
                [
                    'access' => [
                        'token' => [
                            'id' => 1234,
                            'expires' => '2014-07-25T10:32:05+0000',
                        ],
                        'servicecatalog' => [
                            [
                                'name' => 'test',
                                'type' => 'compute',
                                'endpoints' => null,
                            ],
                        ],
                    ],
                ],
            ],
            [
                // invalid endpoint structure
                [
                    'access' => [
                        'token' => [
                            'id' => 1234,
                            'expires' => '2014-07-25T10:32:05+0000',
                        ],
                        'servicecatalog' => [
                            [
                                'name' => 'test',
                                'type' => 'compute',
                                'endpoints' => 'http://example.org',
                            ],
                        ],
                    ],
                ],
            ],
            [
                // invalid endpoint structure (2)
                [
                    'access' => [
                        'token' => [
                            'id' => 1234,
                            'expires' => '2014-07-25T10:32:05+0000',
                        ],
                        'servicecatalog' => [
                            [
                                'name' => 'test',
                                'type' => 'compute',
                                'endpoints' => [
                                    'http://example.org',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                // missing endpoint url
                [
                    'access' => [
                        'token' => [
                            'id' => 1234,
                            'expires' => '2014-07-25T10:32:05+0000',
                        ],
                        'servicecatalog' => [
                            [
                                'name' => 'test',
                                'type' => 'compute',
                                'endpoints' => [
                                    ['foo' => 'bar'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                // missing endpoint type
                [
                    'access' => [
                        'token' => [
                            'id' => 1234,
                            'expires' => '2014-07-25T10:32:05+0000',
                        ],
                        'servicecatalog' => [
                            [
                                'name' => 'test',
                                'endpoints' => [
                                    ['adminurl' => 'http://example.org'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                // missing endpoint name
                [
                    'access' => [
                        'token' => [
                            'id' => 1234,
                            'expires' => '2014-07-25T10:32:05+0000',
                        ],
                        'servicecatalog' => [
                            [
                                'type' => 'compute',
                                'endpoints' => [
                                    ['adminurl' => 'http://example.org'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @expectedException \TreeHouse\Keystone\Client\Exception\TokenException
     */
    public function it_throws_exception_on_undefined_service_catalog_type()
    {
        $token = new Token(uniqid(), new \DateTime('+ 1 minute'));
        $token->addServiceCatalog('compute', 'test', [['adminurl' => 'https://admin.example.org', 'publicurl' => 'https://example.org']]);

        $token->getServiceCatalog('object-store');
    }

    /**
     * @test
     * @expectedException \TreeHouse\Keystone\Client\Exception\TokenException
     */
    public function it_throws_exception_on_undefined_service_catalog_name()
    {
        $token = new Token(uniqid(), new \DateTime('+ 1 minute'));
        $token->addServiceCatalog('compute', 'test', [['adminurl' => 'https://admin.example.org', 'publicurl' => 'https://example.org']]);

        $token->getServiceCatalog('compute', 'api');
    }
}
