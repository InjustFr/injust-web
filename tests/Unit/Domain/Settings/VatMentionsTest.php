<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Settings;

use App\Domain\Settings\VatMentions;
use PHPUnit\Framework\TestCase;

final class VatMentionsTest extends TestCase
{
    public function testEachMentionIsTrimmed(): void
    {
        $mentions = new VatMentions(' Franchise ', ' Autoliquidation ', ' Hors UE ');

        self::assertSame(['Franchise', 'Autoliquidation', 'Hors UE'], [$mentions->consumer, $mentions->euBusiness, $mentions->nonEuBusiness]);
    }
}
