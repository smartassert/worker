<?php

declare(strict_types=1);

namespace App\Services;

use App\Event\SourceCompilationFailedEvent;
use webignition\BasilCompilerModels\Model\ErrorOutputInterface;

class SourceCompilationFailedEventFactory
{
    /**
     * @param non-empty-string $sourcePath
     */
    public function create(string $sourcePath, ErrorOutputInterface $output): SourceCompilationFailedEvent
    {
        return new SourceCompilationFailedEvent($sourcePath, $output->toArray());
    }
}
