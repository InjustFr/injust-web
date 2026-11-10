<?php

declare(strict_types=1);

namespace App\Application;

interface Atomic
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function atomically(callable $work): mixed;
}
