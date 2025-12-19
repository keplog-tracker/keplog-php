<?php

namespace Keplog\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Keplog\Utils\Environment;

class EnvironmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear environment variables
        putenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        // Clean up
        putenv('APP_ENV');
        parent::tearDown();
    }

    public function testDetectReturnsAppEnvWhenSet(): void
    {
        putenv('APP_ENV=staging');
        $this->assertEquals('staging', Environment::detect());
    }

    public function testDetectReturnsProductionWhenAppEnvNotSet(): void
    {
        putenv('APP_ENV');
        $this->assertEquals('production', Environment::detect());
    }

    public function testDetectServerNameReturnsHostname(): void
    {
        $serverName = Environment::detectServerName();
        $this->assertIsString($serverName);
        $this->assertNotEmpty($serverName);
        $this->assertNotEquals('unknown', $serverName);
    }
}
