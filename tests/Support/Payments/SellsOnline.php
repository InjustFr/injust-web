<?php

declare(strict_types=1);

namespace App\Tests\Support\Payments;

use App\Application\Lessons\AddLessonOffer\AddLessonOffer;
use App\Application\Lessons\AddLessonOffer\AddLessonOfferHandler;
use App\Application\Production\AddProductionService\AddProductionService;
use App\Application\Production\AddProductionService\AddProductionServiceHandler;
use App\Application\Sales\CheckoutRequest;
use App\Application\Sales\CheckoutUrls;
use App\Application\Sales\ConsentTexts;
use App\Application\Sales\OrderView;
use App\Application\Sales\PaymentGateway;
use App\Application\Sales\SalesMails;
use App\Application\Sales\SalesQueries;
use App\Application\Settings\SwitchOnlineCheckout\SwitchOnlineCheckout;
use App\Application\Settings\SwitchOnlineCheckout\SwitchOnlineCheckoutHandler;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Sales\Order;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Presentation\Web\Webhooks\StripeSignature;
use App\Tests\Functional\Web\ConfiguresSite;
use App\Tests\Support\Json;
use App\Tests\Support\Sales\FakeCheckoutUrls;
use App\Tests\Support\Sales\FakeConsentTexts;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\SalesExamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

trait SellsOnline
{
    use ConfiguresSite;

    private const array LESSON_CONSENTS = ['terms', 'early-start', 'pro-rata'];

    private static RecordingSalesMails $mails;

    private static function shop(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->get('cache.checkout_limiter')->clear();
        self::getContainer()->set(ConsentTexts::class, new FakeConsentTexts());
        self::getContainer()->set(CheckoutUrls::class, new FakeCheckoutUrls());
        self::$mails = new RecordingSalesMails();
        self::getContainer()->set(SalesMails::class, self::$mails);

        return $client;
    }

    private static function openShop(bool $checkout = true, bool $lessons = true, bool $production = true, bool $beats = false): void
    {
        self::editIdentity();
        self::editGeneral();
        self::writeLegal(LegalKind::CgvLessons, 'CGV cours', 'Lesson terms');
        self::writeLegal(LegalKind::CgvProduction, 'CGV production', 'Production terms');
        self::writeLegal(LegalKind::CgvBeats, 'CGV beats', 'Beat terms');
        self::switchSections($lessons, $production, $beats);
        self::getContainer()->get(SwitchOnlineCheckoutHandler::class)(new SwitchOnlineCheckout($checkout));
    }

    private static function mails(): RecordingSalesMails
    {
        return self::$mails;
    }

    private static function lesson(int $cents = 4500): string
    {
        return self::getContainer()->get(AddLessonOfferHandler::class)(new AddLessonOffer(new LessonOfferDetails(
            new LocalizedText('Cours de guitare', 'Guitar lesson'),
            new LocalizedText(),
            OfferKind::Single,
            1,
            LessonFormat::Online,
            60,
            new Price($cents),
        )))->id;
    }

    private static function service(Pricing $pricing = Pricing::Fixed, int $cents = 12000): string
    {
        return self::getContainer()->get(AddProductionServiceHandler::class)(new AddProductionService(new ProductionServiceDetails(
            new LocalizedText('Mixage', 'Mixing'),
            new LocalizedText(),
            ServiceKind::Mix,
            $pricing,
            new Price($cents),
        )))->id;
    }

    /**
     * @param array<string, mixed>  $overrides
     * @param array<string, string> $server
     */
    private static function checkout(KernelBrowser $client, string $productId, array $overrides = [], array $server = []): void
    {
        $client->jsonRequest('POST', '/api/checkout', [
            'productType' => 'lesson',
            'productId' => $productId,
            'email' => 'buyer@example.test',
            'locale' => 'fr',
            'consents' => self::LESSON_CONSENTS,
            'cgvVersion' => 1,
            ...$overrides,
        ], $server);
    }

    private static function startedSession(KernelBrowser $client, string $productId): string
    {
        self::checkout($client, $productId);
        self::assertResponseStatusCodeSame(201);
        $url = Json::string(Json::decode((string) $client->getResponse()->getContent()), 'url');

        return substr($url, (int) strrpos($url, '/') + 1);
    }

    private static function pendingOrder(int $cents = 4500): Order
    {
        $order = SalesExamples::order(SalesExamples::lesson($cents));
        $id = $order->id()->toBase32();
        $session = self::getContainer()->get(PaymentGateway::class)->startCheckout(new CheckoutRequest($id, $order->reference(), $order->productLabel(), $cents, Order::CURRENCY, 'fr', null, 'https://injust.test/fr/checkout/success?order='.$id, 'https://injust.test/fr/checkout/cancel?order='.$id));
        $order->attachSession($session->id);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($order);
        $manager->flush();

        return $order;
    }

    private static function stored(Order $order): OrderView
    {
        $view = self::getContainer()->get(SalesQueries::class)->order($order->id()->toBase32());
        self::assertNotNull($view);

        return $view;
    }

    /** @return list<OrderView> */
    private static function orders(): array
    {
        return self::getContainer()->get(SalesQueries::class)->orders();
    }

    private static function onlyOrder(): OrderView
    {
        $orders = self::orders();
        self::assertCount(1, $orders);

        return $orders[0];
    }

    /** @return array<mixed> */
    private static function stripePays(string $session, ?int $amountCents = null): array
    {
        return self::getContainer()->get(HttpClientInterface::class)->request('POST', 'https://api.stripe.com/v1/test_helpers/checkout/sessions/'.$session.'/pay', [
            'body' => null === $amountCents ? [] : ['amount_total' => $amountCents],
        ])->toArray();
    }

    /** @param array<mixed> $object */
    private static function notify(KernelBrowser $client, string $type, array $object, ?string $signature = null): void
    {
        $payload = json_encode(['id' => 'evt_test', 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]], \JSON_THROW_ON_ERROR);
        self::deliver($client, $payload, $signature ?? self::getContainer()->get(StripeSignature::class)->sign($payload));
    }

    private static function deliver(KernelBrowser $client, string $payload, ?string $signature): void
    {
        $client->request('POST', '/webhooks/stripe', server: ['CONTENT_TYPE' => 'application/json'] + (null === $signature ? [] : ['HTTP_STRIPE_SIGNATURE' => $signature]), content: $payload);
    }
}
