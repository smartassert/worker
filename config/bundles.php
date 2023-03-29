<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
    SmartAssert\TestAuthenticationProviderBundle\TestAuthenticationProviderBundle::class => [
        'test' => true,
        'integration' => true
    ],
];
