<?php

namespace App\Tests\Unit\Controller;

use App\Controller\JobController;
use App\Exception\Manifest\InvalidMimeTypeException;
use App\Exception\MissingTestSourceException;
use App\Request\AddSourcesRequest;
use App\Request\JobCreateRequest;
use App\Response\BadAddSourcesRequestResponse;
use App\Response\BadJobCreateRequestResponse;
use App\Services\EntityFactory\JobFactory;
use App\Services\EntityStore\JobStore;
use App\Services\EntityStore\SourceStore;
use App\Services\ManifestFactory;
use App\Services\SourceFactory;
use App\Tests\Mock\Entity\MockJob;
use App\Tests\Mock\Model\MockManifest;
use App\Tests\Mock\Model\MockUploadedSourceCollection;
use App\Tests\Mock\Request\MockAddSourcesRequest;
use App\Tests\Mock\Request\MockJobCreateRequest;
use App\Tests\Mock\Services\MockJobFactory;
use App\Tests\Mock\Services\MockJobStore;
use App\Tests\Mock\Services\MockManifestFactory;
use App\Tests\Mock\Services\MockSourceFactory;
use App\Tests\Mock\Services\MockSourceStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;

class JobControllerTest extends TestCase
{
    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(
        JobCreateRequest $jobCreateRequest,
        JobStore $jobStore,
        JobFactory $jobFactory,
        JsonResponse $expectedResponse
    ): void {
        $controller = new JobController($jobStore);

        $response = $controller->create($jobFactory, $jobCreateRequest);

        self::assertSame(
            $expectedResponse->getStatusCode(),
            $response->getStatusCode()
        );

        self::assertSame(
            json_decode((string) $expectedResponse->getContent(), true),
            json_decode((string) $response->getContent(), true)
        );
    }

    /**
     * @return array[]
     */
    public function createDataProvider(): array
    {
        return [
            'label missing' => [
                'jobCreateRequest' => (new MockJobCreateRequest())
                    ->withGetLabelCall('')
                    ->getMock(),
                'jobStore' => (new MockJobStore())->getMock(),
                'jobFactory' => (new MockJobFactory())->getMock(),
                'expectedResponse' => BadJobCreateRequestResponse::createLabelMissingResponse(),
            ],
            'callback url missing' => [
                'jobCreateRequest' => (new MockJobCreateRequest())
                    ->withGetLabelCall('label')
                    ->withGetCallbackUrlCall('')
                    ->getMock(),
                'jobStore' => (new MockJobStore())->getMock(),
                'jobFactory' => (new MockJobFactory())->getMock(),
                'expectedResponse' => BadJobCreateRequestResponse::createCallbackUrlMissingResponse(),
            ],
            'maximum duration missing' => [
                'jobCreateRequest' => (new MockJobCreateRequest())
                    ->withGetLabelCall('label')
                    ->withGetCallbackUrlCall('http://example.com')
                    ->withGetMaximumDurationInSecondsCall(null)
                    ->getMock(),
                'jobStore' => (new MockJobStore())->getMock(),
                'jobFactory' => (new MockJobFactory())->getMock(),
                'expectedResponse' => BadJobCreateRequestResponse::createMaximumDurationMissingResponse(),
            ],
            'job already exists' => [
                'jobCreateRequest' => (new MockJobCreateRequest())
                    ->withGetLabelCall('label')
                    ->withGetCallbackUrlCall('http://example.com')
                    ->withGetMaximumDurationInSecondsCall(10)
                    ->getMock(),
                'jobStore' => (new MockJobStore())
                    ->withHasCall(true)
                    ->getMock(),
                'jobFactory' => (new MockJobFactory())->getMock(),
                'expectedResponse' => BadJobCreateRequestResponse::createJobAlreadyExistsResponse(),
            ],
            'created' => [
                'jobCreateRequest' => (new MockJobCreateRequest())
                    ->withGetLabelCall('label')
                    ->withGetCallbackUrlCall('http://example.com')
                    ->withGetMaximumDurationInSecondsCall(10)
                    ->getMock(),
                'jobStore' => (new MockJobStore())
                    ->withHasCall(false)
                    ->getMock(),
                'jobFactory' => (new MockJobFactory())
                    ->withCreateCall('label', 'http://example.com', 10)
                    ->getMock(),
                'expectedResponse' => new JsonResponse(),
            ],
        ];
    }

    /**
     * @dataProvider addSourcesDataProvider
     */
    public function testAddSources(
        AddSourcesRequest $addSourcesRequest,
        JobStore $jobStore,
        ManifestFactory $manifestFactory,
        SourceStore $sourceStore,
        SourceFactory $sourceFactory,
        JsonResponse $expectedResponse
    ): void {
        $controller = new JobController($jobStore);

        $response = $controller->addSources(
            $manifestFactory,
            $sourceStore,
            $sourceFactory,
            \Mockery::mock(MessageBusInterface::class),
            $addSourcesRequest
        );

        self::assertSame(
            $expectedResponse->getStatusCode(),
            $response->getStatusCode()
        );

        self::assertSame(
            json_decode((string) $expectedResponse->getContent(), true),
            json_decode((string) $response->getContent(), true)
        );
    }

