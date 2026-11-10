<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Contact;

use App\Application\Contact\PurgeContactMessages\PurgeContactMessages;
use App\Application\Contact\PurgeContactMessages\PurgeContactMessagesHandler;
use App\Application\Transaction;
use App\Domain\Contact\ContactMessage;
use App\Domain\Contact\ContactMessageRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

final class PurgeContactMessagesHandlerTest extends TestCase
{
    public function testTheMessagesBeforeTheRetentionLimitAreRemovedThenCommitted(): void
    {
        /** @var \ArrayObject<int, string> $log */
        $log = new \ArrayObject();
        $messages = new class($log) implements ContactMessageRepository {
            /** @param \ArrayObject<int, string> $log */
            public function __construct(private readonly \ArrayObject $log)
            {
            }

            public function add(ContactMessage $message): void
            {
            }

            public function get(Ulid $id): ContactMessage
            {
                throw new \LogicException();
            }

            public function remove(ContactMessage $message): void
            {
            }

            public function removeReceivedBefore(\DateTimeImmutable $before): void
            {
                $this->log[] = 'remove before '.$before->format('Y-m-d H:i:s');
            }

            public function newestFirst(bool $withHandled): array
            {
                return [];
            }
        };
        $transaction = new class($log) implements Transaction {
            /** @param \ArrayObject<int, string> $log */
            public function __construct(private readonly \ArrayObject $log)
            {
            }

            public function commit(): void
            {
                $this->log[] = 'commit';
            }
        };

        new PurgeContactMessagesHandler($messages, new MockClock('2029-11-10 10:00:00'), $transaction)(new PurgeContactMessages());

        self::assertSame(['remove before 2026-11-10 10:00:00', 'commit'], $log->getArrayCopy());
    }
}
