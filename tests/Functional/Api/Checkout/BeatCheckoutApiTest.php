<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Checkout;

use App\Domain\Beats\Deliverable;
use App\Domain\Sales\Order;
use App\Domain\Settings\LegalKind;
use App\Tests\Support\Json;
use App\Tests\Support\Payments\SellsBeats;
use App\Tests\Support\Payments\SellsOnline;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class BeatCheckoutApiTest extends WebTestCase
{
    use SellsBeats;
    use SellsOnline;

    private const array BEAT_CONSENTS = ['terms', 'digital-waiver', 'licence'];

    public function testABeatIsOrderedForATierWithItsLicenceFrozen(): void
    {
        $client = self::shop();
        self::openShop(beats: true);
        self::writeLegal(LegalKind::Licence, 'Licence accordée par {name}', 'Licence granted by {name}');
        $tier = self::tier('Premium', 5900, [Deliverable::Mp3, Deliverable::Wav]);
        $beat = self::beatOnSale();

        self::checkout($client, $beat->id()->toBase32(), ['productType' => 'beat', 'tierId' => $tier->id()->toBase32(), 'consents' => self::BEAT_CONSENTS, 'licenceVersion' => 1, 'locale' => 'en']);

        self::assertResponseStatusCodeSame(201);
        $view = self::onlyOrder();
        self::assertSame(['pending', 'beat', $beat->id()->toBase32(), 'Riot — Premium', 5900, 'en'], [$view->status, $view->productType, $view->productId, $view->productLabel, $view->price->cents, $view->locale]);
        $order = self::getContainer()->get(EntityManagerInterface::class)->find(Order::class, Ulid::fromString($view->id));
        $licence = $order?->licence();
        self::assertNotNull($licence);
        self::assertSame([$tier->id()->toBase32(), 'riot', [Deliverable::Mp3, Deliverable::Wav], 10000], [$licence->beat->tierId, $licence->beat->slug, $licence->beat->deliverables, $licence->beat->streamCap]);
        self::assertSame(['Licence accordée par Louis Injust', 'Licence granted by Louis Injust'], [$licence->terms->body->fr, $licence->terms->body->en]);
        self::assertSame('en:licence:v1', $order?->consents()[2]->text);
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('refusedBeats')]
    public function testABeatOrderAgainstTheRulesIsRefused(bool $checkout, bool $beats, string $case, array $overrides, string $detail): void
    {
        $client = self::shop();
        self::openShop($checkout, beats: $beats);
        self::writeLegal(LegalKind::Licence, 'Licence', 'Licence');
        $tiers = ['basic' => self::tier('Basic', 2900, [Deliverable::Mp3]), 'exclusive' => self::tier('Exclusive', 49900, [Deliverable::Mp3, Deliverable::Wav, Deliverable::Stems], true, 1)];
        $beat = match ($case) {
            'hidden' => self::beatOnSale(visible: false),
            default => self::beatOnSale(),
        };
        if ('sold' === $case) {
            $beat->markSoldExclusively(new \DateTimeImmutable('2026-11-01'));
            self::persist($beat);
        }

        self::checkout($client, $beat->id()->toBase32(), ['productType' => 'beat', 'tierId' => $tiers['missing-file' === $case ? 'exclusive' : 'basic']->id()->toBase32(), 'consents' => self::BEAT_CONSENTS, 'licenceVersion' => 1, ...$overrides]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($detail, Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
        self::assertSame([], self::orders());
    }

    /** @return iterable<string, array{bool, bool, string, array<string, mixed>, string}> */
    public static function refusedBeats(): iterable
    {
        $unavailable = 'This offer cannot be paid online: it may have changed or need a quote. Contact me to book it.';
        yield 'checkout switched off' => [false, true, 'listed', [], 'Online payment is not open yet: contact me to book.'];
        yield 'beats switched off' => [true, false, 'listed', [], 'This part of the site is closed for now: contact me to book.'];
        yield 'no tier' => [true, true, 'listed', ['tierId' => ''], $unavailable];
        yield 'unknown tier' => [true, true, 'listed', ['tierId' => '01JCUNKNOWN000000000000000'], $unavailable];
        yield 'hidden beat' => [true, true, 'hidden', [], $unavailable];
        yield 'sold exclusively' => [true, true, 'sold', [], $unavailable];
        yield 'file of the tier missing' => [true, true, 'missing-file', [], $unavailable];
        yield 'licence edited since the page was shown' => [true, true, 'listed', ['licenceVersion' => 2], 'The licence terms changed while this page was open: reload it, read them and order again.'];
        yield 'licence version missing' => [true, true, 'listed', ['licenceVersion' => null], 'The licence terms changed while this page was open: reload it, read them and order again.'];
        yield 'licence not accepted' => [true, true, 'listed', ['consents' => ['terms', 'digital-waiver']], 'Tick every box above to place the order.'];
    }
}
