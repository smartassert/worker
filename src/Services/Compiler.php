<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser as YamlParser;
use webignition\BasilCompilerModels\Exception\InvalidTestManifestException;
use webignition\BasilCompilerModels\Factory\ErrorOutputFactory;
use webignition\BasilCompilerModels\Factory\TestManifestCollectionFactory;
use webignition\BasilCompilerModels\Model\ErrorOutputInterface;
use webignition\BasilCompilerModels\Model\TestManifestCollection;
use webignition\TcpCliProxyClient\Client;
use webignition\TcpCliProxyClient\Exception\ClientCreationException;
use webignition\TcpCliProxyClient\Exception\SocketErrorException;
use webignition\TcpCliProxyClient\HandlerFactory;

class Compiler
{
    public function __construct(
        private Client $client,
        private string $compilerSourceDirectory,
        private string $compilerTargetDirectory,
        private YamlParser $yamlParser,
        private HandlerFactory $handlerFactory,
        private readonly ErrorOutputFactory $errorOutputFactory,
        private readonly TestManifestCollectionFactory $testManifestCollectionFactory,
    ) {}

    /**
     * @throws ClientCreationException
     * @throws SocketErrorException
     * @throws ParseException
     * @throws InvalidTestManifestException
     */
    public function compile(string $source): ErrorOutputInterface|TestManifestCollection
    {
        $output = '';
        $exitCode = null;

        $handler = $this->handlerFactory->createWithScalarOutput($output, $exitCode);

        $this->client->request(
            sprintf(
                './compiler --source=%s --target=%s',
                $this->compilerSourceDirectory . '/' . $source,
                $this->compilerTargetDirectory
            ),
            $handler
        );

        $outputData = $this->yamlParser->parse($output);
        $outputData = is_array($outputData) ? $outputData : [];

        return 0 === $exitCode
            ? $this->testManifestCollectionFactory->create($outputData)
            : $this->errorOutputFactory->create($outputData);
    }
}
