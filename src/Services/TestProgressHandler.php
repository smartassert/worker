<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use App\Enum\WorkerEventOutcome;
use App\Event\StepEvent;
use App\Event\TestEvent;
use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Model\Document\Document;
use App\Model\Document\StepException;
use App\Services\DocumentFactory\ExceptionFactory;
use App\Services\DocumentFactory\StepFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use webignition\YamlDocument\Document as YamlDocument;

class TestProgressHandler
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private readonly StepFactory $stepFactory,
        private readonly ExceptionFactory $exceptionFactory,
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
            $event = new StepEvent(
                $test,
                $step,
                $test->getSource(),
                $step->getName(),
                $eventOutcome
            );

            $this->eventDispatcher->dispatch($event);
        }

        if ('exception' == $document->getType()) {
            $exception = $this->exceptionFactory->create($documentData);

            if ($exception instanceof StepException) {
                $event = new StepEvent(
                    $test,
                    $exception,
                    $test->getSource(),
                    $exception->stepName,
                    WorkerEventOutcome::EXCEPTION
                );
            } else {
                $event = new TestEvent($test, $exception, $test->getSource(), WorkerEventOutcome::EXCEPTION);
            }

            $this->eventDispatcher->dispatch($event);
        }
    }
}
