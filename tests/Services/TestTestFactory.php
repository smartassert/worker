<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\Test;
use App\Services\TestFactory;
use App\Tests\Model\TestSetup;
use Doctrine\ORM\EntityManagerInterface;
use webignition\ObjectReflector\ObjectReflector;

class TestTestFactory
{
    public function __construct(
        private TestFactory $testFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(TestSetup $testSetup): Test
    {
        $test = $this->testFactory->create(
            $testSetup->getBrowser(),
            $testSetup->getUrl(),
            $testSetup->getSource(),
            $testSetup->getTarget(),
            $testSetup->getStepNames()
        );

        $test->setState($testSetup->getState());

        $position = $testSetup->getPosition();
        if (is_int($position)) {
            ObjectReflector::setProperty($test, $test::class, 'position', $position);
        }

        $this->entityManager->flush();

        return $test;
    }
}
