<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WithdrawalPageTest extends WebTestCase
{
    use ConfiguresSite;

    public function testThePageExplainsTheRightAndMountsTheTwoStepForm(): void
    {
        $client = self::createClient();
        self::editGeneral();

        $crawler = $client->request('GET', '/withdrawal');

        self::assertResponseIsSuccessful();
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
        self::assertSame('Withdrawal', $crawler->filter('main h1')->text());
        self::assertSame('/fr/withdrawal', $crawler->filter('a.lang-switch')->attr('href'));
        $island = $crawler->filter('[data-island="WithdrawalForm"]');
        self::assertCount(1, $island);
        $props = Json::decode((string) $island->attr('data-props'));
        self::assertSame(['/api/withdrawal/preview', '/api/withdrawal', 'en'], [$props['previewEndpoint'], $props['endpoint'], $props['locale']]);
        self::assertSame('Confirm my withdrawal', Json::string($props, 'labels', 'confirm'));
        self::assertStringContainsString('hello@injust.test', $island->text());
    }

    public function testTheFrenchPageKeepsTheEnglishSlug(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/fr/withdrawal');

        self::assertResponseIsSuccessful();
        self::assertSame('Rétractation', $crawler->filter('main h1')->text());
        self::assertSame('Confirmer ma rétractation', Json::string(Json::decode((string) $crawler->filter('[data-island="WithdrawalForm"]')->attr('data-props')), 'labels', 'confirm'));
    }

    public function testTheFooterOffersTheWithdrawalFunctionEvenWithCheckoutOffSoRecentBuyersKeepIt(): void
    {
        $client = self::createClient();

        $english = $client->request('GET', '/')->filter('footer a.footer-withdrawal');
        $french = $client->request('GET', '/fr')->filter('footer a.footer-withdrawal');

        self::assertSame(['Withdraw from a contract here', '/withdrawal'], [$english->text(), $english->attr('href')]);
        self::assertSame(['Renoncer au contrat ici', '/fr/withdrawal'], [$french->text(), $french->attr('href')]);
        $client->request('GET', '/withdrawal');
        self::assertResponseIsSuccessful();
    }
}
