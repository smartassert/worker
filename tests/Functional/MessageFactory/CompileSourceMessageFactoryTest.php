<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageFactory;

use App\Entity\Job;
use App\MessageFactory\CompileSourceMessageFactory;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CompileSourceMessageFactoryTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    private CompileSourceMessageFactory $factory;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(CompileSourceMessageFactory::class);
        \assert($factory instanceof CompileSourceMessageFactory);
        $this->factory = $factory;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }
    }

    public function testCreateNoJob(): void
    {
        $message = $this->factory->create('test.yml');

        self::assertSame(600, $message->timeoutInSeconds);
    }

    public function testCreateHasJob(): void
    {
        $maximumDurationInSeconds = rand(0, 1000);

        $this->environmentFactory->create(
            new EnvironmentSetup()
                ->withJobSetup(
                    new JobSetup()
                        ->withMaximumDurationInSeconds($maximumDurationInSeconds)
                ),
        );

        $message = $this->factory->create('test.yml');

        self::assertSame($maximumDurationInSeconds, $message->timeoutInSeconds);
    }
}
