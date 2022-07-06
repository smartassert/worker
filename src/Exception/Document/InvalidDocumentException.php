<?php

declare(strict_types=1);

namespace App\Exception\Document;

class InvalidDocumentException extends \Exception
{
    public const CODE_TYPE_EMPTY = 100;
    public const CODE_TYPE_INVALID = 101;

    /**
     * @param array<mixed> $data
     */
    public function __construct(
        public readonly array $data,
        string $message,
        int $code,
    ) {
        parent::__construct($message, $code);
    }

    /**
     * @param array<mixed> $data
     */
    public static function createForInvalidType(array $data, string $type, string $expectedType): self
    {
        return new InvalidDocumentException(
            $data,
            sprintf('Type "%s" is not "%s"', $type, $expectedType),
            InvalidDocumentException::CODE_TYPE_INVALID
        );
    }
}
