<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Beats\AddBeat\AddBeat;
use App\Application\Beats\AddBeat\AddBeatHandler;
use App\Application\Beats\AddLicenceTier\AddLicenceTier;
use App\Application\Beats\AddLicenceTier\AddLicenceTierHandler;
use App\Application\Beats\EditBeatsPage\EditBeatsPage;
use App\Application\Beats\EditBeatsPage\EditBeatsPageHandler;
use App\Application\Beats\UploadBeatCover\UploadBeatCover;
use App\Application\Beats\UploadBeatCover\UploadBeatCoverHandler;
use App\Application\Beats\UploadBeatFile\UploadBeatFile;
use App\Application\Beats\UploadBeatFile\UploadBeatFileHandler;
use App\Application\Beats\UploadBeatPreview\UploadBeatPreview;
use App\Application\Beats\UploadBeatPreview\UploadBeatPreviewHandler;
use App\Application\Transaction;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\BeatRepository;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\LicenceTierDetails;
use App\Domain\Beats\MusicalKey;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Ulid;

final class BeatsPageTest extends WebTestCase
{
    use ConfiguresSite;
    use SignsInClient;

    private const array BYTES = [
        'mp3' => "ID3\x04\x00\x00\x00\x00\x00\x00beat",
        'wav' => "RIFF\x24\x00\x00\x00WAVEfmt ",
        'stems' => "PK\x03\x04stems",
    ];

