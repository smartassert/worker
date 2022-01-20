<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Entity\EntityInterface;
use App\Entity\Job;
use App\Entity\Source;
use App\Services\EntityPersister;
use App\Tests\AbstractBaseFunctionalTest;
use Doctrine\ORM\EntityManagerInterface;

class EntityPersisterTest extends AbstractBaseFunctionalTest
{
    private EntityPersister $entityPersister;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $entityPersister = self::getContainer()->get(EntityPersister::class);
        \assert($entityPersister instanceof EntityPersister);
        $this->entityPersister = $entityPersister;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
    }

    /**
     * @dataProvider persistDataProvider
     */
    public function testPersist(EntityInterface $entity): void
    {
        $repository = $this->entityManager->getRepository($entity::class);
        self::assertCount(0, $repository->findAll());

        $this->entityPersister->persist($entity);
        self::assertCount(1, $repository->findAll());
    }

    /**
     * @return array<mixed>
     */
    public function persistDataProvider(): array
    {
        return [
            'callback' => [
                'entity' => CallbackEntity::create(CallbackInterface::TYPE_COMPILATION_FAILED, []),
            ],
            'job' => [
                'entity' => Job::create('label content', 'http://example.com/callback', 600),
            ],
            'source' => [
                'entity' => Source::create(Source::TYPE_TEST, 'Test/test.yml'),
            ],
        ];
    }
}
