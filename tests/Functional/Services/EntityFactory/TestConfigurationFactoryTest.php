<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EntityFactory;

use App\Services\EntityFactory\TestConfigurationFactory;
use App\Tests\AbstractBaseFunctionalTest;

class TestConfigurationFactoryTest extends AbstractBaseFunctionalTest
{
    private TestConfigurationFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(TestConfigurationFactory::class);
        \assert($factory instanceof TestConfigurationFactory);
        $this->factory = $factory;
    }

    public function testCreate(): void
    {
        $browser = 'chrome';
        $url = 'http://example.com';

        $testConfiguration = $this->factory->create($browser, $url);

        self::assertNotNull($testConfiguration->getId());
        self::assertSame($browser, $testConfiguration->getBrowser());
        self::assertSame($url, $testConfiguration->getUrl());
    }
}
