<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Beats\BeatsQueries;
use App\Application\Beats\BeatView;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\MusicalKey;
use App\Domain\Shared\Price;
use App\Presentation\Web\Site\BeatRows;
use App\Presentation\Web\Site\PriceFormat;
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

        self::assertSame([[
            'id' => $view->id,
            'slug' => 'riot-static',
            'title' => 'Riot Static',
            'bpm' => 140,
            'key' => 'c-sharp-minor',
            'keyLabel' => 'Do# mineur',
            'tags' => ['punk', 'dark'],
            'cover' => 'media_image:file=01JCOVER.webp',
            'preview' => 'media_audio:id=01JPREVIEW',
            'price' => "dès 29,50\u{a0}€",
            'cents' => 2950,
        ]], self::rows([$view->id => new Price(2950)])->rows([$view]));
    }

    public function testABeatWithoutCoverPreviewOrPriceHasNulls(): void
    {
        $view = BeatView::of(Beat::add(new BeatDetails('Last Call', null, 90, MusicalKey::DMajor), 0));

        $row = self::rows([])->rows([$view])[0];

        self::assertSame([null, null, null, null, 'Ré majeur'], [$row['cover'], $row['preview'], $row['price'], $row['cents'], $row['keyLabel']]);
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

    /** @param array<string, Price> $prices */
    private static function rows(array $prices): BeatRows
    {
        $beats = self::createStub(BeatsQueries::class);
        $beats->method('fromPrice')->willReturnCallback(static fn (string $id): ?Price => $prices[$id] ?? null);
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'site.beats.from' => 'dès {price}',
            'site.beats.key' => '{note} {mode, select, major {majeur} other {mineur}}',
            'site.beats.notes.c-sharp' => 'Do#',
            'site.beats.notes.d' => 'Ré',
        ], 'fr', 'messages+intl-icu');

        return new BeatRows($beats, new PriceFormat(), $translator, self::urls());
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
