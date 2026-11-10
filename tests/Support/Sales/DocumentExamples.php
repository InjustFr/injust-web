<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsQueries;
use App\Application\Settings\SettingsView;
use App\Domain\Sales\Customer;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\Order;
use App\Domain\Sales\Purchasable;
use App\Domain\Sales\Seller;
use App\Domain\Settings\LegalDocument;
use App\Domain\Settings\LegalIdentity;
use App\Domain\Settings\LegalKind;
use App\Domain\Settings\Settings;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\Exception\NotFound;
use App\Domain\Shared\LocalizedText;

final class DocumentExamples
{
    public static function identity(string $vatNumber = ''): LegalIdentity
    {
        return new LegalIdentity('Louis Injust', 'EI', '443061841', "1 rue du Son\n75000 Paris", 'louis@injust.test', '+33600000000', 'Louis Injust', 'Médiateur Test', 'https://mediator.test', 'OVH SAS', 'Roubaix', '+33972101007', $vatNumber);
    }

    public static function settings(?string $bookingUrl = null, string $contactEmail = 'hello@injust.test', string $vatNumber = ''): SettingsView
    {
        $settings = Settings::initial();
        $settings->editLegalIdentity(self::identity($vatNumber));
        $settings->editGeneral($contactEmail, $bookingUrl, VatMentions::defaults(), new LocalizedText(), new LocalizedText(), false, '');

        return SettingsView::of($settings);
    }

    public static function document(LegalKind $kind = LegalKind::CgvLessons, string $fr = "# CGV\n\nVendeur : {name}, SIREN {siren}.", string $en = "# Terms\n\nSeller: {name}, SIREN {siren}.", int $edits = 0): LegalDocumentView
    {
        $document = LegalDocument::draft($kind, new LocalizedText($fr, $en), new \DateTimeImmutable('2026-11-01 09:00:00 Europe/Paris'));
        for ($edit = 0; $edit < $edits; ++$edit) {
            $document->edit(new LocalizedText($fr, $en), new \DateTimeImmutable('2026-11-02 09:00:00 Europe/Paris'));
        }

        return LegalDocumentView::of($document);
    }

    /** @param list<LegalDocumentView> $documents */
    public static function queries(SettingsView $settings, array $documents = []): SettingsQueries
    {
        return new class($settings, $documents) implements SettingsQueries {
            /** @param list<LegalDocumentView> $documents */
            public function __construct(private readonly SettingsView $settings, private readonly array $documents)
            {
            }

            public function current(): SettingsView
            {
                return $this->settings;
            }

            public function legalDocument(LegalKind $kind): LegalDocumentView
            {
                foreach ($this->documents as $document) {
                    if ($kind === $document->kind) {
                        return $document;
                    }
                }
                throw new NotFound('legal document', $kind->value);
            }

            public function legalDocuments(): array
            {
                return $this->documents;
            }
        };
    }

    public static function paidOrder(?Purchasable $product = null, ?Customer $customer = null, string $locale = 'fr'): Order
    {
        $order = SalesExamples::order($product, '2026-11-10 10:00:00', $locale);
        $order->attachSession('cs_test');
        $order->pay(SalesExamples::payment($order, $customer, '2026-11-10 10:05:00'));
        $order->invoiced('2026-0007');

        return $order;
    }

    public static function invoice(Order $order): InvoiceView
    {
        return InvoiceView::of(self::invoiceOf($order));
    }

    public static function invoiceOf(Order $order, string $number = '2026-0007'): Invoice
    {
        return Invoice::issue($number, $order, Seller::of(self::identity()), VatMentions::defaults(), new \DateTimeImmutable('2026-11-10 10:06:00'));
    }

    public static function orderView(Order $order): OrderView
    {
        return OrderView::of($order);
    }
}
