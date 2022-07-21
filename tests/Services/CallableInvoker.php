<?php

declare(strict_types=1);

namespace App\Tests\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;

class CallableInvoker
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    public function invoke(callable $callable): mixed
    {
        if ($callable instanceof \Closure) {
            $reflectionFunction = new \ReflectionFunction($callable);
            $args = [];

            foreach ($reflectionFunction->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof \ReflectionNamedType) {
                    $typeName = $type->getName();

                    if (class_exists($typeName)) {
                        $service = $this->fetchContainerServiceByClassName($typeName);

                        if (null !== $service) {
                            $args[] = $service;
                        }
                    } else {
                        $args[] = $this->fetchContainerParameter($parameter->getName());
                    }
                }
            }

            return $callable(...$args);
        }

        return null;
    }

    private function fetchContainerServiceByClassName(string $className): ?object
    {
        $service = $this->container->get($className);

        return $service instanceof $className ? $service : null;
    }

    /**
     * @return null|array<mixed>|bool|float|int|string
     */
    private function fetchContainerParameter(string $camelCasedName): array|bool|string|int|float|null
    {
        $containerParameterName = strtolower(
            (string) preg_replace('/(?<!^)[A-Z]/', '_$0', $camelCasedName)
        );

        return $this->container->getParameter($containerParameterName);
    }
}
