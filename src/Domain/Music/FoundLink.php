<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Shared\HttpsUrl;

final readonly class FoundLink
{
    public ?string $url;

    public function __construct(public Platform $platform, ?string $url)
    {
        $this->url = null === $url ? null : HttpsUrl::of($url);
    }
}
