<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\SourceCompilation\SourceCompilationFailedEvent;
use App\Event\SourceCompilation\SourceCompilationPassedEvent;
use App\Event\SourceCompilation\SourceCompilationStartedEvent;
use App\Message\CompileSourceMessage;
use App\Repository\JobRepository;
use App\Services\CompilationState;
use App\Services\Compiler;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use webignition\BasilCompilerModels\ErrorOutputInterface;

class CompileSourceHandler implements MessageHandlerInterface
{
    public function __construct(
        private Compiler $compiler,
        private readonly JobRepository $jobRepository,
        private CompilationState $compilationState,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(CompileSourceMessage $message): void
    {
        if (null === $this->jobRepository->get()) {
            return;
        }

        if (false === $this->compilationState->is(CompilationState::STATE_RUNNING)) {
            return;
        }

        $sourcePath = $message->getPath();

        $this->eventDispatcher->dispatch(new SourceCompilationStartedEvent($sourcePath));

        $output = $this->compiler->compile($sourcePath);

        $event = $output instanceof ErrorOutputInterface
            ? new SourceCompilationFailedEvent($sourcePath, $output)
            : new SourceCompilationPassedEvent($sourcePath, $output);

        $this->eventDispatcher->dispatch($event);
    }
}
