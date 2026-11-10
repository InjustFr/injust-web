<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Music\Exception\InvalidSlug;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class Slug
{
    public const int MAX = 200;

    public static function of(string $text): string
    {
        $slug = rtrim(substr(new AsciiSlugger()->slug($text)->lower()->toString(), 0, self::MAX), '-');

        return '' === $slug ? throw new InvalidSlug($text) : $slug;
    }
}
