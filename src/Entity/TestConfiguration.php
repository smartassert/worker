<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(
 *     name="test_configuration",
 *     uniqueConstraints={
 *          @ORM\UniqueConstraint(
 *              name="browser_url_idx",
 *              columns={
 *                  "browser",
 *                  "url"
 *              }
 *          )
 *     }
 * )
 */
class TestConfiguration implements \JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $browser = '';

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $url = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public static function create(string $browser, string $url): self
    {
        $configuration = new TestConfiguration();
        $configuration->browser = $browser;
        $configuration->url = $url;

        return $configuration;
    }

    public function getBrowser(): string
    {
        return $this->browser;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'browser' => $this->browser,
            'url' => $this->url,
        ];
    }
}
