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
        $this->entityManager->persist($test);
        $this->entityManager->flush();

        $position = $testSetup->getPosition();
        if (is_int($position) && $position !== $test->position) {
            $mutatedTest = $this->createTestWithPosition($test, $position);

            $this->entityManager->remove($test);
            $this->entityManager->persist($mutatedTest);
            $this->entityManager->flush();

            $test = $mutatedTest;
        }

        return $test;
    }

    private function createTestWithPosition(Test $test, int $position): Test
    {
        $reflectionClass = new \ReflectionClass($test);
        $reflectionTest = $reflectionClass->newInstanceWithoutConstructor();
        \assert($reflectionTest instanceof Test);

        $positionProperty = $reflectionClass->getProperty('position');
        $positionProperty->setValue(
            $reflectionTest,
            $position
        );

        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($reflectionTest, ObjectReflector::getProperty($test, 'id'));

        $browserProperty = $reflectionClass->getProperty('browser');
        $browserProperty->setValue($reflectionTest, $test->browser);

        $urlProperty = $reflectionClass->getProperty('url');
        $urlProperty->setValue($reflectionTest, $test->url);

        $sourceProperty = $reflectionClass->getProperty('source');
        $sourceProperty->setValue($reflectionTest, $test->getSource());

        $targetProperty = $reflectionClass->getProperty('target');
        $targetProperty->setValue($reflectionTest, $test->target);

        $stepNamesProperty = $reflectionClass->getProperty('stepNames');
        $stepNamesProperty->setValue($reflectionTest, $test->stepNames);

        $stateProperty = $reflectionClass->getProperty('state');
        $stateProperty->setValue($reflectionTest, $test->getState());

        return $reflectionTest;
    }
}
