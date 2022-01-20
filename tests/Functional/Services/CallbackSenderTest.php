<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Exception\NonSuccessfulHttpResponseException;
use App\Services\CallbackSender;
use App\Services\EntityFactory\JobFactory;
use App\Tests\AbstractBaseFunctionalTest;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class CallbackSenderTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private CallbackSender $callbackSender;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $callbackSender = self::getContainer()->get(CallbackSender::class);
        \assert($callbackSender instanceof CallbackSender);
        $this->callbackSender = $callbackSender;

        $mockHandler = self::getContainer()->get('app.tests.services.guzzle.handler.queuing');
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;
    }

    public function testSendSuccess(): void
    {
        $callback = CallbackEntity::create(CallbackInterface::TYPE_JOB_STARTED, []);
        $this->mockHandler->append(new Response(200));
        $this->createJob();

        try {
            $this->callbackSender->send($callback);
            $this->expectNotToPerformAssertions();
        } catch (NonSuccessfulHttpResponseException | ClientExceptionInterface $e) {
            $this->fail($e::class);
        }
    }

    /**
     * @dataProvider sendNonSuccessfulResponseDataProvider
     */
    public function testSendNonSuccessfulResponse(ResponseInterface $response): void
    {
        $callback = CallbackEntity::create(CallbackInterface::TYPE_JOB_STARTED, []);
        $this->mockHandler->append($response);
        $this->createJob();

        $this->expectExceptionObject(new NonSuccessfulHttpResponseException($callback, $response));

        $this->callbackSender->send($callback);
    }

    /**
     * @return array<mixed>
     */
    public function sendNonSuccessfulResponseDataProvider(): array
    {
        $dataSets = [];

        for ($statusCode = 300; $statusCode < 600; ++$statusCode) {
            $dataSets[(string) $statusCode] = [
                'response' => new Response($statusCode),
            ];
        }

        return $dataSets;
    }

    /**
     * @dataProvider sendClientExceptionDataProvider
     */
    public function testSendClientException(\Exception $exception): void
    {
        $callback = CallbackEntity::create(CallbackInterface::TYPE_JOB_STARTED, []);
        $this->mockHandler->append($exception);
        $this->createJob();

        $this->expectExceptionObject($exception);

        $this->callbackSender->send($callback);
    }

    /**
     * @return array<mixed>
     */
    public function sendClientExceptionDataProvider(): array
    {
        return [
            'Guzzle ConnectException' => [
                'exception' => \Mockery::mock(ConnectException::class),
            ],
        ];
    }

    private function createJob(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $jobFactory->create('label content', 'http://example.com/callback', 10);
    }
}
