<?php

namespace TreeHouse\Keystone\Tests\Exception;

use TreeHouse\Keystone\Client\Exception\TokenException;

class TokenExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_can_be_constructed()
    {
        $message = 'Foobar';

        $exception = new TokenException($message);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }
}
