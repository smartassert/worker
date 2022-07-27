<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\ResourceReference;
use App\Tests\Services\EntityRemover;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class ResourceReferenceTest extends AbstractEntityTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(ResourceReference::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(ResourceReference::class);
        self::assertCount(0, $repository->findAll());

        $resourceReference = new ResourceReference('non-empty label', md5('non-empty reference'));

        $this->entityManager->persist($resourceReference);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }

    public function testLabelAndReferenceAreUnique(): void
    {
        $this->entityManager->persist(new ResourceReference('non-empty label', md5('non-empty reference')));
        $this->entityManager->flush();

        self::expectException(UniqueConstraintViolationException::class);

        $this->entityManager->persist(new ResourceReference('non-empty label', md5('non-empty reference')));
        $this->entityManager->flush();
    }
}