    /**
     * @return array[]
     */
    public function addSourcesDataProvider(): array
    {
        $job = (new MockJob())
            ->getMock()
        ;

        $nonEmptyManifest = (new MockManifest())
            ->withGetTestPathsCall([
                'Test/test1.yml',
            ])
            ->getMock()
        ;

        $emptyManifest = (new MockManifest())
            ->withGetTestPathsCall([])
            ->getMock()
        ;

        $manifestUploadedFile = \Mockery::mock(UploadedFile::class);

        $uploadedSources = (new MockUploadedSourceCollection())->getMock();
        $emptySourceFactory = (new MockSourceFactory())->getMock();

        $emptySourceStore = (new MockSourceStore())
            ->withHasAnyCall(false)
            ->getMock()
        ;

        return [
            'job missing' => [
                'addSourcesRequest' => \Mockery::mock(AddSourcesRequest::class),
                'jobStore' => (new MockJobStore())
                    ->withHasCall(false)
                    ->getMock(),
                'manifestFactory' => (new MockManifestFactory())->getMock(),
                'sourceRepository' => $emptySourceStore,
                'sourceFactory' => $emptySourceFactory,
                'expectedResponse' => BadAddSourcesRequestResponse::createJobMissingResponse(),
            ],
            'job has sources' => [
                'addSourcesRequest' => \Mockery::mock(AddSourcesRequest::class),
                'jobStore' => (new MockJobStore())
                    ->withHasCall(true)
                    ->withGetCall(
                        (new MockJob())
                            ->getMock()
                    )
                    ->getMock(),
                'manifestFactory' => (new MockManifestFactory())->getMock(),
                'sourceRepository' => (new MockSourceStore())
                    ->withHasAnyCall(true)
                    ->getMock(),
                'sourceFactory' => $emptySourceFactory,
                'expectedResponse' => BadAddSourcesRequestResponse::createSourcesNotEmptyResponse(),
            ],
            'request manifest missing' => [
                'addSourcesRequest' => (new MockAddSourcesRequest())
                    ->withGetManifestCall(null)
                    ->getMock(),
                'jobStore' => (new MockJobStore())
                    ->withHasCall(true)
                    ->withGetCall($job)
                    ->getMock(),
                'manifestFactory' => (new MockManifestFactory())->getMock(),
                'sourceRepository' => $emptySourceStore,
                'sourceFactory' => $emptySourceFactory,
                'expectedResponse' => BadAddSourcesRequestResponse::createManifestMissingResponse(),
            ],
            'request manifest has invalid mime type' => [
                'addSourcesRequest' => (new MockAddSourcesRequest())
                    ->withGetManifestCall(
                        $manifestUploadedFile
                    )
                    ->getMock(),
                'jobStore' => (new MockJobStore())
                    ->withHasCall(true)
                    ->withGetCall($job)
                    ->getMock(),
                'manifestFactory' => (new MockManifestFactory())
                    ->withCreateFromUploadedFileCallThrowingException(
                        $manifestUploadedFile,
                        new InvalidMimeTypeException('invalid/mime-type')
                    )
                    ->getMock(),
                'sourceRepository' => $emptySourceStore,
                'sourceFactory' => $emptySourceFactory,
                'expectedResponse' => BadAddSourcesRequestResponse::createInvalidRequestManifest(
                    new InvalidMimeTypeException('invalid/mime-type')
                ),
            ],
            'request manifest empty' => [
                'addSourcesRequest' => (new MockAddSourcesRequest())
                    ->withGetManifestCall(
                        $manifestUploadedFile
                    )
                    ->getMock(),
                'jobStore' => (new MockJobStore())
                    ->withHasCall(true)
                    ->withGetCall($job)
                    ->getMock(),
                'manifestFactory' => (new MockManifestFactory())
                    ->withCreateFromUploadedFileCall($manifestUploadedFile, $emptyManifest)
                    ->getMock(),
                'sourceRepository' => $emptySourceStore,
                'sourceFactory' => $emptySourceFactory,
                'expectedResponse' => BadAddSourcesRequestResponse::createManifestEmptyResponse(),
            ],
            'request source missing' => [
                'addSourcesRequest' => (new MockAddSourcesRequest())
                    ->withGetManifestCall($manifestUploadedFile)
                    ->withGetUploadedSourcesCall($uploadedSources)
                    ->getMock(),
                'jobStore' => (new MockJobStore())
                    ->withHasCall(true)
                    ->withGetCall($job)
                    ->getMock(),
                'manifestFactory' => (new MockManifestFactory())
                    ->withCreateFromUploadedFileCall($manifestUploadedFile, $nonEmptyManifest)
                    ->getMock(),
                'sourceRepository' => $emptySourceStore,
                'sourceFactory' => (new MockSourceFactory())
                    ->withCreateCollectionFromManifestCallThrowingException(
                        $nonEmptyManifest,
                        $uploadedSources,
                        new MissingTestSourceException('Test/test1.yml')
                    )
                    ->getMock(),
                'expectedResponse' => BadAddSourcesRequestResponse::createSourceMissingResponse('Test/test1.yml'),
            ],
        ];
    }
}
