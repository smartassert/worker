<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Repository\JobRepository;
use App\Services\CallbackFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\DataProvider\CallbackFactory\CreateFromCompilationFailedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromCompilationPassedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromCompilationStartedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromExecutionCompletedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromExecutionStartedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobCompiledEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobCompletedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobFailedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobReadyEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobTimeoutEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromStepEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromTestEventDataProviderTrait;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Contracts\EventDispatcher\Event;

class CallbackFactoryTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;
    use CreateFromCompilationStartedEventDataProviderTrait;
    use CreateFromCompilationPassedEventDataProviderTrait;
    use CreateFromCompilationFailedEventDataProviderTrait;
    use CreateFromTestEventDataProviderTrait;
    use CreateFromJobTimeoutEventDataProviderTrait;
    use CreateFromJobCompletedEventDataProviderTrait;
    use CreateFromJobReadyEventDataProviderTrait;
    use CreateFromJobCompiledEventDataProviderTrait;
    use CreateFromExecutionStartedEventDataProviderTrait;
    use CreateFromExecutionCompletedEventDataProviderTrait;
    use CreateFromJobFailedEventDataProviderTrait;
    use CreateFromStepEventDataProviderTrait;

    private CallbackFactory $callbackFactory;
    private EnvironmentFactory $environmentFactory;
    private JobRepository $jobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $callbackFactory = self::getContainer()->get(CallbackFactory::class);
        \assert($callbackFactory instanceof CallbackFactory);
        $this->callbackFactory = $callbackFactory;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $this->jobRepository = $jobRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
//            $entityRemover->removeForEntity(CallbackEntity::class);
//            $entityRemover->removeForEntity(Source::class);
//            $entityRemover->removeForEntity(Test::class);
            $entityRemover->removeForEntity(Job::class);
        }
    }

    public function testCreateReturnsNullIfNoJob(): void
    {
        self::assertNull($this->jobRepository->get());
        self::assertNull($this->callbackFactory->createForEvent(new Event()));
    }

    public function testCreateForEventUnsupportedEvent(): void
    {
        $this->environmentFactory->create((new EnvironmentSetup())->withJobSetup(new JobSetup()));

        self::assertNotNull($this->jobRepository->get());
        self::assertNull($this->callbackFactory->createForEvent(new Event()));
    }

    /**
     * @dataProvider createFromCompilationStartedEventDataProvider
     * @dataProvider createFromCompilationPassedEventDataProvider
     * @dataProvider createFromCompilationFailedEventDataProvider
     * @dataProvider createFromJobCompiledEventDataProvider
     * @dataProvider createFromExecutionStartedEventDataProvider
     * @dataProvider createFromTestEventEventDataProvider
     * @dataProvider createFromStepEventDataProvider
     * @dataProvider createFromJobTimeoutEventDataProvider
     * @dataProvider createFromJobCompletedEventDataProvider
     * @dataProvider createFromJobReadyEventDataProvider
     * @dataProvider createFromExecutionCompletedEventDataProvider
     * @dataProvider createFromJobFailedEventDataProvider
     */
    public function testCreateForEventFoo(Event $event, CallbackInterface $expectedCallback): void
    {
        $this->environmentFactory->create((new EnvironmentSetup())->withJobSetup(new JobSetup()));
        self::assertNotNull($this->jobRepository->get());

        $callback = $this->callbackFactory->createForEvent($event);

        self::assertInstanceOf(CallbackInterface::class, $callback);
        self::assertNotNull($callback->getId());
        self::assertSame($expectedCallback->getType(), $callback->getType());
        self::assertSame($expectedCallback->getPayload(), $callback->getPayload());
    }
}
