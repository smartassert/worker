<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\JobNotFoundException;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class EventController
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly WorkerEventRepository $workerEventRepository,
        private readonly WorkerEventSerializer $workerEventSerializer,
    ) {
    }

    #[Route('/event/{id<\d+>}', name: 'event_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        try {
            $job = $this->jobRepository->get();
        } catch (JobNotFoundException) {
            return new JsonResponse([], 400);
        }

        $event = $this->workerEventRepository->findOneBy(['id' => $id]);
        if (null === $event) {
            return new JsonResponse([], 404);
        }

        $serializedEvent = $this->workerEventSerializer->serialize($job, $event);
        $serializedEvent['header']['state'] = $event->getState()->value;

        return new JsonResponse($serializedEvent);
    }
}
