<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Callback\CallbackInterface;
use App\Repository\CallbackRepository;
use App\Services\CallbackAborter;
use App\Services\CallbackStateMutator;
use App\Tests\AbstractBaseFunctionalTest;

class CallbackAborterTest extends AbstractBaseFunctionalTest
{
    private CallbackAborter $aborter;
    private CallbackRepository $callbackRepository;
    private CallbackStateMutator $stateMutator;

    protected function setUp(): void
    {
        parent::setUp();

        $aborter = self::getContainer()->get(CallbackAborter::class);
        \assert($aborter instanceof CallbackAborter);
        $this->aborter = $aborter;

        $callbackRepository = self::getContainer()->get(CallbackRepository::class);
        \assert($callbackRepository instanceof CallbackRepository);
        $this->callbackRepository = $callbackRepository;

        $repository = self::getContainer()->get(CallbackRepository::class);
        \assert($repository instanceof CallbackRepository);
        $this->callbackRepository = $repository;

        $stateMutator = self::getContainer()->get(CallbackStateMutator::class);
        \assert($stateMutator instanceof CallbackStateMutator);
        $this->stateMutator = $stateMutator;
    }

    public function testAbort(): void
    {
        $callback = $this->callbackRepository->create(CallbackInterface::TYPE_JOB_COMPLETED, '', []);
        $this->stateMutator->setQueued($callback);

        $id = $callback->getId();

        self::assertIsInt($id);
        self::assertNotSame(CallbackInterface::STATE_FAILED, $callback->getState());

        $this->aborter->abort($id);

        $callback = $this->callbackRepository->find($id);
        \assert($callback instanceof CallbackInterface);

        self::assertSame(CallbackInterface::STATE_FAILED, $callback->getState());
    }
}
