<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use Symfony\Component\String\UnicodeString;

class TestSerializer
{
    public function __construct(
        private string $compilerSourceDirectory,
        private string $compilerTargetDirectory,
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
            'source' => (string) (new UnicodeString((string) $test->getSource()))->trimPrefix(
                $this->compilerSourceDirectory . '/'
            ),
            'target' => (string) (new UnicodeString((string) $test->getTarget()))->trimPrefix(
                $this->compilerTargetDirectory . '/'
            ),
            'step_names' => $test->getStepNames(),
            'state' => $test->getState()->value,
            'position' => $test->getPosition(),
        ];
    }
}
