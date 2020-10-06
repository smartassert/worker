<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Yaml\Parser as YamlParser;
use webignition\BasilCompilerModels\ErrorOutput;
use webignition\BasilCompilerModels\OutputInterface;
use webignition\BasilCompilerModels\SuiteManifest;
use webignition\TcpCliProxyClient\Client;
use webignition\TcpCliProxyClient\HandlerFactory;

class Compiler
{
    private Client $client;
    private string $compilerSourceDirectory;
    private string $compilerTargetDirectory;
    private YamlParser $yamlParser;
    private HandlerFactory $handlerFactory;

    public function __construct(
        Client $client,
        string $compilerSourceDirectory,
        string $compilerTargetDirectory,
        YamlParser $yamlParser,
        HandlerFactory $handlerFactory
    ) {
        $this->client = $client;
        $this->compilerTargetDirectory = $compilerTargetDirectory;
        $this->compilerSourceDirectory = $compilerSourceDirectory;
        $this->yamlParser = $yamlParser;
        $this->handlerFactory = $handlerFactory;
    }

    public function compile(string $source): OutputInterface
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

        if (0 === $exitCode) {
            return SuiteManifest::fromArray($outputData);
        }

        return ErrorOutput::fromArray($outputData);
    }
}
