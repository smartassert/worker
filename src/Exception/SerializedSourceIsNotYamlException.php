<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Yaml\Exception\ParseException;

class SerializedSourceIsNotYamlException extends \Exception
{
    public function __construct(
        public readonly string $serializedSource,
        public readonly ParseException $parseException,
    ) {
        parent::__construct($parseException->getMessage(), 0, $parseException);
    }
}
