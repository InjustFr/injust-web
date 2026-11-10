<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Contact;

use App\Application\Contact\ContactQueries;
use App\Application\Lessons\AddLessonOffer\AddLessonOffer;
use App\Application\Lessons\AddLessonOffer\AddLessonOfferHandler;
use App\Application\Production\AddProductionService\AddProductionService;
use App\Application\Production\AddProductionService\AddProductionServiceHandler;
use App\Application\Settings\EditGeneralSettings\EditGeneralSettings;
use App\Application\Settings\EditGeneralSettings\EditGeneralSettingsHandler;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class ContactApiTest extends WebTestCase
{
    private const array MESSAGE = [
        'name' => 'Joe Strummer',
        'email' => 'joe@example.test',
        'topic' => 'general',
        'body' => 'Can you play at our squat party?',
        'website' => '',
    ];

    public function testAMessageIsStoredThenNotifiedAndAcknowledged(): void
    {
        $client = self::visitor();
        self::contactEmail('hello@injust.test');

        $client->jsonRequest('POST', '/api/contact', self::MESSAGE);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('{}', $client->getResponse()->getContent());
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'));
        $messages = self::getContainer()->get(ContactQueries::class)->messages(true);
        self::assertCount(1, $messages);
        self::assertSame(['Joe Strummer', 'joe@example.test', 'general', 'Can you play at our squat party?', 'en', null, null, false], [
            $messages[0]->name, $messages[0]->email, $messages[0]->topic, $messages[0]->body, $messages[0]->locale, $messages[0]->offerRef, $messages[0]->offerLabel, $messages[0]->handled,
        ]);

        self::assertEmailCount(2);
        $notification = self::getMailerMessage(0);
        self::assertNotNull($notification);
        self::assertEmailAddressContains($notification, 'To', 'hello@injust.test');
        self::assertEmailAddressContains($notification, 'Reply-To', 'joe@example.test');
        self::assertEmailAddressContains($notification, 'From', 'noreply@injustmusic.com');
        self::assertEmailHeaderSame($notification, 'Subject', 'New message from Joe Strummer (General)');
        self::assertEmailTextBodyContains($notification, 'Can you play at our squat party?');
        self::assertEmailTextBodyContains($notification, 'joe@example.test');

        $acknowledgement = self::getMailerMessage(1);
        self::assertNotNull($acknowledgement);
        self::assertEmailAddressContains($acknowledgement, 'To', 'joe@example.test');
        self::assertEmailAddressContains($acknowledgement, 'From', 'noreply@injustmusic.com');
        self::assertEmailHeaderSame($acknowledgement, 'Subject', 'Injust: your message was received');
        self::assertInstanceOf(Email::class, $acknowledgement);
        self::assertSame('', $acknowledgement->getTo()[0]->getName());
        self::assertEmailTextBodyContains($acknowledgement, 'Thanks for your message.');
        self::assertEmailTextBodyNotContains($acknowledgement, 'Joe Strummer');
        self::assertEmailTextBodyNotContains($acknowledgement, 'squat party');
    }

    public function testTheVisitorIsAnsweredInTheirLanguage(): void
    {
        $client = self::visitor();

        $client->jsonRequest('POST', '/api/contact', self::MESSAGE, ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('fr', self::getContainer()->get(ContactQueries::class)->messages(true)[0]->locale);
        self::assertEmailCount(1);
        $acknowledgement = self::getMailerMessage(0);
        self::assertNotNull($acknowledgement);
        self::assertEmailHeaderSame($acknowledgement, 'Subject', 'Injust : votre message est bien arrivé');
        self::assertEmailTextBodyContains($acknowledgement, 'Merci pour votre message.');
        self::assertEmailTextBodyNotContains($acknowledgement, 'Joe Strummer');
    }

    public function testALessonOfferIsSnapshottedWithTheMessage(): void
    {
        $client = self::visitor();
        $offer = self::getContainer()->get(AddLessonOfferHandler::class)(new AddLessonOffer(new LessonOfferDetails(
            new LocalizedText('Cours d’essai', 'Trial lesson'),
            new LocalizedText(),
            OfferKind::Single,
            1,
            LessonFormat::Online,
            30,
            new Price(2000),
        )));
        self::contactEmail('hello@injust.test');

        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'topic' => 'lessons', 'offerRef' => $offer->id]);

        $message = self::getContainer()->get(ContactQueries::class)->messages(true)[0];
        self::assertSame(['lessons', $offer->id, 'Trial lesson'], [$message->topic, $message->offerRef, $message->offerLabel]);
        $notification = self::getMailerMessage(0);
        self::assertNotNull($notification);
        self::assertEmailHeaderSame($notification, 'Subject', 'New message from Joe Strummer (Lessons)');
        self::assertEmailTextBodyContains($notification, 'Trial lesson');
        $acknowledgement = self::getMailerMessage(1);
        self::assertNotNull($acknowledgement);
        self::assertEmailTextBodyContains($acknowledgement, 'About: Trial lesson');
    }

    public function testAProductionServiceIsSnapshottedInTheVisitorsLanguage(): void
    {
        $client = self::visitor();
        $service = self::service();

        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'topic' => 'production', 'offerRef' => $service], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        $message = self::getContainer()->get(ContactQueries::class)->messages(true)[0];
        self::assertSame(['production', $service, 'Matriçage'], [$message->topic, $message->offerRef, $message->offerLabel]);
    }

    public function testAnOfferFromAnotherTopicIsIgnored(): void
    {
        $client = self::visitor();
        $client->disableReboot();
        $service = self::service();
        $offer = self::getContainer()->get(AddLessonOfferHandler::class)(new AddLessonOffer(new LessonOfferDetails(new LocalizedText('Cours'), new LocalizedText(), OfferKind::Single, 1, LessonFormat::Online, 30, new Price(2000))))->id;

        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'topic' => 'lessons', 'offerRef' => $service]);
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'topic' => 'production', 'offerRef' => $offer]);
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'topic' => 'production']);

        $messages = self::getContainer()->get(ContactQueries::class)->messages(true);
        self::assertSame([[null, null], [null, null], [null, null]], array_map(static fn ($message): array => [$message->offerRef, $message->offerLabel], $messages));
    }

    public function testAnUnknownOrForeignOfferIsIgnored(): void
    {
        $client = self::visitor();
        $client->disableReboot();
        $offer = self::getContainer()->get(AddLessonOfferHandler::class)(new AddLessonOffer(new LessonOfferDetails(new LocalizedText('Cours'), new LocalizedText(), OfferKind::Single, 1, LessonFormat::Online, 30, new Price(2000))));

        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'topic' => 'lessons', 'offerRef' => '01JAAAAAAAAAAAAAAAAAAAAAAA']);
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'topic' => 'beats', 'offerRef' => $offer->id]);

        $messages = self::getContainer()->get(ContactQueries::class)->messages(true);
        self::assertSame([[null, null], [null, null]], array_map(static fn ($message): array => [$message->offerRef, $message->offerLabel], $messages));
    }

    public function testNoNotificationIsSentWithoutAContactEmail(): void
    {
        $client = self::visitor();
        self::contactEmail('');

        $client->jsonRequest('POST', '/api/contact', self::MESSAGE);

        self::assertResponseStatusCodeSame(201);
        self::assertEmailCount(1);
        $acknowledgement = self::getMailerMessage(0);
        self::assertNotNull($acknowledgement);
        self::assertEmailAddressContains($acknowledgement, 'To', 'joe@example.test');
    }

    public function testTheHoneypotPretendsToSucceed(): void
    {
        $client = self::visitor();
        self::contactEmail('hello@injust.test');

        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'website' => 'https://spam.test']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame([], self::getContainer()->get(ContactQueries::class)->messages(true));
        self::assertEmailCount(0);
    }

    public function testAMailFailureKeepsTheMessage(): void
    {
        $client = self::visitor();
        self::contactEmail('hello@injust.test');
        self::getContainer()->set(MailerInterface::class, new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new TransportException('SMTP down');
            }
        });

        $client->jsonRequest('POST', '/api/contact', self::MESSAGE);

        self::assertResponseStatusCodeSame(201);
        self::assertEmailCount(0);
        self::assertCount(1, self::getContainer()->get(ContactQueries::class)->messages(true));
    }

    public function testAnInvalidMessageIsRefusedFieldByField(): void
    {
        $client = self::visitor();

        $client->jsonRequest('POST', '/api/contact', ['name' => str_repeat('a', 101), 'email' => 'joe@', 'topic' => 'general', 'body' => 'Too short', 'offerRef' => str_repeat('a', 61), 'website' => '']);

        self::assertResponseStatusCodeSame(422);
        $problem = Json::decode((string) $client->getResponse()->getContent());
        $paths = array_map(static fn (mixed $violation): string => Json::string($violation, 'propertyPath'), Json::array($problem, 'violations'));
        sort($paths);
        self::assertSame(['body', 'email', 'name', 'offerRef'], $paths);
        self::assertSame([], self::getContainer()->get(ContactQueries::class)->messages(true));
    }

    public function testTheLengthLimitsAreInclusive(): void
    {
        $client = self::visitor();
        $client->disableReboot();

        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'name' => str_repeat('a', 100), 'email' => 'joe12@'.str_repeat('abcdefghi.', 17).'test', 'body' => str_repeat('b', 10)]);
        self::assertResponseStatusCodeSame(201);
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'body' => str_repeat('b', 5000), 'offerRef' => str_repeat('a', 60)]);
        self::assertResponseStatusCodeSame(201);
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'body' => str_repeat('b', 5001)]);
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'email' => 'joe123@'.str_repeat('abcdefghi.', 17).'test']);
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'name' => ' ', 'topic' => 'gossip']);
        self::assertResponseStatusCodeSame(422);

        self::assertCount(2, self::getContainer()->get(ContactQueries::class)->messages(true));
    }

    public function testControlCharactersAreRefusedButLineBreaksAndTabsAreKept(): void
    {
        $client = self::visitor();
        $client->disableReboot();

        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'name' => "Joe\r\nBcc: x@evil.test", 'body' => "hello world\u{0}"]);
        self::assertResponseStatusCodeSame(422);
        $paths = array_map(static fn (mixed $violation): string => Json::string($violation, 'propertyPath'), Json::array(Json::decode((string) $client->getResponse()->getContent()), 'violations'));
        sort($paths);
        self::assertSame(['body', 'name'], $paths);
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'name' => "Joe\tStrummer"]);
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'body' => "hello world\x7F"]);
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'body' => "Hello,\r\n\tcan you play?\nThanks"]);
        self::assertResponseStatusCodeSame(201);

        self::assertSame(["Hello,\r\n\tcan you play?\nThanks"], array_map(static fn ($message): string => $message->body, self::getContainer()->get(ContactQueries::class)->messages(true)));
    }

    public function testAnAddressGetsThreeAcknowledgementsADay(): void
    {
        $client = self::visitor();
        $client->disableReboot();
        self::contactEmail('');

        foreach (['198.51.100.1', '198.51.100.2', '198.51.100.3', '198.51.100.4'] as $i => $ip) {
            $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'email' => 0 === $i % 2 ? 'joe@example.test' : 'JOE@example.test'], ['REMOTE_ADDR' => $ip]);
            self::assertResponseStatusCodeSame(201);
            self::assertEmailCount($i < 3 ? 1 : 0);
        }
        $client->jsonRequest('POST', '/api/contact', [...self::MESSAGE, 'email' => 'mick@example.test'], ['REMOTE_ADDR' => '198.51.100.5']);

        self::assertEmailCount(1);
        self::assertCount(5, self::getContainer()->get(ContactQueries::class)->messages(true));
    }

    public function testAnIpv6NetworkSharesOneBucket(): void
    {
        $client = self::visitor();
        $client->disableReboot();

        for ($i = 1; $i <= 5; ++$i) {
            self::post($client, '2001:db8:1:2::'.$i);
            self::assertResponseStatusCodeSame(201);
        }
        self::post($client, '2001:db8:1:2:ffff:ffff:ffff:ffff');
        self::assertResponseStatusCodeSame(429);

        self::post($client, '2001:db8:1:3::1');
        self::assertResponseStatusCodeSame(201);
    }

    public function testTheBucketFollowsTheAddressTheProxySaw(): void
    {
        $client = self::visitor();
        $client->disableReboot();
        $proxies = Request::getTrustedProxies();
        Request::setTrustedProxies(['private_ranges'], Request::HEADER_X_FORWARDED_FOR);

        try {
            for ($i = 1; $i <= 5; ++$i) {
                $client->jsonRequest('POST', '/api/contact', self::MESSAGE, ['REMOTE_ADDR' => '172.18.0.2', 'HTTP_X_FORWARDED_FOR' => \sprintf('192.0.2.%d, 203.0.113.50', $i)]);
                self::assertResponseStatusCodeSame(201);
            }
            $client->jsonRequest('POST', '/api/contact', self::MESSAGE, ['REMOTE_ADDR' => '172.18.0.2', 'HTTP_X_FORWARDED_FOR' => '192.0.2.99, 203.0.113.50']);
            self::assertResponseStatusCodeSame(429);
            $client->jsonRequest('POST', '/api/contact', self::MESSAGE, ['REMOTE_ADDR' => '172.18.0.2', 'HTTP_X_FORWARDED_FOR' => '203.0.113.51']);
            self::assertResponseStatusCodeSame(201);
        } finally {
            Request::setTrustedProxies($proxies, Request::HEADER_X_FORWARDED_FOR);
        }
    }

    public function testAVisitorSendsFiveMessagesPerTenMinutes(): void
    {
        $client = self::visitor();
        $client->disableReboot();

        for ($i = 0; $i < 5; ++$i) {
            self::post($client, '203.0.113.7');
            self::assertResponseStatusCodeSame(201);
        }
        self::post($client, '203.0.113.7');

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertStringContainsString('10 minutes', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
        self::assertCount(5, self::getContainer()->get(ContactQueries::class)->messages(true));

        self::post($client, '203.0.113.8');
        self::assertResponseStatusCodeSame(201);
    }

    public function testACrossOriginPostIsRefused(): void
    {
        $client = self::visitor();

        $client->jsonRequest('POST', '/api/contact', self::MESSAGE, ['HTTP_ORIGIN' => 'https://evil.test']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], self::getContainer()->get(ContactQueries::class)->messages(true));
    }

    private static function visitor(): KernelBrowser
    {
        $client = self::createClient();
        self::getContainer()->get('cache.contact_limiter')->clear();

        return $client;
    }

    private static function post(KernelBrowser $client, string $ip): void
    {
        $client->jsonRequest('POST', '/api/contact', self::MESSAGE, ['REMOTE_ADDR' => $ip]);
    }

    private static function contactEmail(string $email): void
    {
        self::getContainer()->get(EditGeneralSettingsHandler::class)(new EditGeneralSettings($email, null, new VatMentions('A', 'B', 'C'), new LocalizedText(), new LocalizedText(), false, ''));
    }

    private static function service(): string
    {
        return self::getContainer()->get(AddProductionServiceHandler::class)(new AddProductionService(new ProductionServiceDetails(new LocalizedText('Matriçage', 'Mastering'), new LocalizedText(), ServiceKind::Master, Pricing::Quote, new Price(0))))->id;
    }
}
