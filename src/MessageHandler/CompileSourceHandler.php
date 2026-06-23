<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\CompilationState;
use App\Event\EmittableEvent\CompilationPassedEvent;
use App\Event\EmittableEvent\CompilationStartedEvent;
use App\Event\EmittableEvent\CompilationTimedOutEvent;
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
use webignition\TcpCliProxyClient\Exception\SocketTimedOutException;

#[AsMessageHandler]
class CompileSourceHandler
{
    public function __construct(
        private Compiler $compiler,
        private CompilationProgress $compilationProgress,
        private EventDispatcherInterface $eventDispatcher,
        private readonly SourceCompilationFailedEventFactory $sourceCompilationFailedEventFactory,
    ) {}

    /**
     * @throws ClientCreationException
     * @throws SocketErrorException
     * @throws ParseException
     * @throws InvalidTestManifestException
     * @throws \ErrorException
     */
    public function __invoke(CompileSourceMessage $message): void
    {
        if (CompilationState::RUNNING !== $this->compilationProgress->get()) {
            return;
        }

        $sourcePath = $message->path;
        $this->eventDispatcher->dispatch(new CompilationStartedEvent($sourcePath));

        try {
            $output = $this->compiler->compile($sourcePath, $message->timeoutInSeconds);

            $event = $output instanceof ErrorOutputInterface
                ? $this->sourceCompilationFailedEventFactory->create($sourcePath, $output)
                : new CompilationPassedEvent($sourcePath, $output);
        } catch (SocketTimedOutException) {
            $event = new CompilationTimedOutEvent($sourcePath, $message->timeoutInSeconds);
        }

        $this->eventDispatcher->dispatch($event);
    }
}
