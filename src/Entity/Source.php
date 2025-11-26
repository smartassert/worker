<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SourceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SourceRepository::class)]
class Source
{
    public const TYPE_TEST = 'test';
    public const TYPE_RESOURCE = 'resource';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private readonly string $type;

    #[ORM\Column(type: 'text')]
    private readonly string $path;

    /**
     * @param Source::TYPE_* $type
     */
    public function __construct(string $type, string $path)
    {
        $this->id = md5($type . $path);
        $this->type = $type;
        $this->path = $path;
    }
}
