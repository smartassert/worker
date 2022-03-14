<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use App\Model\SourceCollection;
use SmartAssert\YamlFile\Collection\Deserializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Yaml\Exception\ParseException;

class SourceCollectionResolver implements ArgumentValueResolverInterface
{
    public function __construct(
        private readonly Deserializer $yamlFileCollectionDeserializer,
    ) {
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return SourceCollection::class === $argument->getType();
    }

    /**
     * @throws ParseException
     *
     * @return iterable<SourceCollection>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($this->supports($request, $argument)) {
            $provider = $this->yamlFileCollectionDeserializer->deserialize($request->getContent());

            yield new SourceCollection($provider);
        }
    }
}
