<?php

declare(strict_types=1);

namespace App\Domain\Music\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidYoutubeUrl extends DomainException
{
    public function __construct(string $url)
    {
        parent::__construct('music.invalid_youtube_url', ['url' => $url]);
    }
}
