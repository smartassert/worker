<?php

declare(strict_types=1);

namespace App\Services\EntityStore;

use App\Entity\TestConfiguration;
use App\Repository\TestConfigurationRepository;

class TestConfigurationStore
{
    public function __construct(
        private TestConfigurationRepository $repository,
    ) {
    }

    public function get(TestConfiguration $testConfiguration): TestConfiguration
    {
        $existingConfiguration = $this->repository->findOneByConfiguration($testConfiguration);
        if ($existingConfiguration instanceof TestConfiguration) {
            return $existingConfiguration;
        }

        return $this->repository->create(
            $testConfiguration->getBrowser(),
            $testConfiguration->getUrl()
        );
    }
}
