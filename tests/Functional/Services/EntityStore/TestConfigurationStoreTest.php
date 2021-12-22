<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EntityStore;

use App\Entity\TestConfiguration;
use App\Services\EntityStore\TestConfigurationStore;
use App\Tests\AbstractBaseFunctionalTest;

class TestConfigurationStoreTest extends AbstractBaseFunctionalTest
{
    private TestConfigurationStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $store = self::getContainer()->get(TestConfigurationStore::class);
        \assert($store instanceof TestConfigurationStore);
        $this->store = $store;
    }

    public function testGet(): void
    {
        $configuration = TestConfiguration::create('chrome', 'http://example.com');
        self::assertNull($configuration->getId());

        $retrievedConfiguration = $this->store->get($configuration);

        self::assertIsInt($retrievedConfiguration->getId());
        self::assertSame($configuration->getBrowser(), $retrievedConfiguration->getBrowser());
        self::assertSame($configuration->getUrl(), $retrievedConfiguration->getUrl());

        self::assertSame($retrievedConfiguration, $this->store->get($retrievedConfiguration));
    }
}
