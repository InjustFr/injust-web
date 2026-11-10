<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Lessons\AddLessonOffer\AddLessonOffer;
use App\Application\Lessons\AddLessonOffer\AddLessonOfferHandler;
use App\Application\Production\AddProductionService\AddProductionService;
use App\Application\Production\AddProductionService\AddProductionServiceHandler;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ContactPageTest extends WebTestCase
{
    use ConfiguresSite;

    public function testTheContactPageIsAlwaysOpenAndStartsNoSession(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSame('Contact', $crawler->filter('main h1')->text());
        self::assertSame('Contact | Injust', $crawler->filter('title')->text());
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
        $props = self::props($crawler);
        self::assertSame('/api/contact', $props['endpoint']);
        self::assertSame('en', $props['locale']);
        self::assertSame('general', $props['topic']);
        self::assertNull($props['offer']);
        self::assertSame([['value' => 'general', 'label' => 'Something else']], $props['topics']);
        self::assertSame('Send', Json::string($props, 'labels', 'send'));
    }

    public function testTheTopicsFollowTheSwitchedOnSections(): void
    {
        $client = self::createClient();
        self::switchSections(false, true, true);

        $crawler = $client->request('GET', '/fr/contact?topic=beats');

        $props = self::props($crawler);
        self::assertSame('fr', $props['locale']);
        self::assertSame('beats', $props['topic']);
        self::assertSame(['general', 'production', 'beats'], array_column(Json::array($props, 'topics'), 'value'));
        self::assertSame('Envoyer', Json::string($props, 'labels', 'send'));
    }

    public function testALessonsLinkPrefillsTheTopicAndTheOfferInTheVisitorsLanguage(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        $id = self::offer();

        $props = self::props($client->request('GET', '/fr/contact?topic=lessons&offer='.$id));
        self::assertSame('lessons', $props['topic']);
        self::assertSame(['id' => $id, 'label' => 'Pack de quatre'], $props['offer']);

        $props = self::props($client->request('GET', '/contact?topic=lessons&offer='.$id));
        self::assertSame(['id' => $id, 'label' => 'Four-lesson pack'], $props['offer']);
    }

    public function testAProductionLinkPrefillsTheServiceInTheVisitorsLanguage(): void
    {
        $client = self::createClient();
        self::switchSections(true, true, false);
        $service = self::getContainer()->get(AddProductionServiceHandler::class)(new AddProductionService(new ProductionServiceDetails(new LocalizedText('Mixage', 'Mixing'), new LocalizedText(), ServiceKind::Mix, Pricing::Fixed, new Price(25000))))->id;
        $offer = self::offer();

        $props = self::props($client->request('GET', '/fr/contact?topic=production&offer='.$service));
        self::assertSame('production', $props['topic']);
        self::assertSame(['id' => $service, 'label' => 'Mixage'], $props['offer']);

        $props = self::props($client->request('GET', '/contact?topic=production&offer='.$service));
        self::assertSame(['id' => $service, 'label' => 'Mixing'], $props['offer']);

        self::assertNull(self::props($client->request('GET', '/contact?topic=production&offer='.$offer))['offer']);
        self::assertNull(self::props($client->request('GET', '/contact?topic=lessons&offer='.$service))['offer']);
    }

    public function testAnUnknownOfferOrATopicThatIsNotOpenIsIgnored(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        $id = self::offer();

        foreach (['01JBN5Z7Q3X0000000000000AA', 'not-an-id'] as $unknown) {
            $props = self::props($client->request('GET', '/contact?topic=lessons&offer='.$unknown));
            self::assertSame('lessons', $props['topic']);
            self::assertNull($props['offer']);
        }

        $props = self::props($client->request('GET', '/contact?topic=general&offer='.$id));
        self::assertSame('general', $props['topic']);
        self::assertNull($props['offer']);

        foreach (['beats', 'merch', ''] as $topic) {
            $props = self::props($client->request('GET', '/contact?topic='.$topic.'&offer='.$id));
            self::assertSame('general', $props['topic']);
            self::assertNull($props['offer']);
        }
    }

    public function testTheOfferNeedsTheLessonsSectionOn(): void
    {
        $client = self::createClient();
        $id = self::offer();

        $props = self::props($client->request('GET', '/contact?topic=lessons&offer='.$id));

        self::assertSame('general', $props['topic']);
        self::assertNull($props['offer']);
    }

    public function testEveryPageLinksTheContactPageInTheNavAndTheFooter(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/fr');
        self::assertSame('/fr/contact', $crawler->filter('header nav a.nav-contact')->attr('href'));
        self::assertNull($crawler->filter('header nav a.nav-contact')->attr('aria-current'));
        self::assertSame('/fr/contact', $crawler->filter('footer a.footer-contact-link')->attr('href'));

        $crawler = $client->request('GET', '/contact');
        self::assertSame('page', $crawler->filter('header nav a.nav-contact')->attr('aria-current'));
        self::assertSame('Contact', $crawler->filter('header nav a.nav-contact')->text());
    }

    public function testWithoutJavascriptThePageOffersTheContactEmail(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/contact');
        self::assertCount(0, $crawler->filter('.contact-form a[href^="mailto:"]'));

        self::editGeneral(contactEmail: 'hello@injust.test');
        $crawler = $client->request('GET', '/contact');
        self::assertSame('mailto:hello@injust.test', $crawler->filter('.contact-form a[href^="mailto:"]')->attr('href'));
    }

    /** @return array<mixed> */
    private static function props(Crawler $crawler): array
    {
        return Json::decode((string) $crawler->filter('[data-island="ContactForm"]')->attr('data-props'));
    }

    private static function offer(): string
    {
        return self::getContainer()->get(AddLessonOfferHandler::class)(new AddLessonOffer(new LessonOfferDetails(
            new LocalizedText('Pack de quatre', 'Four-lesson pack'),
            new LocalizedText(),
            OfferKind::Pack,
            4,
            LessonFormat::Online,
            60,
            new Price(16000),
        )))->id;
    }
}