    public function testTheTableListsTheListedBeatsWithTheirPreviewPriceAndAContactLink(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        self::standardTiers();
        $riot = self::beat('Riot Gear', 140, MusicalKey::AMinor, ['punk', 'dark'], files: [Deliverable::Mp3]);
        $burn = self::beat('Burn', 170, MusicalKey::CSharpMajor, ['punk']);
        self::beat('Hidden', 120, MusicalKey::CMajor, visible: false);
        self::beat('Silent', 120, MusicalKey::CMajor, preview: false);
        self::soldExclusively(self::beat('Sold', 120, MusicalKey::CMajor, files: [Deliverable::Mp3]));

        $crawler = $client->request('GET', '/beats');

        self::assertResponseIsSuccessful();
        self::assertSame('Beats', $crawler->filter('main h1')->text());
        self::assertSame(['Riot Gear', 'Burn'], self::texts($crawler, '.beat-title'));
        self::assertSame(['beat-riot-gear', 'beat-burn'], $crawler->filter('.beat-row')->each(static fn (Crawler $row): ?string => $row->attr('id')));
        self::assertSame(['140 BPM', '170 BPM'], self::texts($crawler, '.beat-bpm'));
        self::assertSame(['A minor', 'C# major'], self::texts($crawler, '.beat-key'));
        self::assertSame([['punk', 'dark'], ['punk']], $crawler->filter('.beat-tags')->each(static fn (Crawler $tags): array => self::texts($tags, 'li')));
        self::assertSame(['from €29.99', 'Price on request'], self::texts($crawler, '.beat-price'));
        self::assertSame(['Contact me', 'Contact me'], self::texts($crawler, '.beat-row a.button'));
        self::assertSame(['/contact?topic=beats&offer='.$riot, '/contact?topic=beats&offer='.$burn], $crawler->filter('.beat-row a.button')->each(static fn (Crawler $link): ?string => $link->attr('href')));
        self::assertSame('beat-title-'.$riot, $crawler->filter('.beat-row a.button')->attr('aria-describedby'));
        self::assertCount(1, $crawler->filter('#beat-title-'.$riot));
        $play = $crawler->filter('.beat-row a.beat-play')->first();
        self::assertMatchesRegularExpression('#^/media/audio/[0-9A-HJKMNP-TV-Z]{26}\.mp3$#', (string) $play->attr('href'));
        self::assertSame('Play “Riot Gear”', $play->attr('aria-label'));
        self::assertSame('true', $crawler->filter('.beat-row')->first()->filter('.cover-blank')->attr('aria-hidden'));
        self::assertCount(0, $crawler->filter('.beats-empty'));
        self::assertSame('Beats catalogue', $crawler->filter('.beat-table caption')->text());
        self::assertSame('BeatTable', $crawler->filter('.beat-catalogue')->attr('data-island'));
        self::assertStringNotContainsString('preview.mp3', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('Riot Gear.mp3', (string) $client->getResponse()->getContent());
    }

    public function testTheIslandGetsEveryListedBeatAndItsLabels(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        self::standardTiers();
        $riot = self::beat('Riot Gear', 140, MusicalKey::AMinor, ['punk'], files: [Deliverable::Mp3]);
        self::beat('Burn', 90, MusicalKey::EMinor, ['lofi']);

        $crawler = $client->request('GET', '/beats?tag[]=punk');

        $props = Json::decode((string) $crawler->filter('.beat-catalogue')->attr('data-props'));
        $rows = Json::array($props, 'rows');
        self::assertCount(2, $rows);
        $first = Json::array($rows, 0);
        self::assertSame(['id', 'slug', 'title', 'bpm', 'key', 'keyLabel', 'tags', 'cover', 'coverClass', 'preview', 'price', 'cents', 'licences', 'buy'], array_keys($first));
        self::assertSame([$riot, 'riot-gear', 'Riot Gear', 140, 'a-minor', 'A minor', ['punk'], null, 'from €29.99', 2999], [$first['id'], $first['slug'], $first['title'], $first['bpm'], $first['key'], $first['keyLabel'], $first['tags'], $first['cover'], $first['price'], $first['cents']]);
        self::assertMatchesRegularExpression('#^/media/audio/[0-9A-HJKMNP-TV-Z]{26}\.mp3$#', Json::string($first, 'preview'));
        self::assertSame(['lofi', 'punk'], $props['tags']);
        self::assertSame([['value' => 'e-minor', 'label' => 'E minor'], ['value' => 'a-minor', 'label' => 'A minor']], $props['keys']);
        self::assertSame(['tags' => ['punk'], 'key' => '', 'min' => null, 'max' => null], $props['filter']);
        self::assertSame(['min' => 40, 'max' => 300], $props['bounds']);
        $labels = Json::array($props, 'labels');
        self::assertSame('Play “{title}”', $labels['play']);
        self::assertSame(['No beat matches', '1 beat', '2 beats'], $labels['counts']);
        self::assertSame('/contact?topic=beats', $props['contact']);
        self::assertSame(['1 beat'], self::texts($crawler, '.beat-count'));
    }

    public function testTheFiltersWorkWithoutJavaScript(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        self::beat('Riot Gear', 140, MusicalKey::AMinor, ['punk', 'dark']);
        self::beat('Burn', 170, MusicalKey::AMinor, ['punk']);
        self::beat('Ash', 92, MusicalKey::CMajor, ['lofi']);

        $crawler = $client->request('GET', '/beats');
        $form = $crawler->filter('form.beat-filters');
        self::assertSame('get', $form->attr('method'));
        self::assertSame('/beats', $form->attr('action'));
        self::assertSame(['dark', 'lofi', 'punk'], $crawler->filter('.beat-filters input[name="tag[]"]')->each(static fn (Crawler $box): ?string => $box->attr('value')));
        self::assertSame(['', 'c-major', 'a-minor'], $crawler->filter('.beat-filters select[name="key"] option')->each(static fn (Crawler $option): ?string => $option->attr('value')));
        self::assertSame(['Any key', 'C major', 'A minor'], self::texts($crawler, '.beat-filters select[name="key"] option'));
        self::assertSame(['40', '40'], [$crawler->filter('input[name="bpm_min"]')->attr('min'), $crawler->filter('input[name="bpm_max"]')->attr('min')]);
        self::assertSame(['300', '300'], [$crawler->filter('input[name="bpm_min"]')->attr('max'), $crawler->filter('input[name="bpm_max"]')->attr('max')]);
        self::assertSame(['Riot Gear', 'Burn', 'Ash'], self::texts($crawler, '.beat-title'));
        self::assertSame('3 beats', $crawler->filter('.beat-count')->text());

        $crawler = $client->request('GET', '/beats?tag[]=punk&key=a-minor&bpm_min=100&bpm_max=150');

        self::assertSame(['Riot Gear'], self::texts($crawler, '.beat-title'));
        self::assertSame(['punk'], $crawler->filter('.beat-filters input[name="tag[]"][checked]')->each(static fn (Crawler $box): ?string => $box->attr('value')));
        self::assertSame('a-minor', $crawler->filter('select[name="key"] option[selected]')->attr('value'));
        self::assertSame(['100', '150'], [$crawler->filter('input[name="bpm_min"]')->attr('value'), $crawler->filter('input[name="bpm_max"]')->attr('value')]);
        self::assertSame('/beats', $crawler->filter('.beat-filters a.beat-reset')->attr('href'));

        $crawler = $client->request('GET', '/beats?tag[]=punk&tag[]=lofi');
        self::assertSame([], self::texts($crawler, '.beat-title'));
        self::assertSame('No beat matches these filters.', $crawler->filter('.beat-none')->text());
        self::assertSame('No beat matches', $crawler->filter('.beat-count')->text());

        $crawler = $client->request('GET', '/beats?bpm_min=fast&bpm_max=&key=');
        self::assertSame(['Riot Gear', 'Burn', 'Ash'], self::texts($crawler, '.beat-title'));
        self::assertNull($crawler->filter('input[name="bpm_min"]')->attr('value'));
        self::assertCount(0, $crawler->filter('.beat-none'));

        $crawler = $client->request('GET', '/beats?bpm_max=100&tag=punk');
        self::assertSame(['Ash'], self::texts($crawler, '.beat-title'));
    }

    public function testTheFrenchPageTranslatesKeysPricesAndLinks(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        self::standardTiers();
        $riot = self::beat('Riot Gear', 140, MusicalKey::ASharpMinor, ['punk'], files: [Deliverable::Mp3]);
        self::beat('Burn', 170, MusicalKey::DMajor);

        $crawler = $client->request('GET', '/fr/beats');

        self::assertSame(['La# mineur', 'Ré majeur'], self::texts($crawler, '.beat-key'));
        self::assertSame(["à partir de 29,99\u{a0}€", 'Prix sur demande'], self::texts($crawler, '.beat-price'));
        self::assertSame('/fr/contact?topic=beats&offer='.$riot, $crawler->filter('.beat-row a.button')->attr('href'));
        self::assertSame('Me contacter', $crawler->filter('.beat-row a.button')->text());
        self::assertSame('Écouter « Riot Gear »', $crawler->filter('.beat-play')->attr('aria-label'));
        self::assertSame('/fr/beats', $crawler->filter('form.beat-filters')->attr('action'));
        self::assertSame('Toutes les tonalités', $crawler->filter('select[name="key"] option')->text());
        self::assertSame('2 beats', $crawler->filter('.beat-count')->text());
        self::assertSame('page', $crawler->filter('header nav a[href="/fr/beats"]')->attr('aria-current'));
        self::assertCount(0, $crawler->filter('.placeholder'));
    }

    public function testACoveredBeatShowsItsThumbnail(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        $riot = self::beat('Riot Gear', 140, MusicalKey::AMinor);
        self::getContainer()->get(UploadBeatCoverHandler::class)(new UploadBeatCover(Ulid::fromString($riot), \dirname(__DIR__, 2).'/Fixtures/large-fern.png'));

        $crawler = $client->request('GET', '/beats');

        $cover = $crawler->filter('.beat-cover img');
        self::assertMatchesRegularExpression('#^/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', (string) $cover->attr('src'));
        self::assertSame(['', 'lazy'], [$cover->attr('alt'), $cover->attr('loading')]);
        self::assertCount(0, $crawler->filter('.beat-row .cover-blank'));
    }

    public function testTheLicencesAreComparedSideBySide(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        self::tier('Basic', 2999, [Deliverable::Mp3], summary: "MP3 for **demos**.\n\n<script>alert(1)</script>", streamCap: 100000, copyCap: 2000);
        self::tier('Exclusive', 49900, [Deliverable::Stems, Deliverable::Mp3, Deliverable::Wav], exclusive: true, videoCap: 1);

        $crawler = $client->request('GET', '/beats');

        self::assertSame('Licences', $crawler->filter('#tiers-title')->text());
        self::assertSame(['Basic', 'Exclusive'], self::texts($crawler, '.tier-table thead th[scope="col"]'));
        self::assertSame(['Price', 'Files', 'Streams', 'Copies', 'Music videos', 'Exclusive rights', 'Usage'], self::texts($crawler, '.tier-table tbody th[scope="row"]'));
        self::assertSame(['€29.99', '€499'], self::texts($crawler, '.tier-price'));
        self::assertSame([['MP3'], ['MP3', 'WAV', 'Stems']], $crawler->filter('.tier-files')->each(static fn (Crawler $cell): array => self::texts($cell, 'li')));
        self::assertSame(['100,000', 'Unlimited'], self::texts($crawler, '.tier-streams'));
        self::assertSame(['2,000', 'Unlimited'], self::texts($crawler, '.tier-copies'));
        self::assertSame(['Unlimited', '1'], self::texts($crawler, '.tier-videos'));
        self::assertSame(['No', 'Yes'], self::texts($crawler, '.tier-exclusive'));
        self::assertSame('demos', $crawler->filter('.tier-summary strong')->text());
        self::assertStringContainsString('&lt;script&gt;', $crawler->filter('.tier-summary')->first()->html());
        self::assertCount(0, $crawler->filter('main script'));
        self::assertSame('Exclusive', $crawler->filter('.tier-table thead th.tier-exclusive-head')->text());
    }

    public function testTheFrenchLicencesFormatNumbers(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        self::tier('Basic', 2999, [Deliverable::Mp3], streamCap: 100000);

        $crawler = $client->request('GET', '/fr/beats');

        self::assertSame(["100\u{202f}000"], self::texts($crawler, '.tier-streams'));
        self::assertSame(['Illimité'], self::texts($crawler, '.tier-copies'));
        self::assertSame(['Non'], self::texts($crawler, '.tier-exclusive'));
        self::assertSame(["29,99\u{a0}€"], self::texts($crawler, '.tier-price'));
    }

    public function testTheTextsAreEscapedMarkdown(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        self::getContainer()->get(EditBeatsPageHandler::class)(new EditBeatsPage(
            new LocalizedText('', "Beats that **hit**.\n\n<script>alert(1)</script>"),
            new LocalizedText('', "### Can I use it on Spotify?\n\nYes, within the **cap**.\n\n### Stems?\n\nWith the premium licence."),
        ));

        $crawler = $client->request('GET', '/fr/beats');

        self::assertSame('hit', $crawler->filter('.page-intro strong')->text());
        self::assertStringContainsString('&lt;script&gt;', $crawler->filter('.page-intro')->html());
        self::assertCount(0, $crawler->filter('.page-lead'));
        self::assertSame(['Can I use it on Spotify?', 'Stems?'], self::texts($crawler, '.faq details summary'));
        self::assertSame('cap', $crawler->filter('.faq-answer strong')->text());
    }

    public function testWithoutBeatsThePageSaysSoAndPointsToTheContactForm(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);

        $crawler = $client->request('GET', '/beats');

        self::assertResponseIsSuccessful();
        self::assertSame('Beats and instrumentals for your next release, with clear licences and instant downloads.', $crawler->filter('.page-lead')->text());
        self::assertSame('/contact?topic=beats', $crawler->filter('.beats-empty a')->attr('href'));
        self::assertCount(0, $crawler->filter('.beat-catalogue'));
        self::assertCount(0, $crawler->filter('.tiers'));
        self::assertCount(0, $crawler->filter('.faq'));
    }

