<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Mail\Sales;

use App\Application\Sales\CreditNoteView;
use App\Application\Sales\InvoiceView;
use App\Application\Sales\OrderView;
use App\Application\Sales\SalesDocuments;
use App\Application\Sales\WithdrawalRequestView;
use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsView;
use App\Domain\Sales\WithdrawalRequest;
use App\Infrastructure\Mail\Sales\SymfonySalesMails;
use App\Tests\Support\Sales\DocumentExamples;
use App\Tests\Support\Sales\FakeDownloadLinks;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class WithdrawalMailsTest extends KernelTestCase
{
    public function testTheEnglishAcknowledgementDatesTheReceiptAndSaysWhatHappensNext(): void
    {
        [$order, $withdrawal] = self::withdrawal('en', 'Joe Strummer', 'Changed my mind.');

        self::mails(DocumentExamples::settings())->withdrawalAcknowledgement($order, $withdrawal);

        $email = self::sent();
        self::assertSame('noreply@injust.test', $email->getFrom()[0]->getAddress());
        self::assertSame('joe@example.test', $email->getTo()[0]->getAddress());
        self::assertSame('hello@injust.test', $email->getReplyTo()[0]->getAddress());
        self::assertSame('Injust: your withdrawal from order '.$order->reference.' is received', $email->getSubject());
        foreach (self::bodies($email) as $body) {
            foreach ([
                'Hi Joe Strummer,',
                'I received your withdrawal on 2026-11-12 at 10:30 (Paris time).',
                'Reference: '.$order->reference,
                'Product: Cours de guitare',
                'Price: €45.00 incl. tax',
                'Paid on: 2026-11-10',
                'Your message: Changed my mind.',
                'You will be refunded within 14 days of this date',
                'same payment method',
                'proportional to the service already provided',
                'credit note',
                'Keep this email',
            ] as $line) {
                self::assertStringContainsString($line, $body);
            }
        }
        self::assertSame([], $email->getAttachments());
    }

    public function testTheFrenchAcknowledgementWithoutNameOrMessage(): void
    {
        [$order, $withdrawal] = self::withdrawal('fr');

        self::mails(DocumentExamples::settings())->withdrawalAcknowledgement($order, $withdrawal);

        $email = self::sent();
        self::assertSame('Injust : accusé de réception de votre rétractation (commande '.$order->reference.')', $email->getSubject());
        foreach (self::bodies($email) as $body) {
            self::assertStringContainsString('Bonjour,', $body);
            self::assertStringContainsString('J’ai bien reçu votre rétractation le 12/11/2026 à 10:30 (heure de Paris).', $body);
            self::assertStringContainsString('Prix : 45,00 € TTC', $body);
            self::assertStringContainsString('sous 14 jours', $body);
            self::assertStringContainsString('proportionnel au service déjà fourni', $body);
            self::assertStringNotContainsString('Votre message', $body);
        }
    }

    public function testWhatTheCustomerTypedIsEscapedInTheHtmlPart(): void
    {
        [$order, $withdrawal] = self::withdrawal('en', '<b>Joe</b>', '<script>x</script>');

        self::mails(DocumentExamples::settings())->withdrawalAcknowledgement($order, $withdrawal);

        $html = (string) self::sent()->getHtmlBody();
        self::assertStringNotContainsString('<b>Joe</b>', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testTheNoticeGoesToTheContactAddressWithEverythingNeededToRefund(): void
    {
        [$order, $withdrawal] = self::withdrawal('fr', 'Joe Strummer', 'Plus le temps.');

        self::mails(DocumentExamples::settings())->withdrawalNotice($order, $withdrawal);

        $email = self::sent();
        self::assertSame('hello@injust.test', $email->getTo()[0]->getAddress());
        self::assertSame('joe@example.test', $email->getReplyTo()[0]->getAddress());
        self::assertSame('Withdrawal: order '.$order->reference.' (Cours de guitare)', $email->getSubject());
        $body = (string) $email->getTextBody();
        foreach ([
            'Received on 2026-11-12 at 10:30 (Paris time).',
            'Reference: '.$order->reference,
            'Price: €45.00',
            'Paid on: 2026-11-10',
            'Customer: Joe Strummer <joe@example.test>',
            'Message: Plus le temps.',
            'Refund it within 14 days',
        ] as $line) {
            self::assertStringContainsString($line, $body);
        }
    }

    public function testWithoutAContactAddressTheNoticeGoesToTheSeller(): void
    {
        [$order, $withdrawal] = self::withdrawal('en');

        self::mails(DocumentExamples::settings(contactEmail: ''))->withdrawalNotice($order, $withdrawal);

        self::assertSame('louis@injust.test', self::sent()->getTo()[0]->getAddress());
    }

    /** @return array{OrderView, WithdrawalRequestView} */
    private static function withdrawal(string $locale, ?string $name = null, ?string $reason = null): array
    {
        $order = DocumentExamples::paidOrder(locale: $locale);
        $request = WithdrawalRequest::receive($order, 'joe@example.test', $name, $reason, $locale, new \DateTimeImmutable('2026-11-12 09:30:00 UTC'));
        $order->withdraw(new \DateTimeImmutable('2026-11-12 09:30:00 UTC'));

        return [OrderView::of($order, $request), WithdrawalRequestView::of($request)];
    }

    private static function mails(SettingsView $settings): SymfonySalesMails
    {
        $documents = new class implements SalesDocuments {
            public function invoicePdf(InvoiceView $invoice): string
            {
                return '';
            }

            public function creditNotePdf(CreditNoteView $creditNote): string
            {
                return '';
            }

            public function legalPdf(LegalDocumentView $document, string $locale): string
            {
                return '';
            }

            public function licence(OrderView $order): string
            {
                return 'licence '.$order->reference;
            }
        };

        return new SymfonySalesMails(self::getContainer()->get(MailerInterface::class), self::getContainer()->get('translator'), DocumentExamples::queries($settings), $documents, new FakeDownloadLinks(), 'Injust <noreply@injust.test>', 'en');
    }

    private static function sent(): Email
    {
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    /** @return list<string> */
    private static function bodies(Email $email): array
    {
        $html = $email->getHtmlBody();

        return [
            trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', \is_string($html) ? $html : '')), \ENT_QUOTES | \ENT_HTML5))),
            trim((string) preg_replace('/\s+/u', ' ', (string) $email->getTextBody())),
        ];
    }
}
