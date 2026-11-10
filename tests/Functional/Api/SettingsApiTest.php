<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Settings\SettingsQueries;
use App\Domain\Settings\Section;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SettingsApiTest extends WebTestCase
{
    use SignsInClient;

    private const array IDENTITY = [
        'name' => 'Louis Injust',
        'businessForm' => 'EI',
        'siren' => '443 061 841',
        'address' => '1 rue du Son, 75000 Paris',
        'email' => 'louis@injust.test',
        'phone' => '+33 6 00 00 00 00',
        'publicationDirector' => 'Louis Injust',
        'mediatorName' => 'Médiateur Test',
        'mediatorUrl' => 'https://mediator.test',
        'hostName' => 'OVH SAS',
        'hostAddress' => '2 rue Kellermann, 59100 Roubaix',
        'hostPhone' => '+33 9 72 10 10 07',
    ];

    private const array GENERAL = [
        'contactEmail' => 'hello@injust.test',
        'bookingUrl' => 'https://cal.test/injust',
        'vatMentions' => ['consumer' => 'A', 'euBusiness' => 'B', 'nonEuBusiness' => 'C'],
        'seoTitle' => ['fr' => 'Injust — punk', 'en' => 'Injust — punk'],
        'seoDescription' => ['fr' => '', 'en' => 'Protest punk.'],
        'searchIndexing' => true,
    ];

    protected function tearDown(): void
    {
        new Filesystem()->remove(self::$kernel?->getShareDir() ?? []);
        parent::tearDown();
    }

    public function testTheSettingsStartWithEverythingOff(): void
    {
        $client = self::signedInClient();

        $settings = self::send($client, 'GET', '/api/admin/settings');

        self::assertSame(['lessons' => false, 'production' => false, 'beats' => false], Json::array($settings, 'sections'));
        self::assertFalse(Json::at($settings, 'onlineCheckout'));
        self::assertFalse(Json::at($settings, 'searchIndexing'));
        self::assertSame('EI', Json::string($settings, 'legal', 'businessForm'));
        self::assertSame('TVA non applicable – art. 293 B du CGI', Json::string($settings, 'vatMentions', 'consumer'));
        self::assertSame(['fr' => '', 'en' => ''], Json::array($settings, 'seoTitle'));
        self::assertNull(Json::at($settings, 'logoImage'));
    }

    public function testSectionsAreSwitched(): void
    {
        $client = self::signedInClient();

        $settings = self::send($client, 'PUT', '/api/admin/settings/sections', ['lessons' => true, 'production' => false, 'beats' => true]);

        self::assertSame(['lessons' => true, 'production' => false, 'beats' => true], Json::array($settings, 'sections'));
        $view = self::getContainer()->get(SettingsQueries::class)->current();
        self::assertSame([Section::Lessons, Section::Beats], $view->enabledSections());
        self::assertTrue($view->isEnabled(Section::Beats));
    }

    public function testCheckoutNeedsTheFullLegalIdentity(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/settings/checkout', ['enabled' => true]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('legal identity', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
    }

    public function testCheckoutNeedsTheHostPhone(): void
    {
        $client = self::signedInClient();
        $settings = self::send($client, 'PUT', '/api/admin/settings/legal-identity', array_diff_key(self::IDENTITY, ['hostPhone' => true]));
        self::assertSame(['', false], [Json::string($settings, 'legal', 'hostPhone'), Json::at($settings, 'legal', 'complete')]);

        $client->jsonRequest('PUT', '/api/admin/settings/checkout', ['enabled' => true]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCheckoutIsSwitchedOnOnceTheIdentityIsFilled(): void
    {
        $client = self::signedInClient();

        $settings = self::send($client, 'PUT', '/api/admin/settings/legal-identity', self::IDENTITY);
        self::assertSame(['443061841', '+33 9 72 10 10 07', true], [Json::string($settings, 'legal', 'siren'), Json::string($settings, 'legal', 'hostPhone'), Json::at($settings, 'legal', 'complete')]);

        $settings = self::send($client, 'PUT', '/api/admin/settings/checkout', ['enabled' => true]);
        self::assertTrue(Json::at($settings, 'onlineCheckout'));
        self::assertTrue(self::getContainer()->get(SettingsQueries::class)->current()->onlineCheckout);

        $settings = self::send($client, 'PUT', '/api/admin/settings/checkout', ['enabled' => false]);
        self::assertFalse(Json::at($settings, 'onlineCheckout'));
    }

    public function testAnInvalidSirenIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/settings/legal-identity', ['siren' => '443061842'] + self::IDENTITY);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('SIREN', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
    }

    public function testTheVatNumberIsOptionalAndSavedInCapitals(): void
    {
        $client = self::signedInClient();

        $settings = self::send($client, 'PUT', '/api/admin/settings/legal-identity', self::IDENTITY);
        self::assertSame(['', true], [Json::string($settings, 'legal', 'vatNumber'), Json::at($settings, 'legal', 'complete')]);

        $settings = self::send($client, 'PUT', '/api/admin/settings/legal-identity', ['vatNumber' => 'fr 12 443061841'] + self::IDENTITY);
        self::assertSame('FR12443061841', Json::string($settings, 'legal', 'vatNumber'));
        self::assertSame('FR12443061841', self::getContainer()->get(SettingsQueries::class)->current()->legal->vatNumber);
    }

    public function testAnInvalidVatNumberIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/settings/legal-identity', ['vatNumber' => 'FR-12'] + self::IDENTITY);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('FR-12', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
    }

    public function testAnInvalidIdentityEmailIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/settings/legal-identity', ['email' => 'not-an-email'] + self::IDENTITY);

        self::assertResponseStatusCodeSame(422);
    }

    public function testTheGeneralSettingsAreSaved(): void
    {
        $client = self::signedInClient();

        $settings = self::send($client, 'PUT', '/api/admin/settings/general', self::GENERAL);

        self::assertSame(['hello@injust.test', 'https://cal.test/injust', true], [Json::string($settings, 'contactEmail'), Json::string($settings, 'bookingUrl'), Json::at($settings, 'searchIndexing')]);
        self::assertSame(['consumer' => 'A', 'euBusiness' => 'B', 'nonEuBusiness' => 'C'], Json::array($settings, 'vatMentions'));
        $view = self::getContainer()->get(SettingsQueries::class)->current();
        self::assertSame('Protest punk.', $view->seoDescription->in('fr'));
    }

    public function testTheGoogleVerificationTokenIsSavedAndRead(): void
    {
        $client = self::signedInClient();
        self::assertSame('', Json::string(self::send($client, 'GET', '/api/admin/settings'), 'googleSiteVerification'));

        $settings = self::send($client, 'PUT', '/api/admin/settings/general', ['googleSiteVerification' => ' abc_DEF-123456 '] + self::GENERAL);

        self::assertSame('abc_DEF-123456', Json::string($settings, 'googleSiteVerification'));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame('abc_DEF-123456', self::getContainer()->get(SettingsQueries::class)->current()->googleSiteVerification);
        self::assertSame('abc_DEF-123456', Json::string(self::send($client, 'GET', '/api/admin/settings'), 'googleSiteVerification'));
    }

    public function testAVerificationTagInsteadOfItsTokenIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/settings/general', ['googleSiteVerification' => '<meta name="google-site-verification" content="abc_DEF-123456">'] + self::GENERAL);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('token', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
    }

    public function testAnInsecureBookingUrlOrABlankVatMentionIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/settings/general', ['bookingUrl' => 'http://cal.test'] + self::GENERAL);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('PUT', '/api/admin/settings/general', ['vatMentions' => ['consumer' => ' ', 'euBusiness' => 'B', 'nonEuBusiness' => 'C']] + self::GENERAL);
        self::assertResponseStatusCodeSame(422);
    }

    public function testEveryWriteIsStored(): void
    {
        $client = self::signedInClient();
        self::upload($client, 'hero');
        self::send($client, 'PUT', '/api/admin/settings/legal-identity', self::IDENTITY);
        self::send($client, 'PUT', '/api/admin/settings/checkout', ['enabled' => true]);
        self::send($client, 'PUT', '/api/admin/settings/general', self::GENERAL);
        self::send($client, 'DELETE', '/api/admin/settings/images/hero');

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SettingsQueries::class)->current();

        self::assertSame([true, 'https://cal.test/injust', true, null], [$stored->onlineCheckout, $stored->bookingUrl, $stored->searchIndexing, $stored->heroImage]);
    }

    public function testAFlagLeftOutOfTheRequestIsSwitchedOff(): void
    {
        $client = self::signedInClient();
        self::send($client, 'PUT', '/api/admin/settings/sections', ['lessons' => true, 'production' => true, 'beats' => true]);
        self::send($client, 'PUT', '/api/admin/settings/legal-identity', self::IDENTITY);
        self::send($client, 'PUT', '/api/admin/settings/checkout', ['enabled' => true]);
        self::send($client, 'PUT', '/api/admin/settings/general', self::GENERAL);

        self::send($client, 'PUT', '/api/admin/settings/sections', []);
        self::send($client, 'PUT', '/api/admin/settings/checkout', []);
        $settings = self::send($client, 'PUT', '/api/admin/settings/general', array_diff_key(self::GENERAL, ['searchIndexing' => true]));

        self::assertSame(['lessons' => false, 'production' => false, 'beats' => false], Json::array($settings, 'sections'));
        self::assertSame([false, false], [Json::at($settings, 'onlineCheckout'), Json::at($settings, 'searchIndexing')]);
    }

    public function testATextLongerThanItsColumnIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/settings/legal-identity', ['phone' => str_repeat('1', 41)] + self::IDENTITY);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('PUT', '/api/admin/settings/legal-identity', ['hostPhone' => str_repeat('1', 41)] + self::IDENTITY);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('PUT', '/api/admin/settings/general', ['vatMentions' => ['consumer' => str_repeat('a', 256), 'euBusiness' => 'B', 'nonEuBusiness' => 'C']] + self::GENERAL);
        self::assertResponseStatusCodeSame(422);

        self::send($client, 'PUT', '/api/admin/settings/legal-identity', ['phone' => str_repeat('1', 40), 'hostName' => str_repeat('h', 255), 'hostPhone' => str_repeat('1', 40)] + self::IDENTITY);
    }

    public function testAnUploadWithoutAFileIsRefused(): void
    {
        $client = self::signedInClient();

        $client->request('POST', '/api/admin/settings/images/logo', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testTheLogoAndHeroAreUploadedAndReplaced(): void
    {
        $client = self::signedInClient();

        $first = Json::string(self::upload($client, 'logo'), 'logoImage');
        $second = Json::string(self::upload($client, 'logo'), 'logoImage');
        $hero = self::upload($client, 'hero');

        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $second);
        self::assertSame([$second, $second], [Json::string($hero, 'logoImage'), self::getContainer()->get(SettingsQueries::class)->current()->logoImage]);
        self::assertNotNull(Json::at($hero, 'heroImage'));
        $client->request('GET', '/media/images/'.$first.'.webp');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/media/images/'.$second.'.webp');
        self::assertResponseIsSuccessful();
    }

    public function testAnImageIsRemoved(): void
    {
        $client = self::signedInClient();
        $hero = Json::string(self::upload($client, 'hero'), 'heroImage');

        $settings = self::send($client, 'DELETE', '/api/admin/settings/images/hero');

        self::assertNull(Json::at($settings, 'heroImage'));
        $client->request('GET', '/media/images/'.$hero.'.webp');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAFileThatIsNotAnImageIsRefused(): void
    {
        $client = self::signedInClient();
        $copy = self::copyOf('not-an-image.png');

        $client->request('POST', '/api/admin/settings/images/logo', files: ['file' => new UploadedFile($copy, 'logo.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testVisitorsCannotReadTheSettings(): void
    {
        $client = self::createClient();

        $client->jsonRequest('GET', '/api/admin/settings');

        self::assertResponseStatusCodeSame(401);
    }

    public function testWritesFromAnotherOriginAreRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/settings/sections', ['lessons' => true, 'production' => true, 'beats' => true], ['HTTP_ORIGIN' => 'https://evil.test']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAWriteReturnsTheRefreshedSettings(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/settings/sections', ['lessons' => true, 'production' => false, 'beats' => false], ['HTTP_X_REFRESH' => '["/api/admin/settings"]']);

        self::assertResponseIsSuccessful();
        self::assertTrue(Json::at(Json::decode((string) $client->getResponse()->getContent()), 'refreshed', '/api/admin/settings', 'sections', 'lessons'));
    }

    /**
     * @param array<mixed> $body
     *
     * @return array<mixed>
     */
    private static function send(KernelBrowser $client, string $method, string $url, array $body = []): array
    {
        $client->jsonRequest($method, $url, $body);
        self::assertResponseIsSuccessful();

        return Json::decode((string) $client->getResponse()->getContent());
    }

    /**
     * @return array<mixed>
     */
    private static function upload(KernelBrowser $client, string $slot): array
    {
        $client->request('POST', '/api/admin/settings/images/'.$slot, files: ['file' => new UploadedFile(self::copyOf('large-fern.png'), 'image.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        return Json::decode((string) $client->getResponse()->getContent());
    }

    private static function copyOf(string $fixture): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'image');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 2).'/Fixtures/'.$fixture, $copy);

        return $copy;
    }
}
