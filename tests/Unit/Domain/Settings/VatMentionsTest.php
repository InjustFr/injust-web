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

    public function testATrailingPeriodIsDroppedBecauseTheLegalTextsEndTheSentence(): void
    {
        $mentions = new VatMentions('TVA non applicable – art. 293 B du CGI. ', 'Autoliquidation..', 'Hors UE');

        self::assertSame(['TVA non applicable – art. 293 B du CGI', 'Autoliquidation', 'Hors UE'], [$mentions->consumer, $mentions->euBusiness, $mentions->nonEuBusiness]);
    }

    public function testTheDefaultsHaveNoTrailingPeriod(): void
    {
        $defaults = VatMentions::defaults();

        self::assertSame(['TVA non applicable – art. 293 B du CGI', 'Autoliquidation', 'TVA non applicable – art. 259-1 du CGI'], [$defaults->consumer, $defaults->euBusiness, $defaults->nonEuBusiness]);
    }
}
