<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\Test;
use App\Repository\TestRepository;
use App\Tests\Model\TestSetup;
use Doctrine\ORM\EntityManagerInterface;

class TestTestFactory
{
    public function __construct(
        private TestRepository $testRepository,
        private EntityManagerInterface $entityManager,
        private string $compilerSourceDirectory,
        private string $compilerTargetDirectory,
    ) {
    }

    public function create(TestSetup $testSetup): Test
    {
        $source = $testSetup->getSource();
        $source = str_replace('{{ compiler_source_directory }}', $this->compilerSourceDirectory, $source);

        $target = $testSetup->getTarget();
        $target = str_replace('{{ compiler_target_directory }}', $this->compilerTargetDirectory, $target);

        $test = $this->testRepository->create(
            $testSetup->getConfiguration(),
            $source,
            $target,
            $testSetup->getStepCount()
        );

        $test->setState($testSetup->getState());

        $this->entityManager->flush();

        return $test;
    }
}
