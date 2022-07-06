<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;

class TestSerializer
{
    public function __construct(
        private readonly TestPathNormalizer $testPathNormalizer,
    ) {
    }

    /**
     * @param Test[] $tests
     *
     * @return array<int, array<mixed>>
     */
    public function serializeCollection(array $tests): array
    {
        $serializedTests = [];

        foreach ($tests as $test) {
            if ($test instanceof Test) {
                $serializedTests[] = $this->serialize($test);
            }
        }

        return $serializedTests;
    }

    /**
     * @return array<mixed>
     */
    public function serialize(Test $test): array
    {
        return [
            'browser' => $test->getBrowser(),
            'url' => $test->getUrl(),
            'source' => $this->testPathNormalizer->normalize($test->getSource()),
            'target' => $this->testPathNormalizer->normalize($test->getTarget()),
            'step_names' => $test->getStepNames(),
            'state' => $test->getState()->value,
            'position' => $test->getPosition(),
        ];
    }
}
