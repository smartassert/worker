<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EntityFactory;

use App\Entity\TestConfiguration;
use App\Repository\TestRepository;
use App\Services\EntityFactory\TestFactory;
use App\Tests\AbstractBaseFunctionalTest;

class TestFactoryTest extends AbstractBaseFunctionalTest
{
    private TestFactory $factory;
    private TestRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(TestFactory::class);
        \assert($factory instanceof TestFactory);
        $this->factory = $factory;

        $repository = self::getContainer()->get(TestRepository::class);
        \assert($repository instanceof TestRepository);
        $this->repository = $repository;
    }

    public function testCreate(): void
    {
        $this->assertSame(0, $this->repository->count([]));

        $test0 = $this->factory->create(
            TestConfiguration::create('chrome', 'http://example.com'),
            '/app/source/Test/test1.yml',
            '/app/tests/GeneratedTest1.php',
            1
        );
        self::assertSame(1, $test0->getPosition());
        $this->assertSame(1, $this->repository->count([]));

        $test1 = $this->factory->create(
            TestConfiguration::create('firefox', 'http://example.com'),
            '/app/source/Test/test1.yml',
            '/app/tests/GeneratedTest2.php',
            1
        );
        self::assertSame(2, $test1->getPosition());
        $this->assertSame(2, $this->repository->count([]));

        $test2 = $this->factory->create(
            TestConfiguration::create('chrome', 'http://example.com'),
            '/app/source/Test/test2.yml',
            '/app/tests/GeneratedTest3.php',
            1
        );
        self::assertSame(3, $test2->getPosition());
        $this->assertSame(3, $this->repository->count([]));
    }
}
