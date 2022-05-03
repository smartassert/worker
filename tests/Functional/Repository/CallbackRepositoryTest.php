<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Repository\CallbackRepository;
use App\Tests\Services\EntityRemover;

class CallbackRepositoryTest extends AbstractEntityRepositoryTest
{
    private CallbackRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(CallbackRepository::class);
        \assert($repository instanceof CallbackRepository);
        $this->repository = $repository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(CallbackEntity::class);
        }
    }

    public function testHasForType(): void
    {
        $callback0 = CallbackEntity::create(CallbackInterface::TYPE_COMPILATION_FAILED, '', []);
        $callback0->setState(CallbackInterface::STATE_AWAITING);
        $this->persistEntity($callback0);

        $callback1 = CallbackEntity::create(CallbackInterface::TYPE_TEST_STARTED, '', []);
        $callback1->setState(CallbackInterface::STATE_AWAITING);
        $this->persistEntity($callback1);

        $callback2 = CallbackEntity::create(CallbackInterface::TYPE_JOB_TIME_OUT, '', []);
        $callback2->setState(CallbackInterface::STATE_COMPLETE);
        $this->persistEntity($callback2);

        self::assertTrue($this->repository->hasForType(CallbackInterface::TYPE_COMPILATION_FAILED));
        self::assertFalse($this->repository->hasForType(CallbackInterface::TYPE_STEP_PASSED));
    }

    public function testGetTypeCount(): void
    {
        $this->createCallbacksWithTypes([
            CallbackInterface::TYPE_JOB_STARTED,
            CallbackInterface::TYPE_STEP_PASSED,
            CallbackInterface::TYPE_STEP_PASSED,
            CallbackInterface::TYPE_COMPILATION_PASSED,
            CallbackInterface::TYPE_COMPILATION_PASSED,
            CallbackInterface::TYPE_COMPILATION_PASSED,
        ]);

        self::assertSame(0, $this->repository->getTypeCount(CallbackInterface::TYPE_EXECUTION_COMPLETED));
        self::assertSame(1, $this->repository->getTypeCount(CallbackInterface::TYPE_JOB_STARTED));
        self::assertSame(2, $this->repository->getTypeCount(CallbackInterface::TYPE_STEP_PASSED));
        self::assertSame(3, $this->repository->getTypeCount(CallbackInterface::TYPE_COMPILATION_PASSED));
    }

    /**
     * @param array<CallbackInterface::TYPE_*> $types
     */
    private function createCallbacksWithTypes(array $types): void
    {
        foreach ($types as $type) {
            $this->repository->create($type, '', []);
        }
    }
}
