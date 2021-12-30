<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Callback\CallbackInterface;
use App\Model\SendCallbackResult;
use App\Services\CallbackSender;
use App\Services\EntityFactory\JobFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\TestCallback;
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

    /**
     * @dataProvider sendResponseSuccessDataProvider
     */
    public function testSendResponseSuccess(ClientExceptionInterface | ResponseInterface $httpFixture): void
    {
        $callback = new TestCallback();
        $callback = $callback->withState(CallbackInterface::STATE_SENDING);

        $this->mockHandler->append($httpFixture);

        $this->createJob();

        $this->mockHandler->append($httpFixture);
        $result = $this->callbackSender->send($callback);

        self::assertEquals(new SendCallbackResult($callback, $httpFixture), $result);
        self::assertSame(CallbackInterface::STATE_SENDING, $callback->getState());
    }

    /**
     * @return array[]
     */
    public function sendResponseSuccessDataProvider(): array
    {
        $dataSets = [
            'HTTP 400' => [
                'response' => new Response(400),
            ],
            'Guzzle ConnectException' => [
                'exception' => \Mockery::mock(ConnectException::class),
            ],
        ];

        for ($statusCode = 100; $statusCode < 300; ++$statusCode) {
            $dataSets[(string) $statusCode] = [
                'httpFixture' => new Response($statusCode),
            ];
        }

        return $dataSets;
    }

    public function testSendNoJob(): void
    {
        self::assertNull($this->callbackSender->send(new TestCallback()));
    }

    private function createJob(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $jobFactory->create('label content', 'http://example.com/callback', 10);
    }
}
