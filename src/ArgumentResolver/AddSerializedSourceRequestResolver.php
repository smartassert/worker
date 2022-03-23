<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use App\Request\AddSerializedSourceRequest;
use SmartAssert\YamlFile\Collection\Deserializer;
use SmartAssert\YamlFile\Exception\Collection\DeserializeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AddSerializedSourceRequestResolver implements ArgumentValueResolverInterface
{
    public function __construct(
        private readonly Deserializer $yamlFileCollectionDeserializer,
    ) {
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return AddSerializedSourceRequest::class === $argument->getType();
    }

    /**
     * @throws DeserializeException
     *
     * @return \Traversable<AddSerializedSourceRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Traversable
    {
        if ($this->supports($request, $argument)) {
            $sourceContent = $request->request->get(AddSerializedSourceRequest::KEY_SOURCE);
            $sourceContent = is_string($sourceContent) ? $sourceContent : '';

            yield new AddSerializedSourceRequest(
                $this->yamlFileCollectionDeserializer->deserialize($sourceContent)
            );
        }
    }
}
