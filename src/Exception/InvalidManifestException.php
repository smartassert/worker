<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Yaml\Exception\ParseException;

class InvalidManifestException extends \Exception
{
    public const CODE_INVALID_YAML = 100;
    public const CODE_INVALID_DATA = 200;
    public const CODE_EMPTY = 300;

    public function __construct(
        public readonly string $content,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function createForInvalidYaml(string $content, ParseException $parseException): self
    {
        return new InvalidManifestException(
            $content,
            'Manifest content is not valid yaml',
            self::CODE_INVALID_YAML,
            $parseException
        );
    }

    public static function createForInvalidData(string $content): self
    {
        return new InvalidManifestException(
            $content,
            'Manifest is not a list of strings',
            self::CODE_INVALID_DATA
        );
    }

    public static function createForEmptyContent(string $content): self
    {
        return new InvalidManifestException(
            $content,
            'Manifest is empty',
            self::CODE_EMPTY
        );
    }
}
