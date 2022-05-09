<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\TestConfiguration;
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
        $this->removeForEntity(CallbackEntity::class);
        $this->removeForEntity(Job::class);
        $this->removeForEntity(Source::class);
        $this->removeForEntity(Test::class);
        $this->removeForEntity(TestConfiguration::class);
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
