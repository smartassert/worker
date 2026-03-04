<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\WorkerEvent;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;

class EventDeliveryException extends \Exception
{
    public function __construct(
        public readonly WorkerEvent $event,
        public readonly \Throwable $previous,
    ) {
        parent::__construct(
            sprintf(
                'Failed to send event "%d": "%s"',
                $event->getId(),
                $previous->getMessage(),
            ),
            0,
            $previous,
        );
    }

    public function getHttpResponse(): ?ResponseInterface
    {
        return $this->previous instanceof NonSuccessResponseException
            ? $this->previous->getResponse()->getHttpResponse()
            : null;
    }
}
