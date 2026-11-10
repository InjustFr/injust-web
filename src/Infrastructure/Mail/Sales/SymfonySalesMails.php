<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Sales;

use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Sales\SalesDocuments;
use App\Application\Sales\SalesMails;
use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsQueries;
use App\Domain\Sales\ProductType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SymfonySalesMails implements SalesMails
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private SettingsQueries $settings,
        private SalesDocuments $documents,
        #[Autowire(env: 'MAILER_SENDER')]
        private string $sender,
    ) {
    }

    public function confirmation(OrderView $order, InvoiceView $invoice): void
    {
        $settings = $this->settings->current();
        $terms = new LegalDocumentView(ProductType::from($order->productType)->cgvKind(), $order->terms->body, $order->terms->version, $order->terms->publishedAt);
        $contactEmail = '' === $settings->contactEmail ? $settings->legal->email : $settings->contactEmail;

        $this->mailer->send(new TemplatedEmail()
            ->from(Address::create($this->sender))
            ->to($order->email)
            ->replyTo($contactEmail)
            ->subject($this->translator->trans('sales.confirmation.subject', ['reference' => $order->reference], 'emails', $order->locale))
            ->htmlTemplate('email/sales/confirmation.html.twig')
            ->textTemplate('email/sales/confirmation.txt.twig')
            ->context([
                'order' => $order,
                'paidAt' => $invoice->paidAt,
                'bookingUrl' => $settings->bookingUrl,
                'contactEmail' => $contactEmail,
                'mediatorName' => $settings->legal->mediatorName,
                'mediatorUrl' => $settings->legal->mediatorUrl,
            ])
            ->locale($order->locale)
            ->attach($this->documents->invoicePdf($invoice), 'Facture-'.$invoice->number.'.pdf', 'application/pdf')
            ->attach($this->documents->legalPdf($terms, $order->locale), 'CGV-'.$terms->version.'.pdf', 'application/pdf'));
    }
}
