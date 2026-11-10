<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Beats\BeatsQueries;
use App\Application\Beats\BeatTierView;
use App\Application\Beats\BeatView;
use App\Application\Beats\LicenceTierView;
use App\Application\Shared\ImageFocusQueries;
use App\Application\Shared\LegalMarkdown;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierDetails;
use App\Domain\Beats\MusicalKey;
use App\Domain\Shared\ImageFocus;
use App\Domain\Shared\ImageFocusRepository;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Presentation\Web\Site\BeatRows;
use App\Presentation\Web\Site\CheckoutTierOptions;
use App\Presentation\Web\Site\ImageFocusClasses;
use App\Presentation\Web\Site\PriceFormat;
use App\Presentation\Web\Site\TierColumns;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class BeatRowsTest extends TestCase
{
    public function testARowCarriesTheBeatWithItsFromPriceInTheVisitorLanguage(): void
    {
        $beat = Beat::add(new BeatDetails('Riot Static', null, 140, MusicalKey::CSharpMinor, ['punk', 'dark'], true), 0);
        $beat->useCover('01JCOVER');
        $beat->usePreview(new BeatFile('01JPREVIEW', 'tag.mp3', 1, 'audio/mpeg'));
        $view = BeatView::of($beat);
        [$premium, $basic, $cheap] = [self::option('Premium', 5900, true), self::option('Basic', 2950, true), self::option('Cheap', 1000, false)];
        $buy = 'checkout:productType=beat&productId='.$view->id.'&tier=';

        self::assertSame([[
            'id' => $view->id,
            'slug' => 'riot-static',
            'title' => 'Riot Static',
            'bpm' => 140,
            'key' => 'c-sharp-minor',
            'keyLabel' => 'Do# mineur',
            'tags' => ['punk', 'dark'],
            'cover' => 'media_image:file=01JCOVER.webp',
            'coverClass' => 'fx-35 fy-60',
            'preview' => 'media_audio:id=01JPREVIEW',
            'price' => "dès 29,50\u{a0}€",
            'cents' => 2950,
            'licences' => [$premium->tier->id => $buy.$premium->tier->id, $basic->tier->id => $buy.$basic->tier->id, $cheap->tier->id => null],
            'buy' => $buy.$basic->tier->id,
        ]], self::rows([$view->id => [$premium, $basic, $cheap]])->rows([$view]));
    }

    public function testABeatWithoutCoverPreviewOrPriceHasNulls(): void
    {
        $view = BeatView::of(Beat::add(new BeatDetails('Last Call', null, 90, MusicalKey::DMajor), 0));

        $row = self::rows([])->rows([$view])[0];

        self::assertSame([null, '', null, null, null, 'Ré majeur', [], null], [$row['cover'], $row['coverClass'], $row['preview'], $row['price'], $row['cents'], $row['keyLabel'], $row['licences'], $row['buy']]);
    }

    public function testTheKeysOfTheRowsFollowTheKeyOrderOnce(): void
    {
        $rows = [
            ['key' => 'd-major', 'keyLabel' => 'D major'],
            ['key' => 'c-sharp-minor', 'keyLabel' => 'C# minor'],
            ['key' => 'd-major', 'keyLabel' => 'D major'],
        ];

        self::assertSame([['value' => 'c-sharp-minor', 'label' => 'C# minor'], ['value' => 'd-major', 'label' => 'D major']], BeatRows::keys($rows));
        self::assertSame([], BeatRows::keys([]));
    }

    public function testTheTagsOfTheRowsAreSortedOnce(): void
    {
        self::assertSame(['dark', 'punk', 'riot'], BeatRows::tags([['tags' => ['riot', 'dark']], ['tags' => []], ['tags' => ['punk', 'dark']]]));
        self::assertSame([], BeatRows::tags([]));
    }

    private static function option(string $id, int $cents, bool $purchasable): BeatTierView
    {
        return new BeatTierView(LicenceTierView::of(LicenceTier::add(new LicenceTierDetails(new LocalizedText('', $id), new Price($cents), new LocalizedText(), [Deliverable::Mp3], false, null, null, null), 0)), $purchasable);
    }

    /** @param array<string, list<BeatTierView>> $options */
    private static function rows(array $options): BeatRows
    {
        $beats = self::createStub(BeatsQueries::class);
        $beats->method('tiersFor')->willReturnCallback(static fn (string $id): array => $options[$id] ?? []);
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'site.beats.from' => 'dès {price}',
            'site.beats.key' => '{note} {mode, select, major {majeur} other {mineur}}',
            'site.beats.notes.c-sharp' => 'Do#',
            'site.beats.notes.d' => 'Ré',
        ], 'fr', 'messages+intl-icu');

        $cover = ImageFocus::centered('01JCOVER');
        $cover->moveTo(35, 60);
        $focuses = self::createStub(ImageFocusRepository::class);
        $focuses->method('all')->willReturn([$cover]);

        return new BeatRows($beats, new PriceFormat(), $translator, self::urls(), new CheckoutTierOptions($beats, new TierColumns(new PriceFormat(), new LegalMarkdown()), self::urls()), new ImageFocusClasses(new ImageFocusQueries($focuses)));
    }

    private static function urls(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            /** @param array<string, mixed> $parameters */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return self::ABSOLUTE_PATH === $referenceType ? $name.':'.http_build_query($parameters) : 'wrong';
            }
        };
    }
}
