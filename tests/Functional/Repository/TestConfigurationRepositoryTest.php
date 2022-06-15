<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Test;
use App\Entity\TestConfiguration;
use App\Repository\TestConfigurationRepository;
use App\Tests\Services\EntityRemover;

class TestConfigurationRepositoryTest extends AbstractEntityRepositoryTest
{
    private TestConfigurationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(TestConfigurationRepository::class);
        \assert($repository instanceof TestConfigurationRepository);
        $this->repository = $repository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
            $entityRemover->removeForEntity(TestConfiguration::class);
        }
    }

    public function testGet(): void
    {
        $configuration = new TestConfiguration('chrome', 'http://example.com');
        self::assertNull($configuration->getId());

        $retrievedConfiguration = $this->repository->get($configuration);

        self::assertIsInt($retrievedConfiguration->getId());
        self::assertSame($configuration->getBrowser(), $retrievedConfiguration->getBrowser());
        self::assertSame($configuration->getUrl(), $retrievedConfiguration->getUrl());

        self::assertSame($retrievedConfiguration, $this->repository->get($retrievedConfiguration));
    }
}
