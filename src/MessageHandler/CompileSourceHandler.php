<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\CompilationState;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Message\CompileSourceMessage;
use App\Services\CompilationProgress;
use App\Services\Compiler;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use webignition\BasilCompilerModels\ErrorOutputInterface;

class CompileSourceHandler implements MessageHandlerInterface
{
    public function __construct(
        private Compiler $compiler,
        private CompilationProgress $compilationProgress,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(CompileSourceMessage $message): void
    {
        if (false === $this->compilationProgress->is(CompilationState::RUNNING)) {
            return;
        }

        $sourcePath = $message->path;

        $this->eventDispatcher->dispatch(new SourceCompilationStartedEvent($sourcePath));

        $output = $this->compiler->compile($sourcePath);

        $event = $output instanceof ErrorOutputInterface
            ? new SourceCompilationFailedEvent($sourcePath, $output)
            : new SourceCompilationPassedEvent($sourcePath, $output);

        $this->eventDispatcher->dispatch($event);
    }
}
