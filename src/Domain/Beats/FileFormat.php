<?php

declare(strict_types=1);

namespace App\Domain\Beats;

enum FileFormat: string
{
    public const int HEAD = 12;

    case Mp3 = 'mp3';
    case Wav = 'wav';
    case Zip = 'zip';

    public function mimeType(): string
    {
        return match ($this) {
            self::Mp3 => 'audio/mpeg',
            self::Wav => 'audio/wav',
            self::Zip => 'application/zip',
        };
    }

    public function matches(string $head): bool
    {
        return match ($this) {
            self::Mp3 => str_starts_with($head, 'ID3') || 1 === preg_match('/^\xFF[\xE0-\xFF]/', $head),
            self::Wav => str_starts_with($head, 'RIFF') && 'WAVE' === substr($head, 8, 4),
            self::Zip => str_starts_with($head, "PK\x03\x04"),
        };
    }
}
