<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Orders;

use App\Application\Sales\SalesMails;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\Order;
use App\Domain\Sales\Seller;
use App\Domain\Settings\VatMentions;
use App\Tests\Support\Json;
use App\Tests\Support\Sales\DocumentExamples;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\SalesExamples;
use App\Tests\Support\SignsInClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BeatOrdersApiTest extends WebTestCase
{
    use SignsInClient;

    public function testABeatOrderShowsItsLicenceItsConflictAndFreshDownloadLinks(): void
    {
        $client = self::signedInClient();
        $order = SalesExamples::paid(SalesExamples::beat(49900, true), session: 'cs_beat');
        $order->flagExclusiveConflict(new \DateTimeImmutable('2026-11-10 10:06:00 UTC'));
        self::store($order);
        $id = $order->id()->toBase32();

        $body = self::get($client, '/api/admin/orders/'.$id);

        self::assertSame(['beatTitle' => 'Riot', 'tierName' => 'Premium FR', 'exclusive' => true, 'deliverables' => ['mp3', 'wav']], Json::at($body, 'licence'));
        self::assertSame('2026-11-10T10:06:00+00:00', Json::at($body, 'exclusiveConflictAt'));
        $downloads = Json::array($body, 'downloads');
        self::assertSame(['mp3', 'wav'], array_map(static fn (mixed $link): string => Json::string($link, 'deliverable'), $downloads));
        self::assertMatchesRegularExpression('#^http://localhost/download/'.$id.'/mp3\?_expiration=\d+&_hash=[\w-]+$#', Json::string($downloads[0], 'url'));
        self::assertEqualsWithDelta(time() + 7 * 86400, new \DateTimeImmutable(Json::string($downloads[0], 'expiresAt'))->getTimestamp(), 5);
    }

    public function testOtherOrdersHaveNoLicenceNorDownloads(): void
    {
        $client = self::signedInClient();
        $order = SalesExamples::paid(SalesExamples::lesson(), session: 'cs_lesson');
        self::store($order);

        $body = self::get($client, '/api/admin/orders/'.$order->id()->toBase32());

        self::assertNull(Json::at($body, 'licence'));
        self::assertNull(Json::at($body, 'exclusiveConflictAt'));
        self::assertSame([], Json::at($body, 'downloads'));
        self::assertArrayNotHasKey('downloads', Json::array(self::get($client, '/api/admin/orders'), 0));
    }

    public function testTheAdminReadsTheLicencePdf(): void
    {
        $client = self::signedInClient();
        $order = SalesExamples::paid(SalesExamples::beat(), session: 'cs_pdf');
        self::store($order);

        $client->request('GET', '/api/admin/orders/'.$order->id()->toBase32().'/licence');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', (string) $client->getResponse()->getContent());
        self::assertSame('inline; filename=licence-'.$order->reference().'.pdf', $client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testAnOrderWithoutLicenceHasNoLicencePdf(): void
    {
        $client = self::signedInClient();
        $order = SalesExamples::paid(SalesExamples::lesson(), session: 'cs_nolicence');
        self::store($order);

        foreach (['/api/admin/orders/'.$order->id()->toBase32().'/licence', '/api/admin/orders/01JCQZZZZZZZZZZZZZZZZZZZZZ/licence'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testResendingABeatConfirmationAnswersWithNewLinks(): void
    {
        $client = self::signedInClient();
        $mails = new RecordingSalesMails();
        self::getContainer()->set(SalesMails::class, $mails);
        $order = SalesExamples::paid(SalesExamples::beat(), session: 'cs_resend');
        self::store($order);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist(Invoice::issue('2026-0001', $order, Seller::of(DocumentExamples::identity()), VatMentions::defaults(), new \DateTimeImmutable('2026-11-10 10:06:00')));
        $manager->flush();

        $client->jsonRequest('POST', '/api/admin/orders/'.$order->id()->toBase32().'/resend');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $mails->sent);
        self::assertSame(['mp3', 'wav'], array_map(static fn (mixed $link): string => Json::string($link, 'deliverable'), Json::array(Json::decode((string) $client->getResponse()->getContent()), 'downloads')));
    }

    private static function store(Order $order): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($order);
        $manager->flush();
    }

    /** @return array<mixed> */
    private static function get(KernelBrowser $client, string $url): array
    {
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return Json::decode((string) $client->getResponse()->getContent());
    }
}
