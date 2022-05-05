<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\Callback\CallbackInterface;
use App\Repository\CallbackRepository;
use App\Tests\Model\CallbackSetup;
use Doctrine\ORM\EntityManagerInterface;

class TestCallbackFactory
{
    public function __construct(
        private readonly CallbackRepository $callbackRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function create(CallbackSetup $callbackSetup): CallbackInterface
    {
        $callback = $this->callbackRepository->create(
            $callbackSetup->getType(),
            'non-empty reference',
            $callbackSetup->getPayload()
        );

        $callback->setState($callbackSetup->getState());
        $this->entityManager->flush();

        return $callback;
    }
}
