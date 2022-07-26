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
        $payloadOutput = $output->toArray();

        if (array_key_exists('context', $payloadOutput)) {
            $context = $payloadOutput['context'];

            if (array_key_exists('test_path', $context)) {
                $context['test_path'] = $sourcePath;
            }

            $payloadOutput['context'] = $context;
        }

        return new SourceCompilationFailedEvent($sourcePath, $payloadOutput);
    }
}
