<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Mail\Sales;

use App\Application\Sales\CreditNoteView;
use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Sales\SalesDocuments;
use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsView;
use App\Domain\Sales\Consent;
use App\Domain\Sales\ConsentKind;
use App\Domain\Sales\Order;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Purchasable;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\Price;
use App\Infrastructure\Mail\Sales\SymfonySalesMails;
use App\Tests\Support\Sales\DocumentExamples;
use App\Tests\Support\Sales\FakeDownloadLinks;
use App\Tests\Support\Sales\SalesExamples;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\RawMessage;

final class SymfonySalesMailsTest extends KernelTestCase
{
    public function testTheFrenchConfirmationSummarisesTheOrderAndAttachesTheInvoiceAndTheTerms(): void
    {
        $order = self::order('fr');

        self::send($order, DocumentExamples::settings());

        $email = self::sent();
        self::assertSame('Injust', $email->getFrom()[0]->getName());
        self::assertSame('noreply@injust.test', $email->getFrom()[0]->getAddress());
        self::assertSame('stripe@example.test', $email->getTo()[0]->getAddress());
        self::assertSame('hello@injust.test', $email->getReplyTo()[0]->getAddress());
        self::assertSame('Injust : confirmation de votre commande '.$order->reference(), $email->getSubject());
        foreach ([self::text($email->getHtmlBody()), self::text($email->getTextBody())] as $body) {
            foreach ([
                'Référence : '.$order->reference(),
                'Produit : Cours de guitare',
                'Prix : 45,00 € TTC',
                'Date : 10/11/2026 11:05',
                '« Je reconnais tout. » accepté le 10/11/2026 11:00:00',
                '« J’accepte la version 3. » accepté le 10/11/2026 11:00:00',
                'Je vous contacte par e-mail sous 2 jours ouvrés',
                'jusqu’au 24/11/2026',
                'proportionnel au service déjà fourni',
                'http://localhost/fr/withdrawal',
                'hello@injust.test',
                'Médiateur Test',
                'https://mediator.test',
            ] as $line) {
                self::assertStringContainsString($line, $body);
            }
        }
        self::assertSame([
            ['Facture-2026-0007.pdf', 'application/pdf', 'invoice 2026-0007'],
            ['CGV-3.pdf', 'application/pdf', 'cgv-lessons v3 fr CGV v3 2026-11-01'],
        ], array_map(static fn (DataPart $part): array => [$part->getFilename(), $part->getMediaType().'/'.$part->getMediaSubtype(), $part->getBody()], $email->getAttachments()));
    }

    public function testTheEnglishConfirmationPointsToTheBookingPageAndTheProductionTerms(): void
    {
        $order = self::order('en', SalesExamples::production());

        self::send($order, DocumentExamples::settings('https://cal.example.test/injust'));

        $email = self::sent();
        self::assertSame('Injust: your order '.$order->reference().' is confirmed', $email->getSubject());
        foreach ([self::text($email->getHtmlBody()), self::text($email->getTextBody())] as $body) {
            self::assertStringContainsString('Price: €120.00 incl. tax', $body);
            self::assertStringContainsString('Date: 2026-11-10 11:05', $body);
            self::assertStringContainsString('https://cal.example.test/injust', $body);
            self::assertStringNotContainsString('2 working days', $body);
            self::assertStringContainsString('until 2026-11-24', $body);
            self::assertStringContainsString('http://localhost/withdrawal', $body);
        }
        self::assertSame('CGV-3.pdf', $email->getAttachments()[1]->getFilename());
        self::assertSame('cgv-production v3 en Terms v3 2026-11-01', $email->getAttachments()[1]->getBody());
    }

    public function testABeatConfirmationConfirmsTheWaiverInsteadOfOfferingAWithdrawal(): void
    {
        self::send(SalesExamples::paid(SalesExamples::beat()), DocumentExamples::settings());

        foreach ([self::text(self::sent()->getHtmlBody()), self::text(self::sent()->getTextBody())] as $body) {
            self::assertStringContainsString('Vous avez expressément accepté que le contenu numérique soit fourni dès le paiement et reconnu renoncer ainsi à votre droit de rétractation', $body);
            self::assertStringNotContainsString('/withdrawal', $body);
            self::assertStringNotContainsString('14 jours', $body);
        }
    }

