<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use App\Model\SerializedSource;
use App\Request\AddSerializedSourceRequest;
use SmartAssert\YamlFile\Collection\Deserializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Yaml\Exception\ParseException;

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
     * @throws ParseException
     *
     * @return iterable<AddSerializedSourceRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($this->supports($request, $argument)) {
            $provider = $this->yamlFileCollectionDeserializer->deserialize($request->getContent());

            yield new AddSerializedSourceRequest(
                new SerializedSource($provider)
            );
        }
    }
}
