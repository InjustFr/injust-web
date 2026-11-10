<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Domain\Music\Platform;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface PlatformFinder
{
    public function platform(): Platform;

    public function configured(): bool;

    public function find(ReleaseLookup $release): FinderResult;
}
