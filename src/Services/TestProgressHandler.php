<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use App\Enum\WorkerEventOutcome;
use App\Event\StepEvent;
use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Model\Document\Document;
use App\Services\DocumentFactory\StepFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use webignition\YamlDocument\Document as YamlDocument;

class TestProgressHandler
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private readonly StepFactory $stepFactory,
    ) {
    }

    /**
     * @throws InvalidDocumentException
     * @throws InvalidStepException
     */
    public function handle(Test $test, YamlDocument $yamlDocument): void
    {
        $documentData = $yamlDocument->parse();
        $documentData = is_array($documentData) ? $documentData : [];
        $document = new Document($documentData);

        if ('step' === $document->getType()) {
            $step = $this->stepFactory->create($documentData);
            $eventOutcome = $step->statusIsPassed() ? WorkerEventOutcome::PASSED : WorkerEventOutcome::FAILED;
            $event = new StepEvent($test, $step, $eventOutcome);

            $this->eventDispatcher->dispatch($event);
        }
    }
}
