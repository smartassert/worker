<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use App\Event\SourceCompilationPassedEvent;
use App\Repository\TestRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use webignition\BasilCompilerModels\TestManifest;

class TestFactory implements EventSubscriberInterface
{
    public function __construct(
        private readonly TestRepository $repository,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SourceCompilationPassedEvent::class => [
                ['createFromSourceCompileSuccessEvent', 100],
            ],
        ];
    }

    /**
     * @return Test[]
     */
    public function createFromSourceCompileSuccessEvent(SourceCompilationPassedEvent $event): array
    {
        return $this->createFromManifestCollection($event->getSuiteManifest()->getTestManifests());
    }

    /**
     * @param TestManifest[] $manifests
     *
     * @return Test[]
     */
    public function createFromManifestCollection(array $manifests): array
    {
        $tests = [];

        foreach ($manifests as $manifest) {
            if ($manifest instanceof TestManifest) {
                $tests[] = $this->createFromManifest($manifest);
            }
        }

        return $tests;
    }

    /**
     * @param non-empty-string[] $stepNames
     */
    public function create(
        string $browser,
        string $url,
        string $source,
        string $target,
        array $stepNames
    ): Test {
        return $this->repository->add(new Test(
            $browser,
            $url,
            $source,
            $target,
            $stepNames,
            $this->repository->findMaxPosition() + 1
        ));
    }

    private function createFromManifest(TestManifest $manifest): Test
    {
        $manifestConfiguration = $manifest->getConfiguration();

        return $this->create(
            $manifestConfiguration->getBrowser(),
            $manifestConfiguration->getUrl(),
            $manifest->getSource(),
            $manifest->getTarget(),
            $manifest->getStepNames()
        );
    }
}
