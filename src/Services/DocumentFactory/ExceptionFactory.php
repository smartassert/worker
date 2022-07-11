<?php

declare(strict_types=1);

namespace App\Services\DocumentFactory;

use App\Enum\ExecutionExceptionScope;
use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\Document;
use App\Model\Document\Exception;
use App\Model\Document\StepException;

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
            $stepName = '' === $stepName ? null : $stepName;

            return null === $stepName
                ? new Exception(ExecutionExceptionScope::TEST, $data)
                : new StepException($stepName, $data);
        }

        throw InvalidDocumentException::createForInvalidType($data, $type, 'exception');
    }
}
