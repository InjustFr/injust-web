<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Orders;

use App\Application\Sales\InvoiceView;
use App\Application\Sales\SalesDocuments;
use App\Application\Sales\SalesMails;
use App\Application\Settings\LegalDocumentView;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\Order;
use App\Domain\Sales\Purchasable;
use App\Domain\Sales\Seller;
use App\Domain\Settings\LegalIdentity;
use App\Domain\Settings\VatMentions;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineInvoiceRepository;
use App\Infrastructure\Persistence\Doctrine\Sales\DoctrineOrderRepository;
use App\Tests\Support\Json;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\SalesExamples;
use App\Tests\Support\SignsInClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class OrdersApiTest extends WebTestCase
{
    use SignsInClient;

    public function testOrdersAreListedNewestFirstAndFilteredByStatusAndYear(): void
    {
        $client = self::signedInClient();
        $old = self::sell(SalesExamples::lesson(), '2025-06-01 10:00:00', 'cs_old', '2025-0001');
        $new = self::sell(SalesExamples::production(), '2026-06-01 10:00:00', 'cs_new', '2026-0001', 'DE');
        $pending = SalesExamples::order(at: '2026-07-01 10:00:00');
        self::store($pending);

        self::assertSame([$pending->id()->toBase32(), $new->id()->toBase32(), $old->id()->toBase32()], self::ids(self::get($client, '/api/admin/orders')));
        self::assertSame([$new->id()->toBase32()], self::ids(self::get($client, '/api/admin/orders?status=paid&year=2026')));
        self::assertSame([$old->id()->toBase32()], self::ids(self::get($client, '/api/admin/orders?year=2025')));
        self::assertSame([$pending->id()->toBase32()], self::ids(self::get($client, '/api/admin/orders?status=pending')));
    }

    public function testAnOrderIsShownWithItsCustomerAndConsents(): void
    {
        $client = self::signedInClient();
        $order = self::sell(SalesExamples::lesson(4500, 'online'), '2026-06-01 10:00:00', 'cs_a', '2026-0001', 'DE', 'Riot GmbH', 'DE123456789');

        $body = self::get($client, '/api/admin/orders/'.$order->id()->toBase32());

        self::assertSame($order->reference(), Json::at($body, 'reference'));
        self::assertSame(['kind' => 'terms', 'text' => 'Texte terms', 'acceptedAt' => '2026-06-01T10:00:00+00:00'], Json::at($body, 'consents', 0));
        self::assertSame(3, Json::at($body, 'cgvVersion'));
        self::assertSame(4500, Json::at($body, 'amountCents'));
        self::assertSame('online', Json::at($body, 'productFormat'));
        self::assertSame('2026-0001', Json::at($body, 'invoiceNumber'));
        self::assertSame(['name' => 'Test Buyer', 'email' => 'stripe@example.test', 'country' => 'DE', 'line1' => '1 rue du Son', 'line2' => '', 'postalCode' => '75000', 'city' => 'Paris', 'state' => null, 'company' => 'Riot GmbH', 'vatId' => 'DE123456789', 'cardCountry' => 'FR'], Json::at($body, 'customer'));
    }

    public function testAnUnpaidOrderHasNoCustomer(): void
    {
        $client = self::signedInClient();
        $order = SalesExamples::order();
        self::store($order);

        $body = self::get($client, '/api/admin/orders/'.$order->id()->toBase32());

        self::assertNull(Json::at($body, 'customer'));
        self::assertNull(Json::at($body, 'invoiceNumber'));
    }

    public function testTheInvoiceIsServedInline(): void
    {
        $client = self::signedInClient();
        $order = self::sell(SalesExamples::lesson(), '2026-06-01 10:00:00', 'cs_a', '2026-0001');
        self::getContainer()->set(SalesDocuments::class, new class implements SalesDocuments {
            public function invoicePdf(InvoiceView $invoice): string
            {
                return '%PDF '.$invoice->number;
            }

            public function legalPdf(LegalDocumentView $document, string $locale): string
            {
                return '';
            }
        });

        $client->request('GET', '/api/admin/orders/'.$order->id()->toBase32().'/invoice');

        self::assertResponseIsSuccessful();
        self::assertSame('%PDF 2026-0001', $client->getResponse()->getContent());
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringContainsString('inline', (string) $client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('invoice-2026-0001.pdf', (string) $client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testAnOrderWithoutInvoiceHasNoPdf(): void
    {
        $client = self::signedInClient();
        $order = SalesExamples::order();
        self::store($order);

        $client->request('GET', '/api/admin/orders/'.$order->id()->toBase32().'/invoice');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnUnknownOrderIsNotFound(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/api/admin/orders/'.new Ulid()->toBase32());

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheConfirmationIsSentAgain(): void
    {
        $client = self::signedInClient();
        $order = self::sell(SalesExamples::lesson(), '2026-06-01 10:00:00', 'cs_a', '2026-0001');
        $mails = new RecordingSalesMails();
        self::getContainer()->set(SalesMails::class, $mails);

        $client->jsonRequest('POST', '/api/admin/orders/'.$order->id()->toBase32().'/resend');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $mails->sent);
        self::assertNotNull(Json::at(Json::decode((string) $client->getResponse()->getContent()), 'confirmationSentAt'));
    }

    public function testAnUnpaidOrderCannotBeResent(): void
    {
        $client = self::signedInClient();
        $order = SalesExamples::order();
        self::store($order);
        self::getContainer()->set(SalesMails::class, new RecordingSalesMails());

        $client->jsonRequest('POST', '/api/admin/orders/'.$order->id()->toBase32().'/resend');

        self::assertResponseStatusCodeSame(422);
    }

    public function testTheRevenueBookIsExportedAsAnExcelFriendlyCsv(): void
    {
        $client = self::signedInClient();
        self::sell(SalesExamples::lesson(4500), '2026-06-01 10:00:00', 'cs_a', '2026-0001', name: '=HYPERLINK("x")');
        self::sell(SalesExamples::production(12050), '2025-06-01 10:00:00', 'cs_b', '2025-0001');

        $client->request('GET', '/api/admin/orders/export?year=2026');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('revenue-book-2026.csv', (string) $client->getResponse()->headers->get('Content-Disposition'));
        $content = (string) $client->getResponse()->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        $rows = array_map(static fn (string $line): array => str_getcsv($line, ';', escape: ''), explode("\r\n", trim(substr($content, 3))));
        self::assertCount(2, $rows);
        self::assertSame(['date', 'invoice', 'reference', 'customer', 'country', 'type', 'amount', 'payment'], $rows[0]);
        self::assertSame(['2026-06-01', '2026-0001'], \array_slice($rows[1], 0, 2));
        self::assertSame(["'=HYPERLINK(\"x\")", 'FR', 'lesson', '45,00', 'card'], \array_slice($rows[1], 3));
    }

    public function testTheTotalsAreReturnedForTheYear(): void
    {
        $client = self::signedInClient();
        self::sell(SalesExamples::lesson(4500, 'online'), '2026-06-01 10:00:00', 'cs_a', '2026-0001', 'DE');
        self::sell(SalesExamples::lesson(4000, 'online'), '2025-06-01 10:00:00', 'cs_b', '2025-0001', 'DE');

        $body = self::get($client, '/api/admin/orders/totals?year=2026');

        self::assertSame([['country' => 'DE', 'productType' => 'lesson', 'amountCents' => 4500, 'count' => 1]], Json::at($body, 'byCountryAndType'));
        self::assertSame(4500, Json::at($body, 'euConsumerOnlineLessonsCents'));
        self::assertSame(0, Json::at($body, 'euConsumerDigitalCrossBorderCents'));
        self::assertSame(1_000_000, Json::at($body, 'euDigitalThresholdCents'));
    }

    public function testAnInvalidFilterIsRefused(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/api/admin/orders?status=nope');
        self::assertResponseStatusCodeSame(422);
        $client->request('GET', '/api/admin/orders/totals?year=abc');
        self::assertResponseStatusCodeSame(422);
    }

    public function testVisitorsCannotReadOrders(): void
    {
        $client = self::createClient();

        foreach (['/api/admin/orders', '/api/admin/orders/export', '/api/admin/orders/totals'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(401);
        }
    }

    private static function sell(Purchasable $product, string $at, string $session, string $number, string $country = 'FR', ?string $company = null, ?string $vatId = null, string $name = 'Test Buyer'): Order
    {
        $order = SalesExamples::paid($product, SalesExamples::customer($country, $vatId, $company, $name), $at, $session);
        $order->invoiced($number);
        self::store($order);
        $seller = Seller::of(new LegalIdentity('Louis Injust', 'EI', '443061841', '1 rue du Son', 'louis@injust.test', '+33600000000', 'Louis', 'Médiateur', 'https://mediator.test', 'OVH', 'Roubaix', '+33972101007'));
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        new DoctrineInvoiceRepository($manager)->add(Invoice::issue($number, $order, $seller, VatMentions::defaults(), new \DateTimeImmutable($at)));
        $manager->flush();

        return $order;
    }

    private static function store(Order $order): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        new DoctrineOrderRepository($manager)->add($order);
        $manager->flush();
    }

    /**
     * @param array<mixed> $orders
     *
     * @return list<string>
     */
    private static function ids(array $orders): array
    {
        return array_values(array_map(static fn (mixed $order): string => Json::string($order, 'id'), $orders));
    }

    /**
     * @return array<mixed>
     */
    private static function get(KernelBrowser $client, string $url): array
    {
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return Json::decode((string) $client->getResponse()->getContent());
    }
}
