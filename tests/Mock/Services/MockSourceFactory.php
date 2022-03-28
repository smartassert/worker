<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Services\SourceFactory;
use Mockery\MockInterface;

class MockSourceFactory
{
    /**
     * @var MockInterface|SourceFactory
     */
    private SourceFactory $sourceFactory;

    public function __construct()
    {
        $this->sourceFactory = \Mockery::mock(SourceFactory::class);
    }

    public function getMock(): SourceFactory
    {
        return $this->sourceFactory;
    }
}
