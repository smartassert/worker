<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Callback\CallbackInterface;
use App\Message\SendCallbackMessage;
use App\Model\SendCallbackResult;
use App\Repository\CallbackRepository;
use App\Services\CallbackSender;
use App\Services\CallbackStateMutator;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SendCallbackHandler implements MessageHandlerInterface
{
    public function __construct(
        private CallbackRepository $repository,
        private CallbackSender $sender,
        private CallbackStateMutator $stateMutator
    ) {
    }

    public function __invoke(SendCallbackMessage $message): void
    {
        $callback = $this->repository->find($message->getCallbackId());

        if ($callback instanceof CallbackInterface) {
            $this->stateMutator->setSending($callback);
            $result = $this->sender->send($callback);

            if ($result instanceof SendCallbackResult && $result->isSuccess()) {
                $this->stateMutator->setComplete($callback);
            }
        }
    }
}
