<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Beats;

use App\Application\Beats\BeatView;
use App\Application\Beats\StoredBeatsQueries;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\BeatRepository;
use App\Domain\Beats\BeatsPage;
use App\Domain\Beats\BeatsPageRepository;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierDetails;
use App\Domain\Beats\LicenceTierRepository;
use App\Domain\Beats\MusicalKey;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class StoredBeatsQueriesTest extends TestCase
{
    private Beat $listed;

    private Beat $hidden;

    private Beat $sold;

    private Beat $silent;

    private LicenceTier $basic;

    private LicenceTier $premium;

    private LicenceTier $exclusive;

    private StoredBeatsQueries $queries;

    protected function setUp(): void
    {
        $this->listed = self::beat('Listed', true, true);
        $this->hidden = self::beat('Hidden', false, true);
        $this->sold = self::beat('Sold', true, true);
        $this->sold->markSoldExclusively(new \DateTimeImmutable('2026-11-01'));
        $this->silent = self::beat('Silent', true, false);
        $this->listed->useFile(Deliverable::Mp3, new BeatFile('m', 'a.mp3', 1, 'audio/mpeg'));
        $this->listed->useFile(Deliverable::Wav, new BeatFile('w', 'a.wav', 1, 'audio/wav'));
        $this->premium = self::tier('Premium', 4900, [Deliverable::Mp3, Deliverable::Wav]);
        $this->basic = self::tier('Basic', 2900, [Deliverable::Mp3]);
        $this->exclusive = self::tier('Exclusive', 1900, [Deliverable::Mp3, Deliverable::Wav, Deliverable::Stems]);
        $page = BeatsPage::initial();
        $page->edit(new LocalizedText('Intro FR', 'Intro EN'), new LocalizedText('FAQ FR', 'FAQ EN'));
        $pages = self::createStub(BeatsPageRepository::class);
        $pages->method('current')->willReturn($page);
        $all = [$this->hidden, $this->listed, $this->sold, $this->silent];
        $beats = self::createStub(BeatRepository::class);
        $beats->method('all')->willReturn($all);
        $beats->method('find')->willReturnCallback(static function (Ulid $id) use ($all): ?Beat {
            foreach ($all as $beat) {
                if ($beat->id()->equals($id)) {
                    return $beat;
                }
            }

            return null;
        });
        $tiers = self::createStub(LicenceTierRepository::class);
        $tiers->method('all')->willReturn([$this->premium, $this->basic, $this->exclusive]);
        $this->queries = new StoredBeatsQueries($pages, $beats, $tiers);
    }

    public function testThePublicPageListsVisibleUnsoldBeatsWithAPreview(): void
    {
        $page = $this->queries->page();

        self::assertEquals([new LocalizedText('Intro FR', 'Intro EN'), new LocalizedText('FAQ FR', 'FAQ EN')], [$page->intro, $page->faq]);
        self::assertSame([$this->listed->id()->toBase32()], array_map(static fn (BeatView $beat): string => $beat->id, $page->beats));
        self::assertSame(['Premium', 'Basic', 'Exclusive'], array_map(static fn ($tier): string => $tier->name->en, $page->tiers));
    }

    public function testTheAdminSeesEveryBeat(): void
    {
        $admin = $this->queries->admin();

        self::assertSame(['Hidden', 'Listed', 'Sold', 'Silent'], array_map(static fn (BeatView $beat): string => $beat->title, $admin->beats));
        self::assertCount(3, $admin->tiers);
        self::assertSame('Intro EN', $admin->page->intro->en);
    }

    public function testABeatIsFoundOnlyWhenListed(): void
    {
        self::assertSame('Listed', $this->queries->beat($this->listed->id()->toBase32())?->title);
        self::assertNull($this->queries->beat($this->hidden->id()->toBase32()));
        self::assertNull($this->queries->beat($this->sold->id()->toBase32()));
        self::assertNull($this->queries->beat($this->silent->id()->toBase32()));
        self::assertNull($this->queries->beat(new Ulid()->toBase32()));
        self::assertNull($this->queries->beat('nope'));
    }

    public function testEveryTierSaysWhetherTheBeatCanBeBoughtWithIt(): void
    {
        $tiers = $this->queries->tiersFor($this->listed->id()->toBase32());

        self::assertSame([['Premium', true], ['Basic', true], ['Exclusive', false]], array_map(static fn ($option): array => [$option->tier->name->en, $option->purchasable], $tiers));
        self::assertSame([], $this->queries->tiersFor($this->hidden->id()->toBase32()));
        self::assertSame([], $this->queries->tiersFor('nope'));
    }

    public function testTheFromPriceIsTheCheapestPurchasableTier(): void
    {
        self::assertEquals(new Price(2900), $this->queries->fromPrice($this->listed->id()->toBase32()));
        self::assertNull($this->queries->fromPrice($this->hidden->id()->toBase32()));
        $this->listed->removeFile(Deliverable::Mp3);
        self::assertNull($this->queries->fromPrice($this->listed->id()->toBase32()));
    }

    private static function beat(string $title, bool $visible, bool $preview): Beat
    {
        $beat = Beat::add(new BeatDetails($title, null, 120, MusicalKey::CMajor, [], $visible), 0);
        if ($preview) {
            $beat->usePreview(new BeatFile('p'.$title, 'tag.mp3', 1, 'audio/mpeg'));
        }

        return $beat;
    }

    /** @param list<Deliverable> $deliverables */
    private static function tier(string $name, int $cents, array $deliverables): LicenceTier
    {
        return LicenceTier::add(new LicenceTierDetails(new LocalizedText($name, $name), new Price($cents), new LocalizedText(), $deliverables), 0);
    }
}
