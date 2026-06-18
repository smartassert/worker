<?php

declare(strict_types=1);

namespace App\Event\EmittableEvent;

use App\Entity\Test;
use App\Model\Document\Document;

class StepEvent extends AbstractEvent implements EmittableEventInterface, HasTestInterface
{
    /**
     * @param non-empty-string           $path
     * @param non-empty-string           $stepName
     * @param EventTypeInterface::STEP_* $type
     */
    public function __construct(
        private readonly Test $test,
        Document $document,
        string $path,
        string $stepName,
        string $type,
    ) {
        parent::__construct(
            $stepName,
            $type,
            [
                'source' => $path,
                'document' => $document->getData(),
                'name' => $stepName,
            ],
            [
                $path,
                $stepName,
            ]
        );
    }

    public function getTest(): Test
    {
        return $this->test;
    }
}
