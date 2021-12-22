<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Callback\CallbackInterface;
use App\Event\CallbackHttpErrorEvent;
use App\Model\Callback\DelayedCallback;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class CallbackResponseHandler
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private BackoffStrategyFactory $backoffStrategyFactory
    ) {
    }

    public function handle(CallbackInterface $callback, ClientExceptionInterface | ResponseInterface $context): void
    {
        $callback->incrementRetryCount();
        $callback = $this->createNextCallback($callback, $context);

        $this->eventDispatcher->dispatch(new CallbackHttpErrorEvent($callback, $context));
    }

    private function createNextCallback(
        CallbackInterface $callback,
        ClientExceptionInterface | ResponseInterface $context
    ): CallbackInterface {
        if (0 === $callback->getRetryCount()) {
            return $callback;
        }

        return new DelayedCallback(
            $callback,
            $this->backoffStrategyFactory->create($context)
        );
    }
}