    public function testEachBeatIsDescribedForSearchEngines(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        self::standardTiers();
        self::beat('Riot Gear', 140, MusicalKey::AMinor, files: [Deliverable::Mp3, Deliverable::Wav]);
        self::beat('Burn', 170, MusicalKey::AMinor);

        $crawler = $client->request('GET', '/beats');

        $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
        self::assertCount(4, $graph);
        self::assertSame([
            '@type' => 'MusicRecording',
            '@id' => 'http://localhost/beats#beat-riot-gear',
            'name' => 'Riot Gear',
            'url' => 'http://localhost/beats#beat-riot-gear',
            'byArtist' => ['@id' => 'http://localhost/#band'],
            'offers' => ['@type' => 'Offer', 'price' => '29.99', 'priceCurrency' => 'EUR', 'url' => 'http://localhost/beats#beat-riot-gear'],
        ], $graph[2]);
        self::assertSame(['@type' => 'MusicRecording', '@id' => 'http://localhost/beats#beat-burn', 'name' => 'Burn', 'url' => 'http://localhost/beats#beat-burn', 'byArtist' => ['@id' => 'http://localhost/#band']], $graph[3]);
        self::assertSame('Beats | Injust', $crawler->filter('title')->text());
    }

    public function testACoverIsPartOfTheRecordingDescription(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);
        $riot = self::beat('Riot Gear', 140, MusicalKey::AMinor);
        self::getContainer()->get(UploadBeatCoverHandler::class)(new UploadBeatCover(Ulid::fromString($riot), \dirname(__DIR__, 2).'/Fixtures/large-fern.png'));

