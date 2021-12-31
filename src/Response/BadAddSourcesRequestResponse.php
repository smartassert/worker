<?php

declare(strict_types=1);

namespace App\Response;

use App\Exception\Manifest\ManifestFactoryExceptionInterface;

class BadAddSourcesRequestResponse extends ErrorResponse
{
    private const TYPE = 'add-sources';

    private const CODE_JOB_MISSING = 100;
    private const CODE_MANIFEST_MISSING = 200;
    private const CODE_MANIFEST_EMPTY = 300;
    private const CODE_SOURCE_MISSING = 400;
    private const CODE_JOB_SOURCES_NOT_EMPTY = 500;
    private const CODE_INVALID_REQUEST_MANIFEST = 600;

    private const MESSAGE_JOB_MISSING = 'job missing';
    private const MESSAGE_MANIFEST_MISSING = 'manifest missing';
    private const MESSAGE_MANIFEST_EMPTY = 'manifest empty';
    private const MESSAGE_SOURCE_MISSING = 'source "%s" missing';
    private const MESSAGE_JOB_SOURCES_NOT_EMPTY = 'job sources not empty';
    private const MESSAGE_INVALID_REQUEST_MANIFEST = 'invalid request manifest - %s';

    public function __construct(string $message, int $code, int $status = self::HTTP_BAD_REQUEST)
    {
        parent::__construct(self::TYPE, $message, $code, $status);
    }

    public static function createJobMissingResponse(): self
    {
        return new BadAddSourcesRequestResponse(
            self::MESSAGE_JOB_MISSING,
            self::CODE_JOB_MISSING
        );
    }

    public static function createManifestMissingResponse(): self
    {
        return new BadAddSourcesRequestResponse(
            self::MESSAGE_MANIFEST_MISSING,
            self::CODE_MANIFEST_MISSING
        );
    }

    public static function createManifestEmptyResponse(): self
    {
        return new BadAddSourcesRequestResponse(
            self::MESSAGE_MANIFEST_EMPTY,
            self::CODE_MANIFEST_EMPTY
        );
    }

    public static function createSourceMissingResponse(string $source): self
    {
        return new BadAddSourcesRequestResponse(
            sprintf(self::MESSAGE_SOURCE_MISSING, $source),
            self::CODE_SOURCE_MISSING
        );
    }

    public static function createSourcesNotEmptyResponse(): self
    {
        return new BadAddSourcesRequestResponse(
            self::MESSAGE_JOB_SOURCES_NOT_EMPTY,
            self::CODE_JOB_SOURCES_NOT_EMPTY
        );
    }

    public static function createInvalidRequestManifest(ManifestFactoryExceptionInterface $exception): self
    {
        return new BadAddSourcesRequestResponse(
            sprintf(self::MESSAGE_INVALID_REQUEST_MANIFEST, (string) $exception),
            self::CODE_INVALID_REQUEST_MANIFEST
        );
    }
}
