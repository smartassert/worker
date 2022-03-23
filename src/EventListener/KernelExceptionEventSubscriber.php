<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Services\ErrorResponseFactory;
use SmartAssert\YamlFile\Exception\Collection\DeserializeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class KernelExceptionEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ErrorResponseFactory $errorResponseFactory,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ExceptionEvent::class => [
                ['onKernelException', 100],
            ],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $response = null;

        if ($throwable instanceof DeserializeException) {
            $response = $this->errorResponseFactory->createFromYamlFileCollectionDeserializeException($throwable);
        }

        if ($response instanceof Response) {
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}
