<?php

declare(strict_types=1);

namespace App\Tests\Services;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

class EntityRemover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function removeAll(): void
    {
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $this->removeForEntity($metadata->getName());
        }
    }

    /**
     * @param class-string $className
     */
    public function removeForEntity(string $className): void
    {
        $repository = $this->entityManager->getRepository($className);
        if ($repository instanceof ObjectRepository) {
            $entities = $repository->findAll();

            foreach ($entities as $entity) {
                $this->entityManager->remove($entity);
                $this->entityManager->flush();
            }
        }
    }
}
