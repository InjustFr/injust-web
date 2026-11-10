<?php

declare(strict_types=1);

namespace App\Domain\Beats;

enum Deliverable: string
{
    case Mp3 = 'mp3';
    case Wav = 'wav';
    case Stems = 'stems';

    public function format(): FileFormat
    {
        return match ($this) {
            self::Mp3 => FileFormat::Mp3,
            self::Wav => FileFormat::Wav,
            self::Stems => FileFormat::Zip,
        };
    }
}
