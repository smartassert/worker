<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\WorkerEvent;
use Psr\Http\Message\ResponseInterface;

class NonSuccessfulHttpResponseException extends \Exception
{
    public function __construct(
        private WorkerEvent $workerEvent,
        private ResponseInterface $response
    ) {
        $code = $response->getStatusCode();

        parent::__construct(
            sprintf('%s: %s', $this->response::class, $code),
            $code
        );
    }

    public function getWorkerEvent(): WorkerEvent
    {
        return $this->workerEvent;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
