<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Beats\AddBeat\AddBeat;
use App\Application\Beats\AddBeat\AddBeatHandler;
use App\Application\Beats\UploadBeatCover\UploadBeatCover;
use App\Application\Beats\UploadBeatCover\UploadBeatCoverHandler;
use App\Application\Beats\UploadBeatPreview\UploadBeatPreview;
use App\Application\Beats\UploadBeatPreview\UploadBeatPreviewHandler;
use App\Application\Music\AddRelease\AddRelease;
use App\Application\Music\AddRelease\AddReleaseHandler;
use App\Application\Music\FeatureRelease\FeatureRelease;
use App\Application\Music\FeatureRelease\FeatureReleaseHandler;
use App\Application\Music\UploadPortrait\UploadPortrait;
use App\Application\Music\UploadPortrait\UploadPortraitHandler;
use App\Application\Music\UploadReleaseCover\UploadReleaseCover;
use App\Application\Music\UploadReleaseCover\UploadReleaseCoverHandler;
use App\Application\Production\AddPortfolioItem\AddPortfolioItem;
use App\Application\Production\AddPortfolioItem\AddPortfolioItemHandler;
use App\Application\Production\UploadPortfolioCover\UploadPortfolioCover;
use App\Application\Production\UploadPortfolioCover\UploadPortfolioCoverHandler;
use App\Application\Shared\SetImageFocus\SetImageFocus;
use App\Application\Shared\SetImageFocus\SetImageFocusHandler;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\MusicalKey;
use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Production\PortfolioItemDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Settings\SiteImage;
use App\Domain\Shared\LocalizedText;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

final class FocusedImagesTest extends WebTestCase
{
    use ConfiguresSite;

    protected function setUp(): void
    {
        Clock::set(new MockClock('2026-11-10 09:00:00'));
    }

    public function testTheHeroPhotoTheReleaseCoverAndThePortraitCarryTheirFocus(): void
    {
        $client = self::createClient();
        self::upload(SiteImage::Hero);
        $release = self::getContainer()->get(AddReleaseHandler::class)(new AddRelease(new ReleaseDetails('Riot', null, ReleaseType::Single, new \DateTimeImmutable('2026-09-25'), new LocalizedText(), [], [], null, null, null)));
        self::getContainer()->get(UploadReleaseCoverHandler::class)(new UploadReleaseCover(Ulid::fromString($release->id), self::fern()));
        self::getContainer()->get(UploadPortraitHandler::class)(new UploadPortrait(self::fern()));

        $crawler = $client->request('GET', '/');
        $focus = [
            '.hero-photo' => [20, 30],
            '.release-cover img' => [80, 70],
            'img.portrait' => [0, 100],
        ];
        foreach ($focus as $selector => [$x, $y]) {
            $src = (string) $crawler->filter($selector)->attr('src');
            self::getContainer()->get(SetImageFocusHandler::class)(new SetImageFocus(basename($src, '.webp'), $x, $y));
        }

        $crawler = $client->request('GET', '/');

        self::assertSame('hero-photo fx-20 fy-30', $crawler->filter('.hero-photo')->attr('class'));
        self::assertSame('fx-80 fy-70', $crawler->filter('.release-cover img')->attr('class'));
        self::assertSame('portrait fx-0 fy-100', $crawler->filter('img.portrait')->attr('class'));
    }

    public function testTheHeroReleaseTheListenPageThePortfolioAndTheBeatsCarryTheirFocus(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, true);
        $release = self::getContainer()->get(AddReleaseHandler::class)(new AddRelease(new ReleaseDetails('Riot', null, ReleaseType::Single, new \DateTimeImmutable('2026-09-25'), new LocalizedText(), [], [new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com/track/riot')], null, null)))->id;
        self::getContainer()->get(UploadReleaseCoverHandler::class)(new UploadReleaseCover(Ulid::fromString($release), self::fern()));
        self::getContainer()->get(FeatureReleaseHandler::class)(new FeatureRelease(Ulid::fromString($release), true));
        $work = self::getContainer()->get(AddPortfolioItemHandler::class)(new AddPortfolioItem(new PortfolioItemDetails('Burn', 'The Rioters', [ServiceKind::Mix], 2025, null)))->id;
        self::getContainer()->get(UploadPortfolioCoverHandler::class)(new UploadPortfolioCover(Ulid::fromString($work), self::fern()));
        $beat = Ulid::fromString(self::getContainer()->get(AddBeatHandler::class)(new AddBeat(new BeatDetails('Static', null, 140, MusicalKey::AMinor, [], true)))->id);
        $preview = tempnam(sys_get_temp_dir(), 'beat');
        self::assertIsString($preview);
        file_put_contents($preview, "ID3\x04\x00\x00\x00\x00\x00\x00beat");
        self::getContainer()->get(UploadBeatPreviewHandler::class)(new UploadBeatPreview($beat, $preview, 'static.mp3'));
        self::getContainer()->get(UploadBeatCoverHandler::class)(new UploadBeatCover($beat, self::fern()));
        $focus = [
            '/' => ['.hero-release-cover', 15, 85],
            '/production' => ['.work-cover img', 100, 0],
            '/beats' => ['.beat-cover img', 25, 75],
        ];
        foreach ($focus as $page => [$selector, $x, $y]) {
            $src = (string) $client->request('GET', $page)->filter($selector)->attr('src');
            self::getContainer()->get(SetImageFocusHandler::class)(new SetImageFocus(basename($src, '.webp'), $x, $y));
        }

        self::assertSame('hero-release-cover fx-15 fy-85', $client->request('GET', '/')->filter('.hero-release-cover')->attr('class'));
        self::assertSame('fx-15 fy-85', $client->request('GET', '/listen/riot')->filter('.listen-cover img')->attr('class'));
        self::assertSame('fx-100 fy-0', $client->request('GET', '/production')->filter('.work-cover img')->attr('class'));
        $crawler = $client->request('GET', '/beats');
        self::assertSame('fx-25 fy-75', $crawler->filter('.beat-cover img')->attr('class'));
        $row = Json::array(Json::array(Json::decode((string) $crawler->filter('.beat-catalogue')->attr('data-props')), 'rows'), 0);
        self::assertSame('fx-25 fy-75', $row['coverClass']);
    }

    public function testACenteredImageKeepsItsPlainClasses(): void
    {
        $client = self::createClient();
        self::upload(SiteImage::Hero);

        $crawler = $client->request('GET', '/');

        self::assertSame('hero-photo', $crawler->filter('.hero-photo')->attr('class'));
    }

    private static function fern(): string
    {
        return \dirname(__DIR__, 2).'/Fixtures/large-fern.png';
    }
}
