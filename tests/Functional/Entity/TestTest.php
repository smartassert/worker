<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\Test;
use App\Entity\TestConfiguration;
use App\Tests\Services\EntityRemover;

class TestTest extends AbstractEntityTest
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
        $repository = $this->entityManager->getRepository(Test::class);
        self::assertCount(0, $repository->findAll());

        $configuration = TestConfiguration::create('chrome', 'http://example.com');
        $this->entityManager->persist($configuration);
        $this->entityManager->flush();

        $test = new Test(
            $configuration,
            '/app/source/Test/test.yml',
            '/app/tests/GeneratedTest.php',
            ['step 1'],
            1
        );

        $this->entityManager->persist($test);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }
}
