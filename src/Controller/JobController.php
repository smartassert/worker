<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Job;
use App\Entity\Source;
use App\Event\JobReadyEvent;
use App\Exception\InvalidManifestException;
use App\Exception\MissingManifestException;
use App\Exception\MissingTestSourceException;
use App\Repository\JobRepository;
use App\Repository\SourceRepository;
use App\Repository\TestRepository;
use App\Request\CreateJobRequest;
use App\Response\ErrorResponse;
use App\Services\CompilationProgress;
use App\Services\ErrorResponseFactory;
use App\Services\EventDeliveryProgress;
use App\Services\ExecutionProgress;
use App\Services\SourceFactory;
use App\Services\TestSerializer;
use App\Services\YamlSourceCollectionFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\YamlFile\Collection\Deserializer;
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
        YamlSourceCollectionFactory $yamlSourceCollectionFactory,
        SourceFactory $sourceFactory,
        EventDispatcherInterface $eventDispatcher,
        ErrorResponseFactory $errorResponseFactory,
        Deserializer $yamlFileCollectionDeserializer,
        SourceRepository $sourceRepository,
        CreateJobRequest $request,
    ): JsonResponse {
        if ($this->jobRepository->get() instanceof Job) {
            return new ErrorResponse('job/already_exists');
        }

        if ('' === $request->label) {
            return new ErrorResponse('label/missing');
        }

        if ('' === $request->eventDeliveryUrl) {
            return new ErrorResponse('event_delivery_url/missing');
        }

        if (null === $request->maximumDurationInSeconds) {
            return new ErrorResponse('maximum_duration_in_seconds/missing');
        }

        if ('' === trim($request->source)) {
            return new ErrorResponse('source/missing');
        }

        try {
            $provider = $yamlFileCollectionDeserializer->deserialize($request->source);
        } catch (DeserializeException $exception) {
            $response = $errorResponseFactory->createFromYamlFileCollectionDeserializeException($exception);
            if ($response instanceof JsonResponse) {
                return $response;
            }

            throw $exception;
        }

        try {
            $yamlSourceCollection = $yamlSourceCollectionFactory->create($provider);
        } catch (InvalidManifestException $exception) {
            return $errorResponseFactory->createFromInvalidManifestException($exception);
        } catch (MissingManifestException $exception) {
            return new ErrorResponse('source/manifest/missing');
        }

        try {
            $sourceFactory->createFromYamlSourceCollection($yamlSourceCollection);
        } catch (MissingTestSourceException $exception) {
            return $errorResponseFactory->createFromMissingTestSourceException($exception);
        }

        $this->jobRepository->add(new Job(
            $request->label,
            $request->eventDeliveryUrl,
            $request->maximumDurationInSeconds,
            $yamlSourceCollection->getManifest()->getTestPaths()
        ));

        $eventDispatcher->dispatch(new JobReadyEvent($sourceRepository->findAllPaths(Source::TYPE_TEST)));

        return new JsonResponse([]);
    }

    #[Route(self::PATH_JOB, name: 'status', methods: ['GET', 'HEAD'])]
    public function status(
        SourceRepository $sourceRepository,
        TestRepository $testRepository,
        TestSerializer $testSerializer,
        CompilationProgress $compilationProgress,
        ExecutionProgress $executionProgress,
        EventDeliveryProgress $eventDeliveryProgress,
    ): JsonResponse {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return new JsonResponse([], 400);
        }

        $tests = $testRepository->findAll();

        $data = array_merge(
            $job->jsonSerialize(),
            [
                'sources' => $sourceRepository->findAllPaths(),
                'compilation_state' => $compilationProgress->get(),
                'execution_state' => $executionProgress->get(),
                'event_delivery_state' => $eventDeliveryProgress->get(),
                'tests' => $testSerializer->serializeCollection($tests),
            ]
        );

        return new JsonResponse($data);
    }
}