    public function testABeatConfirmationGivesOneDownloadLinkPerFileAndAttachesTheLicence(): void
    {
        $order = self::order('en', SalesExamples::beat(5900), SalesExamples::consents(ProductType::Beat));
        $id = $order->id()->toBase32();

        self::send($order, DocumentExamples::settings());

        $email = self::sent();
        foreach ([self::text($email->getHtmlBody()), self::text($email->getTextBody())] as $body) {
            foreach ([
                'Product: Riot beat',
                'Your files are ready to download:',
                'MP3: https://injust.test/download/'.$id.'/mp3?_hash=signed',
                'WAV: https://injust.test/download/'.$id.'/wav?_hash=signed',
                'These links are valid for 7 days, until 2026-11-17 11:06 (Paris time).',
                'Reply to this email if you need a new link.',
                'Your invoice, the terms of sale and your licence are attached.',
                '“Texte licence” accepted on 2026-11-10 11:00:00',
            ] as $line) {
                self::assertStringContainsString($line, $body);
            }
            self::assertStringNotContainsString('/stems', $body);
            self::assertStringNotContainsString('I will contact you by email', $body);
        }
        self::assertStringContainsString('href="https://injust.test/download/'.$id.'/mp3?_hash=signed"', (string) $email->getHtmlBody());
        self::assertSame([
            ['Facture-2026-0007.pdf', 'application/pdf', 'invoice 2026-0007'],
            ['CGV-3.pdf', 'application/pdf', 'cgv-beats v3 en Terms v3 2026-11-01'],
            ['Licence-'.$order->reference().'.pdf', 'application/pdf', 'licence '.$order->reference()],
        ], array_map(static fn (DataPart $part): array => [$part->getFilename(), $part->getMediaType().'/'.$part->getMediaSubtype(), $part->getBody()], $email->getAttachments()));
    }

    public function testTheFrenchBeatConfirmationExplainsTheDownloadsInFrench(): void
    {
        $order = self::order('fr', SalesExamples::beat(5900), SalesExamples::consents(ProductType::Beat));

        self::send($order, DocumentExamples::settings());

        foreach ([self::text(self::sent()->getHtmlBody()), self::text(self::sent()->getTextBody())] as $body) {
            self::assertStringContainsString('Vos fichiers sont prêts à télécharger :', $body);
            self::assertStringContainsString('Ces liens sont valables 7 jours, jusqu’au 17/11/2026 11:06 (heure de Paris).', $body);
            self::assertStringContainsString('Répondez à cet e-mail si vous avez besoin d’un nouveau lien.', $body);
            self::assertStringContainsString('Votre facture, les conditions générales de vente et votre licence sont jointes.', $body);
        }
    }

    public function testAnExclusiveConflictIsNoticedToTheOwner(): void
    {
        $order = self::order('fr', SalesExamples::beat(49900, true), SalesExamples::consents(ProductType::Beat));
        $order->flagExclusiveConflict(new \DateTimeImmutable('2026-11-10 10:06:00 UTC'));

        self::mails(DocumentExamples::settings(), self::getContainer()->get(MailerInterface::class))->exclusiveConflict(DocumentExamples::orderView($order));

        $email = self::sent();
        self::assertSame('hello@injust.test', $email->getTo()[0]->getAddress());
        self::assertSame('stripe@example.test', $email->getReplyTo()[0]->getAddress());
        self::assertSame('Exclusive conflict: order '.$order->reference().' (Riot beat)', $email->getSubject());
        $body = self::text($email->getTextBody());
        foreach ([
            'A beat was paid for while it was no longer available for this licence',
            'Reference: '.$order->reference(),
            'Product: Riot beat',
            'Price: €499.00 incl. tax',
            'The order is paid and invoiced and the buyer received the download links. Refund it from the Orders page of the admin',
        ] as $line) {
            self::assertStringContainsString($line, $body);
        }
        self::assertNull($email->getHtmlBody());
    }

