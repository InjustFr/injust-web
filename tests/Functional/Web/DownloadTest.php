<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Sales\DownloadLink;
use App\Application\Sales\DownloadLinks;
use App\Application\Sales\SalesQueries;
use App\Domain\Beats\Beat;
use App\Domain\Beats\Deliverable;
use App\Domain\Sales\Order;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Purchasable;
use App\Domain\Shared\Price;
use App\Tests\Support\Payments\SellsBeats;
use App\Tests\Support\Sales\SalesExamples;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\UriSigner;

final class DownloadTest extends WebTestCase
{
    use ConfiguresSite;
    use SellsBeats;

    private KernelBrowser $client;

    private Beat $beat;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        self::editIdentity();
        self::editGeneral(true);
        $this->beat = self::beatOnSale([Deliverable::Mp3, Deliverable::Wav, Deliverable::Stems]);
    }

    public function testAPaidBuyerDownloadsEachFileOfTheTierPrivately(): void
    {
        $order = $this->paid('cs_paid');
        $links = $this->links($order);

        self::assertSame(['mp3', 'wav'], array_map(static fn (DownloadLink $link): string => $link->deliverable, $links));
        self::assertStringStartsWith('http://localhost/download/'.$order->id()->toBase32().'/mp3?_expiration=', $links[0]->url);
        self::assertStringContainsString('&_hash=', $links[0]->url);
        self::assertEqualsWithDelta(time() + 7 * 86400, $links[0]->expiresAt->getTimestamp(), 5);

        foreach ([['mp3', 'audio/mpeg', 'riot-mp3.mp3', Deliverable::Mp3], ['wav', 'audio/wav', 'riot-wav.wav', Deliverable::Wav]] as $index => [$deliverable, $type, $filename, $case]) {
            $limit = (string) ini_get('max_execution_time');
            set_time_limit(30);
            $this->client->request('GET', $links[$index]->url);
            self::assertSame('0', ini_get('max_execution_time'), 'A large file is never cut off by the PHP time limit.');
            set_time_limit((int) $limit);

            self::assertResponseStatusCodeSame(200, $deliverable);
            self::assertSame(self::fileBytes($case), $this->body());
            self::assertResponseHeaderSame('Content-Type', $type);
            self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename='.$filename);
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
            $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
            self::assertStringContainsString('private', $cacheControl);
            self::assertStringContainsString('no-store', $cacheControl);
            self::assertFalse($this->client->getResponse()->headers->has('Set-Cookie'));
        }
        self::assertNull($this->client->getCookieJar()->get('PHPSESSID'));
    }

    public function testStemsAreAZipArchive(): void
    {
        $order = self::storeOrder(SalesExamples::paid($this->product([Deliverable::Stems]), session: 'cs_stems'));

        $this->client->request('GET', $this->links($order)[0]->url);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/zip');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=riot-stems.zip');
    }

    public function testATamperedOrUnsignedLinkIsForbidden(): void
    {
        $order = $this->paid('cs_tampered');
        $url = $this->links($order)[0]->url;

        foreach ([str_replace('/mp3?', '/wav?', $url), (string) preg_replace('/_hash=[^&]+/', '_hash=forged', $url), explode('?', $url)[0], (string) preg_replace('/_expiration=\d+/', '_expiration=4102444800', $url)] as $forged) {
            $this->client->request('GET', $forged);

            self::assertResponseStatusCodeSame(403, $forged);
            self::assertStringNotContainsString(self::fileBytes(Deliverable::Wav), $this->body());
        }
    }

    public function testAnExpiredLinkIsGoneAndExplainsHowToGetANewOne(): void
    {
        $order = $this->paid('cs_expired');
        $url = self::getContainer()->get(UriSigner::class)->sign('http://localhost/download/'.$order->id()->toBase32().'/mp3', time() - 60);

        $crawler = $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(410);
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertSame('This download link has expired', $crawler->filter('main h1')->text());
        self::assertStringContainsString('Download links are valid for 7 days. Reply to your confirmation email', $crawler->filter('main')->text());
        self::assertSame('/fr', $crawler->filter('.lang-switch')->attr('href'));
        self::assertStringContainsString($order->reference(), $crawler->filter('main')->text());
        self::assertStringNotContainsString(self::fileBytes(Deliverable::Mp3), $this->body());
        self::assertNull($this->client->getCookieJar()->get('PHPSESSID'));
    }

    public function testAnExpiredLinkToAnUnknownOrderIsGoneWithoutReference(): void
    {
        $crawler = $this->client->request('GET', self::getContainer()->get(UriSigner::class)->sign('http://localhost/download/01JCQZZZZZZZZZZZZZZZZZZZZZ/licence', time() - 60));

        self::assertResponseStatusCodeSame(410);
        self::assertCount(0, $crawler->filter('.checkout-reference'));
        self::assertSame('This download link has expired', $crawler->filter('main h1')->text());
        self::assertSame('/fr', $crawler->filter('.lang-switch')->attr('href'));
    }

    public function testAnOrderThatIsNotPaidIsForbidden(): void
    {
        $refunded = SalesExamples::paid($this->product(), session: 'cs_refunded');
        $refunded->refund($refunded->price()->cents);
        $pending = SalesExamples::order($this->product());
        $pending->attachSession('cs_pending');
        $expired = SalesExamples::order($this->product());
        $expired->attachSession('cs_expired');
        $expired->expire();

        foreach ([$refunded, $pending, $expired] as $order) {
            self::storeOrder($order);
            $id = $order->id()->toBase32();
            $signer = self::getContainer()->get(UriSigner::class);

            foreach (['mp3', 'licence'] as $file) {
                $this->client->request('GET', $signer->sign('http://localhost/download/'.$id.'/'.$file, new \DateTimeImmutable('+1 day')));

                self::assertResponseStatusCodeSame(403, $order->status()->value.' '.$file);
            }
        }
    }

    public function testAPartlyRefundedOrAConflictingOrderStillDownloads(): void
    {
        $partly = SalesExamples::paid($this->product(), session: 'cs_partly');
        $partly->refund(1000);
        $conflict = SalesExamples::paid($this->product(), session: 'cs_conflict');
        $conflict->flagExclusiveConflict(new \DateTimeImmutable());

        foreach ([$partly, $conflict] as $order) {
            $this->client->request('GET', $this->links(self::storeOrder($order))[0]->url);

            self::assertResponseStatusCodeSame(200);
        }
    }

    public function testAFileOutsideTheTierOrNoLongerStoredIsNotFound(): void
    {
        $order = $this->paid('cs_missing');
        $signer = self::getContainer()->get(UriSigner::class);
        $base = 'http://localhost/download/'.$order->id()->toBase32().'/';

        $this->client->request('GET', $signer->sign($base.'stems', new \DateTimeImmutable('+1 day')));
        self::assertResponseStatusCodeSame(404);

        $this->beat->removeFile(Deliverable::Wav);
        self::persist($this->beat);
        $this->client->request('GET', $signer->sign($base.'wav', new \DateTimeImmutable('+1 day')));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', $signer->sign('http://localhost/download/01JCUNKNOWN000000000000000/mp3', new \DateTimeImmutable('+1 day')));
        self::assertResponseStatusCodeSame(404);
    }

    public function testADeletedBeatIsNotFound(): void
    {
        $order = self::storeOrder(SalesExamples::paid(SalesExamples::beat(3000, false, '01JCDE1ETED000000000000000'), session: 'cs_deleted'));

        $this->client->request('GET', $this->links($order)[0]->url);

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnotherProductHasNothingToDownload(): void
    {
        $order = self::storeOrder(SalesExamples::paid(SalesExamples::lesson(), session: 'cs_lesson'));
        $view = self::getContainer()->get(SalesQueries::class)->order($order->id()->toBase32());
        self::assertNotNull($view);
        $signer = self::getContainer()->get(UriSigner::class);

        self::assertSame([], self::getContainer()->get(DownloadLinks::class)->forOrder($view));
        foreach (['mp3', 'licence'] as $file) {
            $this->client->request('GET', $signer->sign('http://localhost/download/'.$order->id()->toBase32().'/'.$file, new \DateTimeImmutable('+1 day')));
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testTheBuyerDownloadsTheLicencePdf(): void
    {
        $order = $this->paid('cs_licence');
        $view = self::getContainer()->get(SalesQueries::class)->order($order->id()->toBase32());
        self::assertNotNull($view);
        $link = self::getContainer()->get(DownloadLinks::class)->licence($view);

        self::assertSame('licence', $link->deliverable);
        self::assertStringStartsWith('http://localhost/download/'.$order->id()->toBase32().'/licence?_expiration=', $link->url);
        $this->client->request('GET', $link->url);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=licence-'.$order->reference().'.pdf');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertStringStartsWith('%PDF', $this->body());
        $this->client->request('GET', str_replace('_hash=', '_hash=x', $link->url));
        self::assertResponseStatusCodeSame(403);
    }

    private function paid(string $session): Order
    {
        return self::storeOrder(SalesExamples::paid($this->product(), session: $session));
    }

    /** @param list<Deliverable> $deliverables */
    private function product(array $deliverables = [Deliverable::Mp3, Deliverable::Wav]): Purchasable
    {
        return new Purchasable(ProductType::Beat, $this->beat->id()->toBase32(), 'Riot — Premium', new Price(3000), null, SalesExamples::licensedBeat(false, $this->beat->id()->toBase32(), $deliverables));
    }

    /** @return list<DownloadLink> */
    private function links(Order $order): array
    {
        $view = self::getContainer()->get(SalesQueries::class)->order($order->id()->toBase32());
        self::assertNotNull($view);

        return self::getContainer()->get(DownloadLinks::class)->forOrder($view);
    }

    private function body(): string
    {
        return $this->client->getInternalResponse()->getContent();
    }
}
