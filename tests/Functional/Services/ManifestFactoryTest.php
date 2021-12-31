<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Exception\Manifest\InvalidMimeTypeException;
use App\Services\ManifestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockUploadedFile;

class ManifestFactoryTest extends AbstractBaseFunctionalTest
{
    private ManifestFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(ManifestFactory::class);
        \assert($factory instanceof ManifestFactory);
        $this->factory = $factory;
    }

    public function testCreateFromUploadedFileThrowsException(): void
    {
        $mimeType = 'invalid/mime-type';

        $uploadedFile = (new MockUploadedFile())
            ->withGetClientMimeTypeCall($mimeType)
            ->getMock()
        ;

        $this->expectExceptionObject(new InvalidMimeTypeException($mimeType));

        $this->factory->createFromUploadedFile($uploadedFile);
    }
}
