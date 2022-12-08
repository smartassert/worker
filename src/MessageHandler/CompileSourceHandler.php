<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\CompilationState;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Message\CompileSourceMessage;
use App\Services\CompilationProgress;
use App\Services\Compiler;
use App\Services\SourceCompilationFailedEventFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Yaml\Exception\ParseException;
use webignition\BasilCompilerModels\Exception\InvalidTestManifestException;
use webignition\BasilCompilerModels\Model\ErrorOutputInterface;
use webignition\TcpCliProxyClient\Exception\ClientCreationException;
use webignition\TcpCliProxyClient\Exception\SocketErrorException;

#[AsMessageHandler]
class CompileSourceHandler
{
    public function __construct(
        private Compiler $compiler,
        private CompilationProgress $compilationProgress,
        private EventDispatcherInterface $eventDispatcher,
        private readonly SourceCompilationFailedEventFactory $sourceCompilationFailedEventFactory,
    ) {
    }

    /**
     * @throws ClientCreationException
     * @throws SocketErrorException
     * @throws ParseException
     * @throws InvalidTestManifestException
     */
    public function __invoke(CompileSourceMessage $message): void
    {
        if (false === $this->compilationProgress->is([CompilationState::RUNNING])) {
            return;
        }

        $sourcePath = $message->path;
        $this->eventDispatcher->dispatch(new SourceCompilationStartedEvent($sourcePath));

        $output = $this->compiler->compile($sourcePath);

        $event = $output instanceof ErrorOutputInterface
            ? $this->sourceCompilationFailedEventFactory->create($sourcePath, $output)
            : new SourceCompilationPassedEvent($sourcePath, $output);

        $this->eventDispatcher->dispatch($event);
    }
}
