<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\Test;
use App\Entity\TestConfiguration;
use App\Tests\Services\EntityRemover;

class TestConfigurationTest extends AbstractEntityTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
            $entityRemover->removeForEntity(TestConfiguration::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(TestConfiguration::class);
        self::assertCount(0, $repository->findAll());

        $configuration = new TestConfiguration('chrome', 'http://example.com');

        $this->entityManager->persist($configuration);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }
}
