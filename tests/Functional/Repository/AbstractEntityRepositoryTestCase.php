<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Tests\AbstractBaseFunctionalTestCase;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractEntityRepositoryTestCase extends AbstractBaseFunctionalTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
    }

    protected function persistEntity(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
