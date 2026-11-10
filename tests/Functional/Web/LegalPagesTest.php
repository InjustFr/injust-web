<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Settings\LegalKind;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class LegalPagesTest extends WebTestCase
{
    use ConfiguresSite;

    private const string MENTIONS = <<<'MD'
        ## Éditeur

        {name}, {businessForm}, SIREN {siren}, {address}, {email}, {phone}.
        Directeur : {publicationDirector}. Hébergeur : {hostName}, {hostAddress}, {hostPhone}.
        Médiateur : [{mediatorName}]({mediatorUrl}). TVA : {vatMention}. Site : {siteUrl}. Mise à jour : {updatedAt}.
        MD;

    public function testTheLegalNoticeShowsTheIdentityInPlaceOfThePlaceholders(): void
    {
        $client = self::createClient();
        Clock::set(new MockClock('2026-11-10 09:00:00'));
        self::editIdentity();
        self::writeLegal(LegalKind::Mentions, self::MENTIONS, 'Published by {name}. Updated {updatedAt}.');

        $crawler = $client->request('GET', '/fr/legal/mentions');

        self::assertResponseIsSuccessful();
        self::assertSame('Mentions légales', $crawler->filter('main h1')->text());
        self::assertSame('Éditeur', $crawler->filter('.legal-body h2')->text());
        self::assertSame(
            'Louis Injust, EI, SIREN 443061841, 1 rue du Son, 75000 Paris, louis@injust.test, +33 6 00 00 00 00. Directeur : Louis Injust. Hébergeur : OVH SAS, 2 rue Kellermann, 59100 Roubaix, +33 9 72 10 10 07. Médiateur : Médiateur Test. TVA : TVA non applicable – art. 293 B du CGI. Site : http://localhost. Mise à jour : 10 novembre 2026.',
            $crawler->filter('.legal-body p')->text(),
        );
        self::assertSame('https://mediator.test', $crawler->filter('.legal-body a')->attr('href'));

        $crawler = $client->request('GET', '/legal/mentions');
        self::assertSame('Legal notice', $crawler->filter('main h1')->text());
        self::assertSame('Published by Louis Injust. Updated November 10, 2026.', $crawler->filter('.legal-body p')->text());
    }

    public function testAMissingTranslationShowsTheOtherLanguage(): void
    {
        $client = self::createClient();
        self::writeLegal(LegalKind::CgvBeats, 'Conditions en français');

        $crawler = $client->request('GET', '/legal/cgv-beats');

        self::assertSame('Conditions en français', $crawler->filter('.legal-body p')->text());
    }

    public function testHtmlInTheTextOrTheIdentityIsShownAsText(): void
    {
        $client = self::createClient();
        self::editIdentity('<b>Louis</b>');
        self::writeLegal(LegalKind::Privacy, "<script>alert(1)</script>\n\n{name} [x](javascript:alert(1))");

        $crawler = $client->request('GET', '/fr/legal/privacy');

        self::assertCount(0, $crawler->filter('.legal-body script, .legal-body b'));
        self::assertStringContainsString('&lt;script&gt;', $crawler->filter('.legal-body')->html());
        self::assertStringContainsString('<b>Louis</b>', $crawler->filter('.legal-body')->text());
        self::assertNull($crawler->filter('.legal-body a')->attr('href'));
    }

    public function testALegalPageReadsTheSettingsRowOnce(): void
    {
        self::editIdentity();
        self::writeLegal(LegalKind::Mentions, '{name}');
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->enableProfiler();

        $client->request('GET', '/legal/mentions');

        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        $queries = $collector->getQueries()['default'] ?? [];
        self::assertIsArray($queries);
        $sql = array_column($queries, 'sql');
        self::assertCount(1, array_filter($sql, static fn (mixed $query): bool => \is_string($query) && str_contains($query, 'FROM settings')));
    }

    public function testALegalPageNotWrittenYetIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/legal/licence');
        self::assertResponseStatusCodeSame(404);
        self::assertStringStartsWith('text/html', (string) $client->getResponse()->headers->get('Content-Type'));

        $client->request('GET', '/legal/unknown');
        self::assertResponseStatusCodeSame(404);
    }
}
