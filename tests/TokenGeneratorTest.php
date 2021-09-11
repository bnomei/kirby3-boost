<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\TokenGenerator;
use PHPUnit\Framework\TestCase;

final class TokenGeneratorTest extends TestCase
{
    public function testToken()
    {
        $gen = new TokenGenerator();

        $this->assertMatchesRegularExpression(
            '/^[a-z0-9]{8}$/',
            $gen->generate()
        );

        $this->assertMatchesRegularExpression(
            '/^[a-zA-Z0-9]{16}$/',
            $gen->generate(16, true, true, true)
        );
    }
}
