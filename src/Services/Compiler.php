<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Yaml\Parser as YamlParser;
use webignition\BasilCompilerModels\ErrorOutput;
use webignition\BasilCompilerModels\ErrorOutputInterface;
use webignition\BasilCompilerModels\SuiteManifest;
use webignition\TcpCliProxyClient\Client;
use webignition\TcpCliProxyClient\HandlerFactory;

class Compiler
{
    public function __construct(
        private Client $client,
        private string $compilerSourceDirectory,
        private string $compilerTargetDirectory,
        private YamlParser $yamlParser,
        private HandlerFactory $handlerFactory
    ) {
    }

    public function compile(string $source): ErrorOutputInterface | SuiteManifest
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

        return 0 === $exitCode
            ? SuiteManifest::fromArray($outputData)
            : ErrorOutput::fromArray($outputData);
    }
}
