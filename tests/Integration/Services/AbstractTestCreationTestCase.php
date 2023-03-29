<?php

declare(strict_types=1);

namespace App\Tests\Integration\Services;

use App\Tests\Integration\AbstractBaseIntegrationTestCase;
use webignition\TcpCliProxyClient\Client;

abstract class AbstractTestCreationTestCase extends AbstractBaseIntegrationTestCase
{
    protected string $compilerTargetDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $compilerTargetDirectory = self::getContainer()->getParameter('compiler_target_directory');
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
