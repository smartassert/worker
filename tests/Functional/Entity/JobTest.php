<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\Job;
use App\Tests\Services\EntityRemover;

class JobTest extends AbstractEntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(Job::class);
        self::assertCount(0, $repository->findAll());

        $job = new Job(
            'label content',
            'results-token',
            600,
            [
                'test1.yml',
                'test2.yml',
                'test3.yml',
            ]
        );

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }
}
