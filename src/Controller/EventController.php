<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\WorkerEventRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class EventController
{
    #[Route('/event/{id<\d+>}', name: 'event_get', methods: ['GET'])]
    public function get(WorkerEventRepository $workerEventRepository, int $id): JsonResponse
    {
        $event = $workerEventRepository->findOneBy(['id' => $id]);

        return null == $event ? new JsonResponse([], 404) : new JsonResponse($event);
    }
}
