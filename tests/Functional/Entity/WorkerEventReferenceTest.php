<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\WorkerEventReference;
use App\Tests\Services\EntityRemover;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class WorkerEventReferenceTest extends AbstractEntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEventReference::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(WorkerEventReference::class);
        self::assertCount(0, $repository->findAll());

        $resourceReference = new WorkerEventReference('non-empty label', md5('non-empty reference'));

        $this->entityManager->persist($resourceReference);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }

    public function testLabelAndReferenceAreUnique(): void
    {
        $this->entityManager->persist(new WorkerEventReference('non-empty label', md5('non-empty reference')));
        $this->entityManager->flush();

        self::expectException(UniqueConstraintViolationException::class);

        $this->entityManager->persist(new WorkerEventReference('non-empty label', md5('non-empty reference')));
        $this->entityManager->flush();
    }
}
