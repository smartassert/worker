<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\SerializedState;
use App\Services\ApplicationProgress;
use App\Services\CompilationProgress;
use App\Services\EventDeliveryProgress;
use App\Services\ExecutionProgress;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ApplicationStateController
{
    public function __construct(
        private readonly ApplicationProgress $applicationProgress,
        private readonly CompilationProgress $compilationProgress,
        private readonly ExecutionProgress $executionProgress,
        private readonly EventDeliveryProgress $eventDeliveryProgress,
    ) {
    }

    #[Route('/application_state', name: 'application_state', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse([
            'application' => new SerializedState($this->applicationProgress->get()),
            'compilation' => new SerializedState($this->compilationProgress->get()),
            'execution' => new SerializedState($this->executionProgress->get()),
            'event_delivery' => new SerializedState($this->eventDeliveryProgress->get()),
        ]);
    }
}
