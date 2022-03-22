<?php

declare(strict_types=1);

namespace App\Exception;

use SmartAssert\YamlFile\Exception\Collection\DeserializeException;

class SerializedSourceDeserializationException extends \Exception
{
    public function __construct(
        public readonly string $serializedSource,
        public readonly DeserializeException $exception,
    ) {
        parent::__construct($exception->getMessage(), 0, $exception);
    }
}
