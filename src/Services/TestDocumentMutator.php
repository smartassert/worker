<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Document\Test;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Yaml\Dumper;
use webignition\YamlDocument\Document;

class TestDocumentMutator
{
    public function __construct(
        private Dumper $yamlDumper,
        private string $compilerSourceDirectory,
    ) {
    }

    public function removeCompilerSourceDirectoryFromSource(Document $document): Document
    {
        $test = new Test($document);
        if ($test->isTest()) {
            $path = $test->getPath();

            $mutatedPath = (string) (new UnicodeString($path))->trimPrefix($this->compilerSourceDirectory . '/');
            if ($mutatedPath !== $path) {
                $mutatedPath = ltrim($mutatedPath, '/');
            }

            $mutatedTestSource = $this->yamlDumper->dump($test->getMutatedData([
                Test::KEY_PATH => $mutatedPath,
            ]));

            $document = new Document($mutatedTestSource);
        }

        return $document;
    }
}
