<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Job;
use App\Repository\CallbackRepository;
use App\Services\CallbackReferenceFactory;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractEventCallbackFactory implements EventCallbackFactoryInterface
{
    public function __construct(
        private readonly CallbackRepository $callbackRepository,
        private readonly CallbackReferenceFactory $callbackReferenceFactory,
    ) {
    }

    /**
     * @param CallbackInterface::TYPE_* $type
     * @param array<mixed>              $data
     */
    protected function create(Job $job, Event $event, string $type, array $data): CallbackInterface
    {
        return $this->callbackRepository->create(
            $type,
            $this->callbackReferenceFactory->createForEvent($job, $event),
            $data
        );
    }
}
