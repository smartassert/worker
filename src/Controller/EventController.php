<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\SerializableEvent;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class EventController
{
    #[Route('/event/{id<\d+>}', name: 'event_get', methods: ['GET'])]
    public function get(
        JobRepository $jobRepository,
        WorkerEventRepository $workerEventRepository,
        int $id,
    ): JsonResponse {
        $job = $jobRepository->get();
        $event = $workerEventRepository->findOneBy(['id' => $id]);

        if (null === $job || null === $event) {
            return new JsonResponse([], 404);
        }

        $serializableEvent = new SerializableEvent($job->getLabel(), $event);

        return new JsonResponse($serializableEvent);
    }
}
