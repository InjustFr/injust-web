<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Application\Sales\InvoiceView;
use App\Application\Settings\LegalDocumentHtml;
use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsQueries;
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
            'country' => Countries::exists($buyer->country) ? Countries::getName($buyer->country, 'fr') : $buyer->country,
            'business' => null !== $buyer->company || null !== $buyer->vatId,
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
}
