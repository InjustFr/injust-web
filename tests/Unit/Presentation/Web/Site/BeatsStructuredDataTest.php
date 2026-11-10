<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Beats\BeatsQueries;
use App\Application\Beats\BeatView;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\MusicalKey;
use App\Domain\Shared\Price;
use App\Presentation\Web\Site\BeatsStructuredData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class BeatsStructuredDataTest extends TestCase
{
    public function testEveryBeatIsARecordingOfTheBandWithItsFromPrice(): void
    {
        $priced = Beat::add(new BeatDetails('Riot Static', null, 140, MusicalKey::AMinor), 0);
        $priced->useCover('01JCOVER');
        $cheap = BeatView::of(Beat::add(new BeatDetails('Cheap', null, 140, MusicalKey::AMinor), 1));
        $free = BeatView::of(Beat::add(new BeatDetails('Last Call', null, 90, MusicalKey::DMajor), 2));
        $priced = BeatView::of($priced);
        $beats = self::createStub(BeatsQueries::class);
        $beats->method('fromPrice')->willReturnCallback(static fn (string $id): ?Price => [$priced->id => new Price(2905), $cheap->id => new Price(5)][$id] ?? null);

        $recordings = new BeatsStructuredData($beats, self::urls())->recordings([$priced, $cheap, $free]);

        $band = ['@id' => 'https://injust.test/home#band'];
        self::assertSame([
            [
                '@type' => 'MusicRecording',
                '@id' => 'https://injust.test/beats#beat-riot-static',
                'name' => 'Riot Static',
                'url' => 'https://injust.test/beats#beat-riot-static',
                'byArtist' => $band,
                'image' => 'https://injust.test/media_image?file=01JCOVER.webp',
                'offers' => ['@type' => 'Offer', 'price' => '29.05', 'priceCurrency' => 'EUR', 'url' => 'https://injust.test/beats#beat-riot-static'],
            ],
            [
                '@type' => 'MusicRecording',
                '@id' => 'https://injust.test/beats#beat-cheap',
                'name' => 'Cheap',
                'url' => 'https://injust.test/beats#beat-cheap',
                'byArtist' => $band,
                'offers' => ['@type' => 'Offer', 'price' => '0.05', 'priceCurrency' => 'EUR', 'url' => 'https://injust.test/beats#beat-cheap'],
            ],
            [
                '@type' => 'MusicRecording',
                '@id' => 'https://injust.test/beats#beat-last-call',
                'name' => 'Last Call',
                'url' => 'https://injust.test/beats#beat-last-call',
                'byArtist' => $band,
            ],
        ], $recordings);
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
                return self::ABSOLUTE_URL === $referenceType ? rtrim('https://injust.test/'.$name.'?'.http_build_query($parameters), '?') : 'wrong';
            }
        };
    }
}
