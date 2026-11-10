<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Contact;

use App\Domain\Contact\ContactMessage;
use App\Domain\Contact\ContactTopic;
use App\Domain\Shared\Exception\UnsupportedLocale;
use PHPUnit\Framework\TestCase;

final class ContactMessageTest extends TestCase
{
    public function testAMessageKeepsWhatTheVisitorSent(): void
    {
        $at = new \DateTimeImmutable('2026-11-10 10:00');

        $message = ContactMessage::receive(' Joe ', ' joe@example.test ', ContactTopic::Lessons, ' I want to learn riffs. ', 'fr', $at, 'OFFER', ' Pack ');

        self::assertSame(['Joe', 'joe@example.test', ContactTopic::Lessons, 'I want to learn riffs.', 'fr', $at, 'OFFER', 'Pack', null], [
            $message->name(), $message->email(), $message->topic(), $message->body(), $message->locale(), $message->receivedAt(), $message->offerRef(), $message->offerLabel(), $message->handledAt(),
        ]);
    }

    public function testAMessageWithoutOfferHasNone(): void
    {
        $message = ContactMessage::receive('Joe', 'joe@example.test', ContactTopic::General, 'Hello there, Injust.', 'en', new \DateTimeImmutable());

        self::assertSame([null, null], [$message->offerRef(), $message->offerLabel()]);
    }

    public function testALongOfferLabelIsCut(): void
    {
        $message = ContactMessage::receive('Joe', 'joe@example.test', ContactTopic::Lessons, 'Hello there, Injust.', 'en', new \DateTimeImmutable(), 'OFFER', str_repeat('é', 205));

        self::assertSame(str_repeat('é', ContactMessage::MAX_OFFER_LABEL), $message->offerLabel());
    }

    public function testAMessageIsHandledOnceThenReopened(): void
    {
        $message = ContactMessage::receive('Joe', 'joe@example.test', ContactTopic::General, 'Hello there, Injust.', 'en', new \DateTimeImmutable());
        $first = new \DateTimeImmutable('2026-11-10 11:00');

        $message->markHandled($first);
        $message->markHandled(new \DateTimeImmutable('2026-11-10 12:00'));

        self::assertSame($first, $message->handledAt());

        $message->markUnhandled();

        self::assertNull($message->handledAt());
    }

    public function testOnlyTheSiteLanguagesAreAccepted(): void
    {
        self::assertSame(['en', 'fr'], array_map(static fn (string $locale): string => ContactMessage::receive('Joe', 'joe@example.test', ContactTopic::General, 'Hello there, Injust.', $locale, new \DateTimeImmutable())->locale(), ['en', 'fr']));

        try {
            ContactMessage::receive('Joe', 'joe@example.test', ContactTopic::General, 'Hello there, Injust.', 'de', new \DateTimeImmutable());
            self::fail('A German message was accepted.');
        } catch (UnsupportedLocale $refused) {
            self::assertSame(['shared.unsupported_locale', ['locale' => 'de']], [$refused->getMessage(), $refused->parameters()]);
        }
    }
}
