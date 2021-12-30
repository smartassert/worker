<?php

declare(strict_types=1);

namespace App\Tests\Services;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventListenerRemover
{
    public function __construct(
        private ContainerInterface $container,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param class-string|string $serviceId
     * @param class-string|string $eventName
     */
    public function removeServiceMethodForEvent(string $serviceId, string $eventName, string $methodName): void
    {
        $service = $this->container->get($serviceId);
        if (is_object($service)) {
            $callable = [$service, $methodName];
            if (is_callable($callable)) {
                $this->eventDispatcher->removeListener($eventName, $callable);
            }
        }
    }

    /**
     * @param class-string|string           $serviceId
     * @param array<class-string, string[]> $eventsAndMethods
     */
    public function removeServiceMethodsForEvents(string $serviceId, array $eventsAndMethods): void
    {
        foreach ($eventsAndMethods as $eventName => $methodNames) {
            foreach ($methodNames as $methodName) {
                $this->removeServiceMethodForEvent($serviceId, $eventName, $methodName);
            }
        }
    }

    /**
     * @param array<class-string|string, array<class-string, string[]>> $definitions
     */
    public function remove(array $definitions): void
    {
        foreach ($definitions as $serviceId => $methodNames) {
            $this->removeServiceMethodsForEvents($serviceId, $methodNames);
        }
    }
}
