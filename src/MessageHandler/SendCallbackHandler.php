<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Callback\CallbackInterface;
use App\Exception\NonSuccessfulHttpResponseException;
use App\Message\SendCallbackMessage;
use App\Repository\CallbackRepository;
use App\Services\CallbackSender;
use App\Services\CallbackStateMutator;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SendCallbackHandler implements MessageHandlerInterface
{
    public function __construct(
        private CallbackRepository $repository,
        private CallbackSender $sender,
        private CallbackStateMutator $stateMutator
    ) {
    }

    /**
     * @throws NonSuccessfulHttpResponseException
     * @throws ClientExceptionInterface
     */
    public function __invoke(SendCallbackMessage $message): void
    {
        $callback = $this->repository->find($message->getCallbackId());

        if ($callback instanceof CallbackInterface) {
            $this->stateMutator->setSending($callback);
            $this->sender->send($callback);
            $this->stateMutator->setComplete($callback);
        }
    }
}
