<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Callback\CallbackInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class SendCallbackResult
{
    public function __construct(
        private CallbackInterface $callback,
        private ClientExceptionInterface | ResponseInterface $context
    ) {
    }

    public function getCallback(): CallbackInterface
    {
        return $this->callback;
    }

    public function getContext(): ClientExceptionInterface | ResponseInterface
    {
        return $this->context;
    }

    public function isSuccess(): bool
    {
        return false === $this->isError();
    }

    public function isError(): bool
    {
        if ($this->context instanceof ClientExceptionInterface) {
            return true;
        }

        if ($this->context->getStatusCode() >= 300) {
            return true;
        }

        return false;
    }
}
