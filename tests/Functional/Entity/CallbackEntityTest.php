<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\Callback\CallbackEntity;
use App\Tests\Services\EntityRemover;

class CallbackEntityTest extends AbstractEntityTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(CallbackEntity::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(CallbackEntity::class);
        self::assertCount(0, $repository->findAll());

        $callback = CallbackEntity::create(CallbackEntity::TYPE_COMPILATION_FAILED, '', []);

        $this->entityManager->persist($callback);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }
}
