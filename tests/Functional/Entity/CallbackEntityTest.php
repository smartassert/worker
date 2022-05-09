<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\WorkerEvent;
use App\Tests\Services\EntityRemover;

class CallbackEntityTest extends AbstractEntityTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(WorkerEvent::class);
        self::assertCount(0, $repository->findAll());

        $callback = WorkerEvent::create(
            WorkerEvent::TYPE_COMPILATION_FAILED,
            'non-empty reference',
            []
        );

        $this->entityManager->persist($callback);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }
}
