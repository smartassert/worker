<?php

declare(strict_types=1);

namespace App\Tests\Model;

class JobSetup
{
    /**
     * @var non-empty-string
     */
    private string $label;

    /**
     * @var non-empty-string
     */
    private string $resultsToken;
    private int $maximumDurationInSeconds;

    /**
     * @var string[]
     */
    private array $localSourcePaths;

    /**
     * @var non-empty-string[]
     */
    private array $testPaths;

    public function __construct()
    {
        $this->label = md5('label content');
        $this->resultsToken = 'results-token';
        $this->maximumDurationInSeconds = 600;
        $this->localSourcePaths = [];
        $this->testPaths = ['test.yml'];
    }

    /**
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return non-empty-string
     */
    public function getResultsToken(): string
    {
        return $this->resultsToken;
    }

    public function getMaximumDurationInSeconds(): int
    {
        return $this->maximumDurationInSeconds;
    }

    /**
     * @return string[]
     */
    public function getLocalSourcePaths(): array
    {
        return $this->localSourcePaths;
    }

    /**
     * @return non-empty-string[]
     */
    public function getTestPaths(): array
    {
        return $this->testPaths;
    }

    /**
     * @param non-empty-string $label
     */
    public function withLabel(string $label): self
    {
        $new = clone $this;
        $new->label = $label;

        return $new;
    }

    /**
     * @param non-empty-string $token
     */
    public function withResultsToken(string $token): self
    {
        $new = clone $this;
        $new->resultsToken = $token;

        return $new;
    }

    /**
     * @param string[] $localSourcePaths
     */
    public function withLocalSourcePaths(array $localSourcePaths): self
    {
        $new = clone $this;
        $new->localSourcePaths = $localSourcePaths;

        return $new;
    }

    public function withMaximumDurationInSeconds(int $maximumDurationInSeconds): self
    {
        $new = clone $this;
        $new->maximumDurationInSeconds = $maximumDurationInSeconds;

        return $new;
    }

    /**
     * @param non-empty-string[] $testPaths
     */
    public function withTestPaths(array $testPaths): self
    {
        $new = clone $this;
        $new->testPaths = $testPaths;

        return $new;
    }
}