        $crawler = $client->request('GET', '/beats');

        $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
        self::assertMatchesRegularExpression('#^http://localhost/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', Json::string(Json::array($graph, 2), 'image'));
    }

    public function testAnAdminSeesTheSwitchedOffPageWithABanner(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/beats');

        self::assertResponseIsSuccessful();
        self::assertSame('Hidden from visitors', $crawler->filter('.hidden-banner strong')->text());
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('Beats', $crawler->filter('main h1')->text());
    }

    public function testTheSitemapListsTheBeatsPageWhenTheSectionIsOn(): void
    {
        $client = self::createClient();
        self::switchSections(false, false, true);

        $client->request('GET', '/sitemap.xml');
        $locs = [];
        foreach (new \SimpleXMLElement((string) $client->getResponse()->getContent())->url as $url) {
            $locs[] = (string) $url->loc;
        }

        self::assertSame(['http://localhost/', 'http://localhost/fr', 'http://localhost/beats', 'http://localhost/fr/beats', 'http://localhost/contact', 'http://localhost/fr/contact'], $locs);
    }

    /** @return list<string> */
    private static function texts(Crawler $crawler, string $selector): array
    {
        return $crawler->filter($selector)->each(static fn (Crawler $node): string => $node->text());
    }

    /**
     * @param list<string>      $tags
     * @param list<Deliverable> $files
     */
    private static function beat(string $title, int $bpm, MusicalKey $key, array $tags = [], bool $visible = true, bool $preview = true, array $files = []): string
    {
        $id = self::getContainer()->get(AddBeatHandler::class)(new AddBeat(new BeatDetails($title, null, $bpm, $key, $tags, $visible)))->id;
        if ($preview) {
            self::getContainer()->get(UploadBeatPreviewHandler::class)(new UploadBeatPreview(Ulid::fromString($id), self::file('mp3'), $title.' preview.mp3'));
        }
        foreach ($files as $deliverable) {
            self::getContainer()->get(UploadBeatFileHandler::class)(new UploadBeatFile(Ulid::fromString($id), $deliverable, self::file($deliverable->value), $title.'.'.$deliverable->value));
        }

        return $id;
    }

    private static function file(string $format): string
    {
        $path = tempnam(sys_get_temp_dir(), 'beat');
        self::assertIsString($path);
        file_put_contents($path, self::BYTES[$format]);

        return $path;
    }

    private static function soldExclusively(string $id): void
    {
        self::getContainer()->get(BeatRepository::class)->get(Ulid::fromString($id))->markSoldExclusively(new \DateTimeImmutable());
        self::getContainer()->get(Transaction::class)->commit();
    }

    private static function standardTiers(): void
    {
        self::tier('Premium', 7900, [Deliverable::Mp3, Deliverable::Wav]);
        self::tier('Basic', 2999, [Deliverable::Mp3]);
    }

    /** @param list<Deliverable> $deliverables */
    private static function tier(string $name, int $cents, array $deliverables, string $summary = '', bool $exclusive = false, ?int $streamCap = null, ?int $copyCap = null, ?int $videoCap = null): void
    {
        self::getContainer()->get(AddLicenceTierHandler::class)(new AddLicenceTier(new LicenceTierDetails(
            new LocalizedText('', $name),
            new Price($cents),
            new LocalizedText('', $summary),
            $deliverables,
            $exclusive,
            $streamCap,
            $copyCap,
            $videoCap,
        )));
    }
}
