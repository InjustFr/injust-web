<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Sales;

use App\Application\Sales\DownloadLink;
use App\Application\Sales\OrderView;
use App\Infrastructure\Sales\SignedDownloadLinks;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class SignedDownloadLinksTest extends TestCase
{
    private const string NOW = '2026-11-10 10:00:00 UTC';

    public function testEachFileOfTheTierGetsAnAbsoluteLinkSignedForSevenDays(): void
    {
        $order = OrderView::of(SalesExamples::paid(SalesExamples::beat()));

        $links = self::links()->forOrder($order);

        self::assertSame(['mp3', 'wav'], array_map(static fn (DownloadLink $link): string => $link->deliverable, $links));
        foreach ($links as $link) {
            self::assertSigned($link, 'https://injust.test/download/'.$order->id.'/'.$link->deliverable);
        }
    }

    public function testTheLicencePdfHasItsOwnSignedLink(): void
    {
        $order = OrderView::of(SalesExamples::paid(SalesExamples::beat()));

        $link = self::links()->licence($order);

        self::assertSame('licence', $link->deliverable);
        self::assertSigned($link, 'https://injust.test/download/'.$order->id.'/licence');
    }

    public function testAnOrderWithoutLicenceHasNoFile(): void
    {
        self::assertSame([], self::links()->forOrder(OrderView::of(SalesExamples::paid())));
    }

    private static function assertSigned(DownloadLink $link, string $path): void
    {
        $expiresAt = new \DateTimeImmutable('2026-11-17 10:00:00 UTC');
        self::assertEquals($expiresAt, $link->expiresAt);
        self::assertStringStartsWith($path.'?_expiration='.$expiresAt->getTimestamp().'&_hash=', $link->url);
        $signer = self::signer(new MockClock('2026-11-17 09:59:59 UTC'));
        self::assertTrue($signer->checkRequest(Request::create($link->url)));
        self::assertFalse(self::signer(new MockClock('2026-11-17 10:00:01 UTC'))->checkRequest(Request::create($link->url)));
    }

    private static function links(): SignedDownloadLinks
    {
        $routes = new RouteCollection();
        $routes->add('download', new Route('/download/{orderId}/{deliverable}'));
        $routes->add('download_licence', new Route('/download/{orderId}/licence'));

        return new SignedDownloadLinks(self::signer(new MockClock(self::NOW)), new UrlGenerator($routes, new RequestContext(host: 'injust.test', scheme: 'https')), new MockClock(self::NOW));
    }

    private static function signer(MockClock $clock): UriSigner
    {
        return new UriSigner('secret', clock: $clock);
    }
}
