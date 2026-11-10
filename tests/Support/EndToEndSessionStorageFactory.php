<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

final readonly class EndToEndSessionStorageFactory implements SessionStorageFactoryInterface
{
    public function __construct(
        private SessionStorageFactoryInterface $mock,
        private SessionStorageFactoryInterface $native,
        private bool $useNative,
    ) {
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        return ($this->useNative ? $this->native : $this->mock)->createStorage($request);
    }
}
