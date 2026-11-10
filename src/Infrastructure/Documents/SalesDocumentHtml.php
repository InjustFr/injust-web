<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Application\Sales\CreditNoteView;
use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Settings\LegalDocumentHtml;
use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsQueries;
use App\Domain\Sales\Customer;
use App\Domain\Settings\LegalKind;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Intl\Countries;
use Twig\Environment;

final readonly class SalesDocumentHtml
{
    public function __construct(
        private Environment $twig,
        private SettingsQueries $settings,
        private LegalDocumentHtml $legal,
        #[Autowire(env: 'DEFAULT_URI')]
        private string $siteUrl,
    ) {
    }

    public function invoice(InvoiceView $invoice): string
    {
        $buyer = $invoice->buyer;

        return $this->twig->render('pdf/invoice.html.twig', [
            'invoice' => $invoice,
            'country' => self::country($buyer, 'fr'),
            'business' => null !== $buyer->company || null !== $buyer->vatId,
        ]);
    }

    public function creditNote(CreditNoteView $creditNote): string
    {
        return $this->twig->render('pdf/credit_note.html.twig', [
            'creditNote' => $creditNote,
            'country' => self::country($creditNote->buyer, 'fr'),
        ]);
    }

    public function legal(LegalDocumentView $document, string $locale): string
    {
        return $this->twig->render('pdf/legal.html.twig', [
            'locale' => $locale,
            'kind' => $document->kind->value,
            'version' => $document->version,
            'updatedAt' => $document->updatedAt,
            'body' => $this->legal->render($document, $this->settings->current(), $locale, $this->siteUrl),
        ]);
    }

    public function licence(OrderView $order): string
    {
        $licence = $order->licence ?? throw new \LogicException('Only a beat order has a licence.');
        $terms = $licence->terms;
        $settings = $this->settings->current();

        return $this->twig->render('pdf/licence.html.twig', [
            'locale' => $order->locale,
            'order' => $order,
            'beat' => $licence->beat,
            'seller' => $settings->legal,
            'buyer' => $order->customer,
            'country' => null === $order->customer ? '' : self::country($order->customer, $order->locale),
            'version' => $terms->version,
            'body' => $this->legal->render(new LegalDocumentView(LegalKind::Licence, $terms->body, $terms->version, $terms->publishedAt), $settings, $order->locale, $this->siteUrl),
        ]);
    }

    private static function country(Customer $buyer, string $locale): string
    {
        return Countries::exists($buyer->country) ? Countries::getName($buyer->country, $locale) : $buyer->country;
    }
}
