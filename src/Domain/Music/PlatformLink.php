<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Shared\HttpsUrl;

final readonly class PlatformLink
{
    public Platform $platform;
    public string $url;

    public function __construct(Platform $platform, string $url)
    {
        $this->platform = $platform;
        $this->url = HttpsUrl::of($url);
    }
}
