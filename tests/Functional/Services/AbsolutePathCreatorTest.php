<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Services\AbsolutePathCreator;
use App\Tests\AbstractBaseFunctionalTest;

class AbsolutePathCreatorTest extends AbstractBaseFunctionalTest
{
    public function testSourceAbsolutePathCreator(): void
    {
        $service = self::getContainer()->get('app.services.absolute_path_creator.source');
        self::assertInstanceOf(AbsolutePathCreator::class, $service);

        $prefix = self::getContainer()->getParameter('compiler_source_directory');
        self::assertIsString($prefix);

        $relativePath = 'Test/test.yml';

        self::assertSame($prefix . '/' . $relativePath, $service->create($relativePath));
    }

    public function testTargetAbsolutePathCreator(): void
    {
        $service = self::getContainer()->get('app.services.absolute_path_creator.target');
        self::assertInstanceOf(AbsolutePathCreator::class, $service);

        $prefix = self::getContainer()->getParameter('compiler_target_directory');
        self::assertIsString($prefix);

        $relativePath = 'GeneratedTest.php';

        self::assertSame($prefix . '/' . $relativePath, $service->create($relativePath));
    }
}
