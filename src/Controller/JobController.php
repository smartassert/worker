<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\InvalidManifestException;
use App\Exception\Manifest\ManifestFactoryExceptionInterface;
use App\Exception\MissingManifestException;
use App\Exception\MissingTestSourceException;
use App\Message\JobReadyMessage;
use App\Model\YamlSourceCollection;
use App\Repository\TestRepository;
use App\Request\AddSerializedSourceRequest;
use App\Request\AddSourcesRequest;
use App\Request\JobCreateRequest;
use App\Response\BadAddSourcesRequestResponse;
use App\Response\BadJobCreateRequestResponse;
use App\Services\CallbackState;
use App\Services\CompilationState;
use App\Services\EntityFactory\JobFactory;
use App\Services\EntityStore\JobStore;
use App\Services\EntityStore\SourceStore;
use App\Services\ExecutionState;
use App\Services\ManifestFactory;
use App\Services\SourceFactory;
use App\Services\TestSerializer;
use App\Services\YamlSourceCollectionFactory;
use SmartAssert\YamlFile\Exception\ProvisionException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    ): JsonResponse {
        // @todo: validate yaml file provider in #163

        if (false === $this->jobStore->has()) {
            return BadAddSourcesRequestResponse::createJobMissingResponse();
        }

        $yamlSourceCollection = null;

        try {
            $yamlSourceCollection = $factory->create($request->provider);
        } catch (InvalidManifestException | MissingManifestException | ProvisionException $e) {
            // @todo: handle via ExceptionEvent listener in #166
        }
        \assert($yamlSourceCollection instanceof YamlSourceCollection);

        try {
            $sourceFactory->createFromYamlSourceCollection($yamlSourceCollection);
        } catch (MissingTestSourceException | ProvisionException $e) {
            // @todo: handle via ExceptionEvent listener in #166
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
