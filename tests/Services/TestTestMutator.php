<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\Test;
use App\Enum\TestState;
use Doctrine\ORM\EntityManagerInterface;

class TestTestMutator
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function setState(Test $test, TestState $state): Test
    {
        $test->setState($state);
        $this->entityManager->persist($test);
        $this->entityManager->flush();

        return $test;
    }
}
