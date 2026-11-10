<?php

declare(strict_types=1);

namespace App\Application;

interface Transaction
{
    public function commit(): void;
}
