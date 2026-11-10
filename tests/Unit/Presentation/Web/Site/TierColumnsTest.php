<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Beats\LicenceTierView;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierDetails;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Presentation\Web\Site\PriceFormat;
use App\Presentation\Web\Site\TierColumns;
use PHPUnit\Framework\TestCase;

final class TierColumnsTest extends TestCase
{
    public function testAColumnCarriesTheTierInTheVisitorLanguage(): void
    {
        $basic = LicenceTierView::of(LicenceTier::add(new LicenceTierDetails(new LocalizedText('Basique', 'Basic'), new Price(2950), new LocalizedText('Usage **perso**.', 'Personal use.'), [Deliverable::Mp3, Deliverable::Wav], false, 10000, 500, 1), 0));
        $exclusive = LicenceTierView::of(LicenceTier::add(new LicenceTierDetails(new LocalizedText('Exclusive'), new Price(49900), new LocalizedText(), [Deliverable::Stems], true), 1));

        self::assertSame([
            [
                'id' => $basic->id,
                'name' => 'Basique',
                'price' => "29,50\u{a0}€",
                'deliverables' => ['mp3', 'wav'],
                'streams' => 10000,
                'copies' => 500,
                'videos' => 1,
                'exclusive' => false,
                'summary' => "<p>Usage <strong>perso</strong>.</p>\n",
            ],
            [
                'id' => $exclusive->id,
                'name' => 'Exclusive',
                'price' => "499\u{a0}€",
                'deliverables' => ['stems'],
                'streams' => null,
                'copies' => null,
                'videos' => null,
                'exclusive' => true,
                'summary' => '',
            ],
        ], new TierColumns(new PriceFormat(), new LegalMarkdown())->columns([$basic, $exclusive], 'fr'));
    }
}
