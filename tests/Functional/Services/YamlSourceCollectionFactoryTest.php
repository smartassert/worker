<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Exception\InvalidManifestException;
use App\Exception\MissingManifestException;
use App\Model\Manifest;
use App\Model\YamlSourceCollection;
use App\Services\YamlSourceCollectionFactory;
use App\Tests\AbstractBaseFunctionalTest;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\Collection\ProviderInterface;
use SmartAssert\YamlFile\YamlFile;
use Symfony\Component\Yaml\Exception\ParseException;

class YamlSourceCollectionFactoryTest extends AbstractBaseFunctionalTest
{
    private YamlSourceCollectionFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(YamlSourceCollectionFactory::class);
        \assert($factory instanceof YamlSourceCollectionFactory);
        $this->factory = $factory;
    }

    /**
     * @dataProvider createThrowsExceptionDataProvider
     */
    public function testCreateThrowsException(
        ProviderInterface $provider,
        \Exception $expected,
        ?callable $additionalAssertions = null,
    ): void {
        try {
            $this->factory->create($provider);
            self::fail($expected::class . ' not thrown');
        } catch (InvalidManifestException | MissingManifestException $e) {
            self::assertSame($expected::class, $e::class);
            self::assertSame($expected->getMessage(), $e->getMessage());
            self::assertSame($expected->getCode(), $e->getCode());
            self::assertEquals($expected->getPrevious(), $e->getPrevious());

            if (is_callable($additionalAssertions)) {
                $additionalAssertions($expected, $e);
            }
        }
    }

    /**
     * @return array<mixed>
     */
    public function createThrowsExceptionDataProvider(): array
    {
        $invalidYaml = '  invalid' . "\n" . 'yaml';
        $invalidDataExceptionAdditionalAssertions = function (
            InvalidManifestException $expected,
            InvalidManifestException $actual
        ): void {
            self::assertSame($expected->content, $actual->content);
        };

        return [
            'manifest not present' => [
                'provider' => new ArrayCollection([]),
                'expected' => new MissingManifestException(),
            ],
            'invalid, unparseable yaml' => [
                'provider' => new ArrayCollection([
                    YamlFile::create('manifest.yaml', $invalidYaml)
                ]),
                'expected' => InvalidManifestException::createForInvalidYaml(
                    $invalidYaml,
                    new ParseException('Unable to parse.', 1, '  invalid')
                ),
            ],
            'invalid, data is not an array' => [
                'provider' => new ArrayCollection([
                    YamlFile::create('manifest.yaml', 'not an array')
                ]),
                'expected' => InvalidManifestException::createForInvalidData('not an array'),
                'additionalAssertions' => $invalidDataExceptionAdditionalAssertions,
            ],
            'invalid, empty manifest' => [
                'provider' => new ArrayCollection([
                    YamlFile::create('manifest.yaml', '  ')
                ]),
                'expected' => InvalidManifestException::createForEmptyContent('  '),
                'additionalAssertions' => $invalidDataExceptionAdditionalAssertions,
            ],
            'invalid, not all data items are strings' => [
                'provider' => new ArrayCollection([
                    YamlFile::create('manifest.yaml', '- file1.yaml' . "\n" . '- 100')
                ]),
                'expected' => InvalidManifestException::createForInvalidData('- file1.yaml' . "\n" . '- 100'),
                'additionalAssertions' => $invalidDataExceptionAdditionalAssertions,
            ],
        ];
    }

    /**
     * @dataProvider createSuccessDataProvider
     */
    public function testCreateSuccess(ProviderInterface $provider, YamlSourceCollection $expected): void
    {
        self::assertEquals($expected, $this->factory->create($provider));
    }

    /**
     * @return array<mixed>
     */
    public function createSuccessDataProvider(): array
    {
        return [
            'single-item manifest, empty sources' => [
                'provider' => new ArrayCollection([
                    YamlFile::create('manifest.yaml', '- file1.yaml')
                ]),
                'expected' => new YamlSourceCollection(
                    new Manifest([
                        'file1.yaml',
                    ]),
                    new ArrayCollection([])
                )
            ],
            'multiple-item manifest, empty sources' => [
                'provider' => new ArrayCollection([
                    YamlFile::create('manifest.yaml', '- file1.yaml' . "\n" . '- file2.yaml' . "\n" . '- file3.yaml')
                ]),
                'expected' => new YamlSourceCollection(
                    new Manifest([
                        'file1.yaml',
                        'file2.yaml',
                        'file3.yaml',
                    ]),
                    new ArrayCollection([])
                )
            ],
            'multiple-item manifest, non-empty sources' => [
                'provider' => new ArrayCollection([
                    YamlFile::create('manifest.yaml', '- file1.yaml' . "\n" . '- file2.yaml' . "\n" . '- file3.yaml'),
                    YamlFile::create('file1.yaml', 'non-empty content'),
                ]),
                'expected' => new YamlSourceCollection(
                    new Manifest([
                        'file1.yaml',
                        'file2.yaml',
                        'file3.yaml',
                    ]),
                    new ArrayCollection([
                        YamlFile::create('file1.yaml', 'non-empty content'),
                    ])
                )
            ],
        ];
    }
}
