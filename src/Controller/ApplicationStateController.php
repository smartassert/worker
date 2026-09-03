<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\ApplicationStateFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ApplicationStateController
{
    public function __construct(
        private ApplicationStateFactory $applicationStateFactory,
    ) {}

    #[Route('/application_state', name: 'application_state', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse($this->applicationStateFactory->create());
    }
}
