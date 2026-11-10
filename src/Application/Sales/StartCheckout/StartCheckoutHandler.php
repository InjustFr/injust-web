<?php

declare(strict_types=1);

namespace App\Application\Sales\StartCheckout;

use App\Application\Sales\CheckoutProducts;
use App\Application\Sales\CheckoutRequest;
use App\Application\Sales\CheckoutSession;
use App\Application\Sales\CheckoutUrls;
use App\Application\Sales\ConsentTexts;
use App\Application\Sales\PaymentGateway;
use App\Application\Settings\LegalDocumentHtml;
use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsQueries;
use App\Application\Transaction;
use App\Domain\Sales\Consent;
use App\Domain\Sales\ConsentKind;
use App\Domain\Sales\Exception\CheckoutClosed;
use App\Domain\Sales\Exception\LicenceChanged;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Sales\Exception\ProductUnavailable;
use App\Domain\Sales\Exception\SectionClosed;
use App\Domain\Sales\Exception\TermsChanged;
use App\Domain\Sales\LicenceSnapshot;
use App\Domain\Sales\LicensedBeat;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\Purchasable;
use App\Domain\Sales\Terms;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\LocalizedText;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class StartCheckoutHandler
{
    public function __construct(
        private SettingsQueries $settings,
        private CheckoutProducts $products,
        private ConsentTexts $consentTexts,
        private CheckoutUrls $urls,
        private OrderRepository $orders,
        private PaymentGateway $gateway,
        private ClockInterface $clock,
        private Transaction $transaction,
        private LegalDocumentHtml $legal,
        #[Autowire(env: 'DEFAULT_URI')]
        private string $siteUrl,
    ) {
    }

    public function __invoke(StartCheckout $command): CheckoutStarted
    {
        $product = $this->product($command);
        $terms = $this->terms($command);
        $now = $this->clock->now();
        $order = null === $product->beat
            ? Order::place($product, $command->locale, $command->email, $this->consents($command, $terms->version, $terms->version, $now), $terms, $now)
            : $this->placeBeat($command, $product, $product->beat, $terms, $now);
        $this->orders->add($order);
        $this->transaction->commit();

        $session = $this->openPaymentPage($order);
        $order->attachSession($session->id);
        $this->transaction->commit();

        return new CheckoutStarted($order->id()->toBase32(), $session->url);
    }

    private function product(StartCheckout $command): Purchasable
    {
        $settings = $this->settings->current();
        if (!$settings->onlineCheckout) {
            throw new CheckoutClosed();
        }
        if (!$settings->isEnabled($command->productType->section())) {
            throw new SectionClosed();
        }

        return $this->products->find($command->productType, $command->productId, $command->locale, $command->tierId) ?? throw new ProductUnavailable();
    }

    private function terms(StartCheckout $command): Terms
    {
        $cgv = $this->settings->legalDocument($command->productType->cgvKind());
        if ($cgv->version !== $command->cgvVersion) {
            throw new TermsChanged();
        }

        return $this->snapshot($cgv);
    }

    private function placeBeat(StartCheckout $command, Purchasable $product, LicensedBeat $beat, Terms $terms, \DateTimeImmutable $now): Order
    {
        $licence = $this->settings->legalDocument(LegalKind::Licence);
        if ($licence->version !== $command->licenceVersion) {
            throw new LicenceChanged();
        }

        return Order::placeBeat($product, new LicenceSnapshot($beat, $this->snapshot($licence)), $command->locale, $command->email, $this->consents($command, $terms->version, $licence->version, $now), $terms, $now);
    }

    private function snapshot(LegalDocumentView $document): Terms
    {
        $settings = $this->settings->current();

        return new Terms($document->version, new LocalizedText($this->legal->fill($document, $settings, 'fr', $this->siteUrl), $this->legal->fill($document, $settings, 'en', $this->siteUrl)), $document->updatedAt);
    }

    /** @return list<Consent> */
    private function consents(StartCheckout $command, int $cgvVersion, int $licenceVersion, \DateTimeImmutable $now): array
    {
        $consents = [];
        foreach ($command->productType->requiredConsents() as $kind) {
            if (\in_array($kind->value, $command->acceptedConsents, true)) {
                $consents[] = new Consent($kind, $this->consentTexts->text($kind, $command->locale, ConsentKind::Licence === $kind ? $licenceVersion : $cgvVersion), $now);
            }
        }

        return $consents;
    }

    private function openPaymentPage(Order $order): CheckoutSession
    {
        $id = $order->id()->toBase32();
        try {
            return $this->gateway->startCheckout(new CheckoutRequest(
                $id,
                $order->reference(),
                $order->productLabel(),
                $order->price()->cents,
                Order::CURRENCY,
                $order->locale(),
                '' === $order->email() ? null : $order->email(),
                $this->urls->success($id, $order->locale()),
                $this->urls->cancel($id, $order->locale()),
            ));
        } catch (\RuntimeException) {
            $order->expire();
            $this->transaction->commit();
            throw new PaymentUnavailable();
        }
    }
}