    public function testWithoutAContactAddressTheSellerAddressReceivesTheReplies(): void
    {
        self::send(self::order('fr'), DocumentExamples::settings(contactEmail: ''));

        $email = self::sent();
        self::assertSame('louis@injust.test', $email->getReplyTo()[0]->getAddress());
        self::assertStringContainsString('louis@injust.test', (string) $email->getTextBody());
    }

    public function testWhatTheBuyerTypedIsEscapedInTheHtmlPart(): void
    {
        $order = self::order('fr', new Purchasable(ProductType::Lesson, 'lesson', '<b>Bob</b>', new Price(4500)));

        self::send($order, DocumentExamples::settings());

        self::assertStringNotContainsString('<b>Bob</b>', (string) self::sent()->getHtmlBody());
        self::assertStringContainsString('&lt;b&gt;Bob&lt;/b&gt;', (string) self::sent()->getHtmlBody());
    }

    public function testAFailedMailIsNotSwallowed(): void
    {
        $mailer = new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new TransportException('SMTP down');
            }
        };

        $this->expectException(TransportException::class);

        self::mails(DocumentExamples::settings(), $mailer)->confirmation(DocumentExamples::orderView(self::order('fr')), DocumentExamples::invoice(self::order('fr')));
    }

    /** @param list<Consent>|null $consents */
    private static function order(string $locale, ?Purchasable $product = null, ?array $consents = null): Order
    {
        $order = SalesExamples::place($product ?? SalesExamples::lesson(), $locale, 'buyer@example.test', $consents ?? [
            new Consent(ConsentKind::Terms, 'J’accepte la version 3.', new \DateTimeImmutable('2026-11-10 10:00:00 UTC')),
            new Consent(ConsentKind::EarlyStart, 'Je commence tôt.', new \DateTimeImmutable('2026-11-10 10:00:00 UTC')),
            new Consent(ConsentKind::ProRata, 'Je reconnais tout.', new \DateTimeImmutable('2026-11-10 10:00:00 UTC')),
        ], new \DateTimeImmutable('2026-11-10 10:00:00 UTC'));
        $order->attachSession('cs_test');
        $order->pay(SalesExamples::payment($order, paidAt: '2026-11-10 10:05:00 UTC'));
        $order->invoiced('2026-0007');

        return $order;
    }

    private static function send(Order $order, SettingsView $settings): void
    {
        self::mails($settings, self::getContainer()->get(MailerInterface::class))->confirmation(DocumentExamples::orderView($order), DocumentExamples::invoice($order));
    }

    private static function mails(SettingsView $settings, MailerInterface $mailer): SymfonySalesMails
    {
        $documents = new class implements SalesDocuments {
            public function invoicePdf(InvoiceView $invoice): string
            {
                return 'invoice '.$invoice->number;
            }

            public function creditNotePdf(CreditNoteView $creditNote): string
            {
                return 'credit note '.$creditNote->number;
            }

            public function legalPdf(LegalDocumentView $document, string $locale): string
            {
                return $document->kind->value.' v'.$document->version.' '.$locale.' '.$document->body->in($locale).' '.$document->updatedAt->format('Y-m-d');
            }

            public function licence(OrderView $order): string
            {
                return 'licence '.$order->reference;
            }
        };
        $cgv = [DocumentExamples::document(LegalKind::CgvLessons, edits: 1), DocumentExamples::document(LegalKind::CgvProduction, edits: 1), DocumentExamples::document(LegalKind::CgvBeats, edits: 1)];

        return new SymfonySalesMails($mailer, self::getContainer()->get('translator'), DocumentExamples::queries($settings, $cgv), $documents, new FakeDownloadLinks(), 'Injust <noreply@injust.test>', 'en');
    }

    private static function sent(): Email
    {
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    private static function text(mixed $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) (\is_string($html) ? $html : ''))), \ENT_QUOTES | \ENT_HTML5)));
    }
}
