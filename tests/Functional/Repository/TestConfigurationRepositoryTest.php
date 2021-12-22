<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\EntityInterface;
use App\Entity\TestConfiguration;
use App\Repository\TestConfigurationRepository;

/**
 * @extends AbstractEntityRepositoryTest<TestConfiguration>
 */
class TestConfigurationRepositoryTest extends AbstractEntityRepositoryTest
{
    public function findOneByDataProvider(): array
    {
        return [
            'browser chrome' => [
                'criteria' => [
                    'browser' => 'chrome',
                ],
                'orderBy' => null,
                'expectedEntityIndex' => 0,
            ],
            'browser firefox' => [
                'criteria' => [
                    'browser' => 'firefox',
                ],
                'orderBy' => null,
                'expectedEntityIndex' => 1,
            ],
            'url http://example.com/1' => [
                'criteria' => [
                    'url' => 'http://example.com/1',
                ],
                'orderBy' => null,
                'expectedEntityIndex' => 2,
            ],
            'browser firefox and url http://example.com/0' => [
                'criteria' => [
                    'browser' => 'firefox',
                    'url' => 'http://example.com/0',
                ],
                'orderBy' => null,
                'expectedEntityIndex' => 1,
            ],
        ];
    }

    public function countDataProvider(): array
    {
        return [
            'browser chrome' => [
                'criteria' => [
                    'browser' => 'chrome',
                ],
                'expectedCount' => 2,
            ],
            'browser firefox' => [
                'criteria' => [
                    'browser' => 'firefox',
                ],
                'expectedCount' => 1,
            ],
            'url http://example.com/1' => [
                'criteria' => [
                    'url' => 'http://example.com/1',
                ],
                'expectedCount' => 1,
            ],
            'browser firefox and url http://example.com/0' => [
                'criteria' => [
                    'browser' => 'firefox',
                    'url' => 'http://example.com/0',
                ],
                'expectedCount' => 1,
            ],
        ];
    }

    protected function getRepository(): ?TestConfigurationRepository
    {
        $repository = self::getContainer()->get(TestConfigurationRepository::class);
        if ($repository instanceof TestConfigurationRepository) {
            return $repository;
        }

        return null;
    }

    protected function createSingleEntity(): EntityInterface
    {
        return TestConfiguration::create('chrome', 'http://example.com');
    }

    protected function createEntityCollection(): array
    {
        return [
            TestConfiguration::create('chrome', 'http://example.com/0'),
            TestConfiguration::create('firefox', 'http://example.com/0'),
            TestConfiguration::create('chrome', 'http://example.com/1'),
        ];
    }
}
