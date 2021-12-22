<?php

declare(strict_types=1);

namespace App\Tests\Integration\Services;

use App\Tests\Integration\AbstractBaseIntegrationTest;
use webignition\TcpCliProxyClient\Client;

abstract class AbstractTestCreationTest extends AbstractBaseIntegrationTest
{
    protected string $compilerTargetDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $compilerTargetDirectory = self::$container->getParameter('compiler_target_directory');
        if (is_string($compilerTargetDirectory)) {
            $this->compilerTargetDirectory = $compilerTargetDirectory;
        }
    }

    protected function tearDown(): void
    {
        $compilerClient = self::getContainer()->get('app.services.compiler-client');
        self::assertInstanceOf(Client::class, $compilerClient);

        $request = 'rm ' . $this->compilerTargetDirectory . '/*.php';
        $compilerClient->request($request);

        parent::tearDown();
    }
}
