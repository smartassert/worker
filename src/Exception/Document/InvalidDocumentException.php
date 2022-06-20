<?php

declare(strict_types=1);

namespace App\Exception\Document;

class InvalidDocumentException extends \Exception
{
    public const CODE_TYPE_EMPTY = 100;

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
}
