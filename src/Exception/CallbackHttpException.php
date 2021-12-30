<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Callback\CallbackInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class CallbackHttpException extends \Exception
{
    public function __construct(
        private CallbackInterface $callback,
        private ClientExceptionInterface | ResponseInterface $context
    ) {
        $code = $this->deriveCode($this->context);

        parent::__construct(
            sprintf('%s: %s', $this->context::class, $code),
            $code,
            $this->context instanceof \Throwable ? $this->context : null
        );
    }

    public function getCallback(): CallbackInterface
    {
        return $this->callback;
    }

    public function getContext(): ClientExceptionInterface | ResponseInterface
    {
        return $this->context;
    }

    private function deriveCode(ClientExceptionInterface | ResponseInterface $context): int
    {
        if ($context instanceof ResponseInterface) {
            return $context->getStatusCode();
        }

        return $context->getCode();
    }
}
