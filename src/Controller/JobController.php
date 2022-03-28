<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\InvalidManifestException;
use App\Exception\MissingManifestException;
use App\Exception\MissingTestSourceException;
use App\Message\JobReadyMessage;
use App\Repository\TestRepository;
use App\Request\CreateJobRequest;
use App\Services\CallbackState;
use App\Services\CompilationState;
use App\Services\EntityFactory\JobFactory;
use App\Services\EntityStore\JobStore;
use App\Services\EntityStore\SourceStore;
use App\Services\ErrorResponseFactory;
use App\Services\ExecutionState;
use App\Services\SourceFactory;
use App\Services\TestSerializer;
use App\Services\YamlSourceCollectionFactory;
use SmartAssert\YamlFile\Collection\Deserializer;
use SmartAssert\YamlFile\Exception\Collection\DeserializeException;
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

    /**
     * @throws DeserializeException
     */
    #[Route(self::PATH_JOB, name: 'create', methods: ['POST'])]
    public function create(
        JobFactory $jobFactory,
        YamlSourceCollectionFactory $yamlSourceCollectionFactory,
        SourceFactory $sourceFactory,
        MessageBusInterface $messageBus,
        ErrorResponseFactory $errorResponseFactory,
        Deserializer $yamlFileCollectionDeserializer,
        CreateJobRequest $request,
    ): JsonResponse {
        if (true === $this->jobStore->has()) {
            return new JsonResponse(['error_state' => 'job/already_exists'], 400);
        }

        if ('' === $request->label) {
            return new JsonResponse(['error_state' => 'label/missing'], 400);
        }

        if ('' === $request->callbackUrl) {
            return new JsonResponse(['error_state' => 'callback_url/missing'], 400);
        }

        if (null === $request->maximumDurationInSeconds) {
            return new JsonResponse(['error_state' => 'maximum_duration_in_seconds/missing'], 400);
        }

        if ('' === trim($request->source)) {
            return new JsonResponse(['error_state' => 'source/missing'], 400);
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
