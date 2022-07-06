<?php

declare(strict_types=1);

namespace App\Services\DocumentFactory;

use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Model\Document\Document;
use App\Model\Document\Step;

class StepFactory implements DocumentFactoryInterface
{
    /**
     * @param array<mixed> $data
     *
     * @throws InvalidDocumentException
     * @throws InvalidStepException
     */
    public function create(array $data): Step
    {
        $document = new Document($data);
        $type = $document->getType();

        if ('step' === $type) {
            $name = trim((string) $document->getPayloadStringValue('name'));

            if ('' === $name) {
                throw new InvalidStepException(
                    $data,
                    'Payload name missing',
                    InvalidStepException::CODE_NAME_MISSING
                );
            }

            return new Step($name, $data);
        }

        throw InvalidDocumentException::createForInvalidType($data, $type, 'step');
    }
}
