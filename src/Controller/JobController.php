<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\InvalidManifestException;
use App\Exception\Manifest\ManifestFactoryExceptionInterface;
use App\Exception\MissingManifestException;
use App\Exception\MissingTestSourceException;
use App\Message\JobReadyMessage;
use App\Repository\TestRepository;
use App\Request\AddSerializedSourceRequest;
use App\Request\AddSourcesRequest;
use App\Request\CreateJobRequest;
use App\Request\JobCreateRequest;
use App\Response\BadAddSourcesRequestResponse;
use App\Response\BadJobCreateRequestResponse;
use App\Response\ErrorResponse;
use App\Services\CallbackState;
use App\Services\CompilationState;
use App\Services\EntityFactory\JobFactory;
use App\Services\EntityStore\JobStore;
use App\Services\EntityStore\SourceStore;
use App\Services\ErrorResponseFactory;
use App\Services\ExecutionState;
use App\Services\ManifestFactory;
use App\Services\SourceFactory;
use App\Services\TestSerializer;
use App\Services\YamlSourceCollectionFactory;
use SmartAssert\YamlFile\Collection\Deserializer;
use SmartAssert\YamlFile\Exception\Collection\DeserializeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class JobController
{
    public const PATH_JOB = '/job';

    private JobStore $jobStore;

    public function __construct(JobStore $jobStore)
    {
        $this->jobStore = $jobStore;
    }

    /**
     * @throws DeserializeException
     */
    #[Route('/create_combined', name: 'create_combined', methods: ['POST'])]
    public function createCombined(
        JobFactory $jobFactory,
        YamlSourceCollectionFactory $yamlSourceCollectionFactory,
        SourceFactory $sourceFactory,
        MessageBusInterface $messageBus,
        ErrorResponseFactory $errorResponseFactory,
        Deserializer $yamlFileCollectionDeserializer,
        CreateJobRequest $request,
    ): JsonResponse {
        if (true === $this->jobStore->has()) {
            return new ErrorResponse('create', 'job already exists', 100, Response::HTTP_BAD_REQUEST);
        }

        if ('' === $request->label) {
            return new ErrorResponse('create', 'label missing', 200, Response::HTTP_BAD_REQUEST);
        }

        if ('' === $request->callbackUrl) {
            return new ErrorResponse('create', 'callback_url missing', 300, Response::HTTP_BAD_REQUEST);
        }

        if (null === $request->maximumDurationInSeconds) {
            return new ErrorResponse('create', 'maximum_duration_in_seconds missing', 400, Response::HTTP_BAD_REQUEST);
        }

        if ('' === trim($request->source)) {
            return new ErrorResponse('create', 'source missing', 500, Response::HTTP_BAD_REQUEST);
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
            $sourceFactory->createFromYamlSourceCollection($yamlSourceCollectionFactory->create($provider));
        } catch (InvalidManifestException $exception) {
            return $errorResponseFactory->createFromInvalidManifestException($exception);
        } catch (MissingManifestException $exception) {
            return $errorResponseFactory->createFromMissingManifestException($exception);
        } catch (MissingTestSourceException $exception) {
            return $errorResponseFactory->createFromMissingTestSourceException($exception);
        }

        $jobFactory->create($request->label, $request->callbackUrl, $request->maximumDurationInSeconds);

        $messageBus->dispatch(new JobReadyMessage());

        return new JsonResponse([]);
    }

    #[Route(self::PATH_JOB, name: 'create', methods: ['POST'])]
    public function create(JobFactory $jobFactory, JobCreateRequest $request): JsonResponse
    {
        if ('' === $request->getLabel()) {
            return BadJobCreateRequestResponse::createLabelMissingResponse();
        }

        if ('' === $request->getCallbackUrl()) {
            return BadJobCreateRequestResponse::createCallbackUrlMissingResponse();
        }

        if (null === $request->getMaximumDurationInSeconds()) {
            return BadJobCreateRequestResponse::createMaximumDurationMissingResponse();
        }

        if (true === $this->jobStore->has()) {
            return BadJobCreateRequestResponse::createJobAlreadyExistsResponse();
        }

        $jobFactory->create(
            $request->getLabel(),
            $request->getCallbackUrl(),
            $request->getMaximumDurationInSeconds()
        );

        return new JsonResponse([]);
    }

    #[Route('/add-sources', name: 'add-sources', methods: ['POST'])]
    public function addSources(
        ManifestFactory $manifestFactory,
        SourceStore $sourceStore,
        SourceFactory $sourceFactory,
        MessageBusInterface $messageBus,
        AddSourcesRequest $addSourcesRequest
    ): JsonResponse {
        if (false === $this->jobStore->has()) {
            return BadAddSourcesRequestResponse::createJobMissingResponse();
        }

        if (true === $sourceStore->hasAny()) {
            return BadAddSourcesRequestResponse::createSourcesNotEmptyResponse();
        }

        $manifestUploadedFile = $addSourcesRequest->getManifest();
        if (!$manifestUploadedFile instanceof UploadedFile) {
            return BadAddSourcesRequestResponse::createManifestMissingResponse();
        }

        try {
            $manifest = $manifestFactory->createFromUploadedFile($manifestUploadedFile);
        } catch (ManifestFactoryExceptionInterface $manifestFactoryException) {
            return BadAddSourcesRequestResponse::createInvalidRequestManifest($manifestFactoryException);
        }

        $manifestTestPaths = $manifest->getTestPaths();
        if ([] === $manifestTestPaths) {
            return BadAddSourcesRequestResponse::createManifestEmptyResponse();
        }

        $uploadedSources = $addSourcesRequest->getUploadedSources();

        try {
            $sourceFactory->createCollectionFromManifest($manifest, $uploadedSources);
        } catch (MissingTestSourceException $testSourceException) {
            return BadAddSourcesRequestResponse::createSourceMissingResponse($testSourceException->getPath());
        }

        $messageBus->dispatch(new JobReadyMessage());

        return new JsonResponse([]);
    }

    #[Route('/add-sources-as-single-file', name: 'add-sources-as-single-file', methods: ['POST'])]
    public function addSerializedSource(
        YamlSourceCollectionFactory $factory,
        SourceFactory $sourceFactory,
        MessageBusInterface $messageBus,
        AddSerializedSourceRequest $request,
        ErrorResponseFactory $errorResponseFactory,
    ): JsonResponse {
        // @todo: validate yaml file provider in #163
        if (false === $this->jobStore->has()) {
            return BadAddSourcesRequestResponse::createJobMissingResponse();
        }

        try {
            $sourceFactory->createFromYamlSourceCollection(
                $factory->create($request->provider)
            );
        } catch (InvalidManifestException $exception) {
            return $errorResponseFactory->createFromInvalidManifestException($exception);
        } catch (MissingManifestException $exception) {
            return $errorResponseFactory->createFromMissingManifestException($exception);
        } catch (MissingTestSourceException $exception) {
            return $errorResponseFactory->createFromMissingTestSourceException($exception);
        }

        $messageBus->dispatch(new JobReadyMessage());

        return new JsonResponse([]);
    }

    #[Route(self::PATH_JOB, name: 'status', methods: ['GET', 'HEAD'])]
    public function status(
        SourceStore $sourceStore,
        TestRepository $testRepository,
        TestSerializer $testSerializer,
        CompilationState $compilationState,
        ExecutionState $executionState,
        CallbackState $callbackState,
    ): JsonResponse {
        if (false === $this->jobStore->has()) {
            return new JsonResponse([], 400);
        }

        $job = $this->jobStore->get();
        $tests = $testRepository->findAll();

        $data = array_merge(
            $job->jsonSerialize(),
            [
                'sources' => $sourceStore->findAllPaths(),
                'compilation_state' => (string) $compilationState,
                'execution_state' => (string) $executionState,
                'callback_state' => (string) $callbackState,
                'tests' => $testSerializer->serializeCollection($tests),
            ]
        );

        return new JsonResponse($data);
    }
}
