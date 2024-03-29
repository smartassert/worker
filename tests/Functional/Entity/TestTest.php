<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\Test;
use App\Tests\Services\EntityRemover;

class TestTest extends AbstractEntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(Test::class);
        self::assertCount(0, $repository->findAll());

        $test = new Test(
            'chrome',
            'http://example.com',
            'Test/test.yml',
            'GeneratedTest.php',
            ['step 1'],
            1
        );

        $this->entityManager->persist($test);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }
}
