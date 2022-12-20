<?php

declare(strict_types=1);

namespace App\Services;

use App\Event\EmittableEvent\SourceCompilationFailedEvent;
use webignition\BasilCompilerModels\Model\ErrorOutputInterface;

class SourceCompilationFailedEventFactory
{
    public function __construct(
        private readonly string $compilerSourceDirectory,
    ) {
    }

    /**
     * @param non-empty-string $sourcePath
     */
    public function create(string $sourcePath, ErrorOutputInterface $output): SourceCompilationFailedEvent
    {
        $payloadOutput = $output->toArray();

        if (array_key_exists('message', $payloadOutput)) {
            $sourceAbsolutePath = rtrim($this->compilerSourceDirectory, '/') . '/' . $sourcePath;

            $payloadOutput['message'] = str_replace($sourceAbsolutePath, $sourcePath, $payloadOutput['message']);
        }

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
