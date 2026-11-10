<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Settings\Exception\InvalidUrl;

final class HttpsUrl
{
    public static function orBlank(string $url): string
    {
        $url = trim($url);
        if ('' !== $url && (false === filter_var($url, \FILTER_VALIDATE_URL) || 'https' !== parse_url($url, \PHP_URL_SCHEME))) {
            throw new InvalidUrl($url);
        }

        return $url;
    }
}
