<?php

declare(strict_types=1);

namespace App\Services\DocumentFactory;

use App\Enum\ExecutionExceptionScope;
use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\Document;
use App\Model\Document\Exception;

class ExceptionFactory implements DocumentFactoryInterface
{
    /**
     * @param array<mixed> $data
     *
     * @throws InvalidDocumentException
     */
    public function create(array $data): Exception
    {
        $document = new Document($data);
        $type = $document->getType();

        if ('exception' === $type) {
            $stepName = trim((string) $document->getPayloadStringValue('step'));

            return new Exception(
                '' === $stepName ? ExecutionExceptionScope::TEST : ExecutionExceptionScope::STEP,
                $data
            );
        }

        throw new InvalidDocumentException(
            $data,
            sprintf('Type "%s" is not "exception"', $type),
            InvalidDocumentException::CODE_TYPE_INVALID
        );
    }
}
