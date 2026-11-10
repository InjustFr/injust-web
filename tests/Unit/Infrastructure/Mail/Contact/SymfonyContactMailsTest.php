<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail\Contact;

use App\Application\Contact\ContactMessageView;
use App\Domain\Contact\ContactMessage;
use App\Domain\Contact\ContactTopic;
use App\Infrastructure\Mail\SymfonyContactMails;
use App\Tests\Support\RecordingMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Translation\IdentityTranslator;

final class SymfonyContactMailsTest extends TestCase
{
    public function testAFailedMailIsLoggedNotThrown(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string|\Stringable, array<mixed>}> */
            public array $logs = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->logs[] = [$level, $message, $context];
            }
        };
        $mailer = new class implements MailerInterface {
            public int $attempts = 0;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                ++$this->attempts;
                throw new TransportException('SMTP down');
            }
        };
        $mails = self::mails($mailer, $logger, 'en');
        $message = self::message('joe@example.test');

        $mails->notify('hello@injust.test', $message);
        $mails->acknowledge($message);

        self::assertSame(2, $mailer->attempts);
        $context = ['message' => $message->id, 'error' => TransportException::class];
        self::assertSame([['error', 'A contact mail could not be sent.', $context], ['error', 'A contact mail could not be sent.', $context]], $logger->logs);
    }

    public function testTheMailsComeFromTheConfiguredSender(): void
    {
        $mailer = new RecordingMailer();
        $mails = self::mails($mailer, new NullLogger(), 'fr');
        $message = self::message('joe@example.test');

        $mails->notify('hello@injust.test', $message);
        $mails->acknowledge($message);

        self::assertSame([['Injust', 'noreply@injust.test'], ['Injust', 'noreply@injust.test']], array_map(static fn (TemplatedEmail $email): array => [$email->getFrom()[0]->getName(), $email->getFrom()[0]->getAddress()], $mailer->sent));
        self::assertSame(['fr', 'en'], array_map(static fn (TemplatedEmail $email): ?string => $email->getLocale(), $mailer->sent));
    }

    public function testAnAddressGetsAtMostItsQuotaOfAcknowledgementsWhateverItsCase(): void
    {
        $mailer = new RecordingMailer();
        $mails = self::mails($mailer, new NullLogger(), 'en', recipientLimit: 2);

        $mails->acknowledge(self::message('joe@example.test'));
        $mails->acknowledge(self::message('JOE@Example.test'));
        $mails->acknowledge(self::message('joe@example.test'));
        $mails->acknowledge(self::message('mick@example.test'));
        $mails->acknowledge(self::message('zoë@example.test'));
        $mails->acknowledge(self::message('ZOË@example.test'));
        $mails->acknowledge(self::message('zoë@example.test'));

        self::assertSame(['joe@example.test', 'JOE@Example.test', 'mick@example.test', 'zoë@example.test', 'ZOË@example.test'], array_map(static fn (TemplatedEmail $email): string => $email->getTo()[0]->getAddress(), $mailer->sent));
    }

    public function testTheGlobalQuotaOnlyCountsSentAcknowledgements(): void
    {
        $mailer = new RecordingMailer();
        $mails = self::mails($mailer, new NullLogger(), 'en', recipientLimit: 1, globalLimit: 2);

        $mails->acknowledge(self::message('joe@example.test'));
        $mails->acknowledge(self::message('joe@example.test'));
        $mails->acknowledge(self::message('mick@example.test'));
        $mails->acknowledge(self::message('paul@example.test'));

        self::assertSame(['joe@example.test', 'mick@example.test'], array_map(static fn (TemplatedEmail $email): string => $email->getTo()[0]->getAddress(), $mailer->sent));
    }

    public function testTheAcknowledgementEchoesNothingTheVisitorTyped(): void
    {
        $mailer = new RecordingMailer();

        self::mails($mailer, new NullLogger(), 'en')->acknowledge(self::message('joe@example.test'));

        self::assertSame('', $mailer->sent[0]->getTo()[0]->getName());
        self::assertSame(['offer' => null], $mailer->sent[0]->getContext());
    }

    private static function mails(MailerInterface $mailer, LoggerInterface $logger, string $ownerLocale, int $recipientLimit = 3, int $globalLimit = 100): SymfonyContactMails
    {
        return new SymfonyContactMails(
            $mailer,
            new IdentityTranslator(),
            $logger,
            self::limiter('recipient', $recipientLimit),
            self::limiter('global', $globalLimit),
            'Injust <noreply@injust.test>',
            $ownerLocale,
        );
    }

    private static function limiter(string $id, int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => $id, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 day'], new InMemoryStorage());
    }

    private static function message(string $email): ContactMessageView
    {
        return ContactMessageView::of(ContactMessage::receive('Joe', $email, ContactTopic::General, 'Hello there, Injust.', 'en', new \DateTimeImmutable()));
    }
}
