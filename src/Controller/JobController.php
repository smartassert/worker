<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Job;
use App\Entity\Source;
use App\Event\EmittableEvent\JobStartedEvent;
use App\Exception\JobNotFoundException;
use App\Exception\MissingTestSourceException;
use App\Repository\JobRepository;
use App\Repository\SourceRepository;
use App\Request\CreateJobRequest;
use App\Response\ErrorResponse;
use App\Services\ErrorResponseFactory;
use App\Services\JobStatusFactory;
use App\Services\SourceFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerJobSource\Exception\InvalidManifestException;
use SmartAssert\WorkerJobSource\JobSourceDeserializer;
use SmartAssert\YamlFile\Exception\Collection\DeserializeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class JobController
{
    public const PATH_JOB = '/job';

    public function __construct(
        private readonly JobRepository $jobRepository
    ) {
    }

    /**
     * @throws DeserializeException
     */
    #[Route(self::PATH_JOB, name: 'create', methods: ['POST'])]
    public function create(
        SourceFactory $sourceFactory,
        EventDispatcherInterface $eventDispatcher,
        ErrorResponseFactory $errorResponseFactory,
        SourceRepository $sourceRepository,
        JobStatusFactory $jobStatusFactory,
        JobSourceDeserializer $jobSourceDeserializer,
        CreateJobRequest $request,
    ): JsonResponse {
        if ($this->jobRepository->has()) {
            return new ErrorResponse('job/already_exists');
        }

        if ('' === $request->label) {
            return new ErrorResponse('label/missing');
        }

        if ('' === $request->eventDeliveryUrl) {
            return new ErrorResponse('event_delivery_url/missing');
        }

        if ('' === $request->resultsToken) {
            return new ErrorResponse('results_token/missing');
        }

        if (null === $request->maximumDurationInSeconds) {
            return new ErrorResponse('maximum_duration_in_seconds/missing');
        }

        if ('' === trim($request->source)) {
            return new ErrorResponse('source/missing');
        }

        try {
            $jobSource = $jobSourceDeserializer->deserialize($request->source);
        } catch (InvalidManifestException $e) {
            return $errorResponseFactory->createFromInvalidManifestException($e);
        } catch (DeserializeException $e) {
            $response = $errorResponseFactory->createFromYamlFileCollectionDeserializeException($e);
            if ($response instanceof JsonResponse) {
                return $response;
            }

            throw $e;
        }

        try {
            $sourceFactory->createFromJobSource($jobSource);
        } catch (MissingTestSourceException $exception) {
            return $errorResponseFactory->createFromMissingTestSourceException($exception);
        }

        $job = $this->jobRepository->add(new Job(
            $request->label,
            $request->resultsToken,
            $request->maximumDurationInSeconds,
            $jobSource->manifest->testPaths
        ));

        $eventDispatcher->dispatch(new JobStartedEvent(
            $job->label,
            $sourceRepository->findAllPaths(Source::TYPE_TEST)
        ));

        return new JsonResponse($jobStatusFactory->create($job));
    }

    #[Route(self::PATH_JOB, name: 'status', methods: ['GET', 'HEAD'])]
    public function status(JobStatusFactory $jobStatusFactory): JsonResponse
    {
        try {
            return new JsonResponse(
                $jobStatusFactory->create(
                    $this->jobRepository->get()
                )
            );
        } catch (JobNotFoundException) {
            return new JsonResponse([], 400);
        }
    }
}
