<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EntityStore;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Repository\CallbackRepository;
use App\Services\EntityPersister;
use App\Services\EntityStore\CallbackStore;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\EntityRemover;

class CallbackStoreTest extends AbstractBaseFunctionalTest
{
    private CallbackStore $store;
    private CallbackRepository $callbackRepository;
    private EntityPersister $entityPersister;

    protected function setUp(): void
    {
        parent::setUp();

        $store = self::getContainer()->get(CallbackStore::class);
        \assert($store instanceof CallbackStore);
        $this->store = $store;

        $callbackRepository = self::getContainer()->get(CallbackRepository::class);
        \assert($callbackRepository instanceof CallbackRepository);
        $this->callbackRepository = $callbackRepository;

        $entityPersister = self::getContainer()->get(EntityPersister::class);
        \assert($entityPersister instanceof EntityPersister);
        $this->entityPersister = $entityPersister;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(CallbackEntity::class);
        }
    }

    /**
     * @dataProvider getFinishedCountDataProvider
     *
     * @param array<CallbackInterface::STATE_*> $callbackStates
     */
    public function testGetFinishedCount(array $callbackStates, int $expectedFinishedCount): void
    {
        $this->createCallbacksWithStates($callbackStates);

        self::assertSame($expectedFinishedCount, $this->store->getFinishedCount());
    }

    /**
     * @return array<mixed>
     */
    public function getFinishedCountDataProvider(): array
    {
        return [
            'no callbacks' => [
                'callbackStates' => [],
                'expectedFinishedCount' => 0,
            ],
            'none finished' => [
                'callbackStates' => [
                    CallbackInterface::STATE_AWAITING,
                    CallbackInterface::STATE_QUEUED,
                    CallbackInterface::STATE_SENDING,
                ],
                'expectedFinishedCount' => 0,
            ],
            'one complete' => [
                'callbackStates' => [
                    CallbackInterface::STATE_AWAITING,
                    CallbackInterface::STATE_QUEUED,
                    CallbackInterface::STATE_SENDING,
                    CallbackInterface::STATE_COMPLETE,
                ],
                'expectedFinishedCount' => 1,
            ],
            'one failed' => [
                'callbackStates' => [
                    CallbackInterface::STATE_AWAITING,
                    CallbackInterface::STATE_QUEUED,
                    CallbackInterface::STATE_SENDING,
                    CallbackInterface::STATE_FAILED,
                ],
                'expectedFinishedCount' => 1,
            ],
            'two complete, three failed' => [
                'callbackStates' => [
                    CallbackInterface::STATE_AWAITING,
                    CallbackInterface::STATE_QUEUED,
                    CallbackInterface::STATE_SENDING,
                    CallbackInterface::STATE_COMPLETE,
                    CallbackInterface::STATE_COMPLETE,
                    CallbackInterface::STATE_FAILED,
                    CallbackInterface::STATE_FAILED,
                    CallbackInterface::STATE_FAILED,
                ],
                'expectedFinishedCount' => 5,
            ],
        ];
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

        self::assertSame(0, $this->store->getTypeCount(CallbackInterface::TYPE_EXECUTION_COMPLETED));
        self::assertSame(1, $this->store->getTypeCount(CallbackInterface::TYPE_JOB_STARTED));
        self::assertSame(2, $this->store->getTypeCount(CallbackInterface::TYPE_STEP_PASSED));
        self::assertSame(3, $this->store->getTypeCount(CallbackInterface::TYPE_COMPILATION_PASSED));
    }

    /**
     * @param array<CallbackInterface::STATE_*> $states
     */
    private function createCallbacksWithStates(array $states): void
    {
        foreach ($states as $state) {
            $callback = $this->callbackRepository->create(CallbackInterface::TYPE_COMPILATION_FAILED, []);
            $callback->setState($state);

            $this->entityPersister->persist($callback);
        }
    }

    /**
     * @param array<CallbackInterface::TYPE_*> $types
     */
    private function createCallbacksWithTypes(array $types): void
    {
        foreach ($types as $type) {
            $this->callbackRepository->create($type, []);
        }
    }
}
