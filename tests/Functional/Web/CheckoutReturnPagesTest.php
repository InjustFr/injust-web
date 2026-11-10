<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Transaction;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Tests\Support\Sales\SalesExamples;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class CheckoutReturnPagesTest extends WebTestCase
{
    use ConfiguresSite;

    public function testThePaidOrderIsConfirmedWithItsReferenceOnly(): void
    {
        $client = self::createClient();
        self::editGeneral(true);
        $order = self::store(SalesExamples::paid());

        $crawler = $client->request('GET', '/checkout/success?order='.$order->id()->toBase32());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
        self::assertSame('Thank you', $crawler->filter('main h1')->text());
        self::assertSame($order->reference(), $crawler->filter('.checkout-reference')->text());
        self::assertSame('Payment confirmed. Your confirmation email, with the invoice and the terms of sale, is on its way.', $crawler->filter('.checkout-state')->text());
        self::assertCount(0, $crawler->filter('.checkout-refresh'));
        self::assertSame('/fr/checkout/success?order='.$order->id()->toBase32(), $crawler->filter('a.lang-switch')->attr('href'));
        self::assertStringNotContainsString('stripe@example.test', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('Test Buyer', (string) $client->getResponse()->getContent());
    }

    public function testAPendingOrderIsStillProcessingAndCanBeRefreshed(): void
    {
        $client = self::createClient();
        $order = self::store(SalesExamples::order());
        $id = $order->id()->toBase32();

        $crawler = $client->request('GET', '/fr/checkout/success?order='.$id);

        self::assertSame('Merci', $crawler->filter('main h1')->text());
        self::assertSame($order->reference(), $crawler->filter('.checkout-reference')->text());
        self::assertSame('Paiement en cours de traitement. Vous recevrez un e-mail dès qu’il sera confirmé.', $crawler->filter('.checkout-state')->text());
        self::assertSame('/fr/checkout/success?order='.$id, $crawler->filter('a.checkout-refresh')->attr('href'));
        self::assertStringNotContainsString('buyer@example.test', (string) $client->getResponse()->getContent());
    }

    public function testAnExpiredOrRefundedOrderIsNeverShownAsPaid(): void
    {
        $client = self::createClient();
        $expired = SalesExamples::order();
        $expired->expire();
        $refunded = SalesExamples::paid(session: 'cs_refunded');
        $refunded->refund();

        foreach ([self::store($expired), self::store($refunded)] as $order) {
            $crawler = $client->request('GET', '/checkout/success?order='.$order->id()->toBase32());

            self::assertSame('No payment is pending for this order: it has expired or been refunded.', $crawler->filter('.checkout-state')->text());
            self::assertCount(0, $crawler->filter('.checkout-state.is-paid, .checkout-refresh'));
            self::assertSame('/checkout/lesson/01JCLESSON0000000000000000', $crawler->filter('.checkout-actions a')->attr('href'));
        }
    }

    public function testTheCancelPageSaysNothingWasChargedAndLeadsBackToTheProduct(): void
    {
        $client = self::createClient();
        self::editGeneral(true);
        $order = self::store(SalesExamples::order(SalesExamples::production()));

        $crawler = $client->request('GET', '/checkout/cancel?order='.$order->id()->toBase32());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('Payment cancelled', $crawler->filter('main h1')->text());
        self::assertSame('Nothing was charged and no order was placed.', $crawler->filter('.checkout-state')->text());
        self::assertSame(['/checkout/production/01JCSERVICE000000000000000', '/production'], $crawler->filter('.checkout-actions a')->each(static fn (Crawler $link): ?string => $link->attr('href')));
        self::assertSame('Back to the offer', $crawler->filter('.checkout-actions a')->first()->text());
    }

    public function testTheFrenchCancelPageLeadsBackToTheFrenchLessons(): void
    {
        $client = self::createClient();
        $order = self::store(SalesExamples::order());

        $crawler = $client->request('GET', '/fr/checkout/cancel?order='.$order->id()->toBase32());

        self::assertSame(['/fr/checkout/lesson/01JCLESSON0000000000000000', '/fr/lessons'], $crawler->filter('.checkout-actions a')->each(static fn (Crawler $link): ?string => $link->attr('href')));
    }

    public function testAnUnknownOrderIsNotFound(): void
    {
        $client = self::createClient();

        foreach (['/checkout/success', '/checkout/success?order=nope', '/checkout/success?order=01JBN5Z7Q3X0000000000000AA', '/checkout/cancel?order=01JBN5Z7Q3X0000000000000AA', '/fr/checkout/cancel'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
    }

    private static function store(Order $order): Order
    {
        self::getContainer()->get(OrderRepository::class)->add($order);
        self::getContainer()->get(Transaction::class)->commit();

        return $order;
    }
}
