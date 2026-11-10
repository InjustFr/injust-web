<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web\FakeCheckout;

use App\Tests\Support\Payments\SellsOnline;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FakeCheckoutTest extends WebTestCase
{
    use SellsOnline;

    public function testThePaymentPageShowsTheOrderAndPayingConfirmsIt(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        $id = $order->id()->toBase32();

        $page = $client->request('GET', '/_fake-checkout/'.$order->checkoutSessionId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', '45.00 EUR');
        self::assertSame('https://injust.test/fr/checkout/cancel?order='.$id, $page->selectLink('Cancel')->link()->getUri());

        $client->submitForm('Pay');

        self::assertResponseRedirects('https://injust.test/fr/checkout/success?order='.$id);
        $paid = self::stored($order);
        self::assertSame(['paid', 'Test Buyer', '2026-0001'], [$paid->status, $paid->customer?->name, $paid->invoiceNumber]);
        self::assertCount(1, self::mails()->sent);
    }

    public function testAFailingWebhookIsShownInsteadOfTheSuccessPage(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        self::mails()->failures = 1;

        $client->request('POST', '/_fake-checkout/'.$order->checkoutSessionId().'/pay');

        self::assertResponseStatusCodeSame(500);
        self::assertSame('paid', self::stored($order)->status);
    }

    public function testAnUnknownSessionIsNotFound(): void
    {
        $client = self::shop();

        $client->request('GET', '/_fake-checkout/cs_test_unknown');
        self::assertResponseStatusCodeSame(404);
        $client->request('POST', '/_fake-checkout/cs_test_unknown/pay');
        self::assertResponseStatusCodeSame(404);
    }
}
