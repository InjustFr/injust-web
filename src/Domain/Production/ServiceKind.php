<?php

declare(strict_types=1);

namespace App\Domain\Production;

enum ServiceKind: string
{
    case Mix = 'mix';
    case Master = 'master';
    case MixMaster = 'mix-master';
    case Production = 'production';
    case Arrangement = 'arrangement';
    case Recording = 'recording';
    case Other = 'other';
}
