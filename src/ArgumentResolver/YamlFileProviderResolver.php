<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use SmartAssert\YamlFile\Collection\Deserializer;
use SmartAssert\YamlFile\Collection\ProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Yaml\Exception\ParseException;

class YamlFileProviderResolver implements ArgumentValueResolverInterface
{
    public function __construct(
        private readonly Deserializer $yamlFileCollectionDeserializer,
    ) {
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return ProviderInterface::class === $argument->getType();
    }

    /**
     * @throws ParseException
     *
     * @return iterable<ProviderInterface>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($this->supports($request, $argument)) {
            yield $this->yamlFileCollectionDeserializer->deserialize($request->getContent());
        }
    }
}
